<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Panel de gestión de entradas. Docentes y administradores gestionan todas las entradas y
 * pueden publicarlas; los alumnos solo ven y editan las suyas y las envían a revisión.
 */
#[Route('/admin')]
#[IsGranted('ROLE_USER')]
final class BackendController extends AbstractController
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';

    public function __construct(private readonly Connection $db)
    {
    }

    #[Route('/', name: 'backend', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->currentUser();
        $sql = <<<'SQL'
            SELECT e.*, u.display_name AS author, c.name AS category
            FROM entry e
            LEFT JOIN user u ON u.id = e.user_id
            LEFT JOIN category c ON c.id = e.category_id
            SQL;
        $params = [];
        if (!$user->isStaff()) {
            $sql .= ' WHERE e.user_id = ?';
            $params[] = $user->getId();
        }

        return $this->render('backend/main.html.twig', [
            'entries' => $this->db->fetchAllAssociative($sql.' ORDER BY e.id DESC', $params),
        ]);
    }

    #[Route('/entry/new', name: 'entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->currentUser();
        $form = $this->entryForm($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->db->insert('entry', [
                'title' => $data['title'],
                'content' => (string) $data['content'],
                'category_id' => $data['category_id'] ?? null,
                'status' => $data['status'],
                'user_id' => $user->getId(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->addFlash('success', 'Entrada guardada.');

            return $this->redirectToRoute('backend');
        }

        return $this->render('backend/entry/new.html.twig', ['form' => $form]);
    }

    #[Route('/entry/{id}/edit', name: 'entry_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->currentUser();
        $entry = $this->findManageable($id, $user);

        $form = $this->entryForm($user, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->db->update('entry', [
                'title' => $data['title'],
                'content' => (string) $data['content'],
                'category_id' => $data['category_id'] ?? null,
                'status' => $data['status'],
            ], ['id' => $id]);
            $this->addFlash('success', 'Entrada actualizada.');

            return $this->redirectToRoute('backend');
        }

        return $this->render('backend/entry/edit.html.twig', ['entry' => $entry, 'form' => $form]);
    }

    #[Route('/entry/{id}/delete', name: 'entry_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $entry = $this->findManageable($id, $this->currentUser());

        if ($this->isCsrfTokenValid('delete'.$id, (string) $request->request->get('_token'))) {
            $this->db->delete('entry', ['id' => $entry['id']]);
            $this->addFlash('success', 'Entrada borrada.');
        }

        return $this->redirectToRoute('backend');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /**
     * Docentes/administradores: cualquier entrada. Alumnos: solo las suyas y mientras no estén publicadas.
     *
     * @return array<string, mixed>
     */
    private function findManageable(int $id, User $user): array
    {
        $entry = $this->db->fetchAssociative('SELECT * FROM entry WHERE id = ?', [$id]);
        if (false === $entry) {
            throw $this->createNotFoundException();
        }
        if (!$user->isStaff() && ((int) $entry['user_id'] !== $user->getId() || self::STATUS_PUBLISHED === $entry['status'])) {
            throw $this->createAccessDeniedException();
        }

        return $entry;
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function entryForm(User $user, ?array $entry = null): FormInterface
    {
        $statuses = ['Borrador' => self::STATUS_DRAFT, 'Enviar a revisión' => self::STATUS_PENDING];
        if ($user->isStaff()) {
            $statuses['Publicada'] = self::STATUS_PUBLISHED;
        }
        $categories = $this->db->fetchAllKeyValue('SELECT name, id FROM category ORDER BY name');

        $builder = $this->createFormBuilder($entry ?? ['status' => self::STATUS_DRAFT])
            ->add('title', TextType::class, [
                'label' => 'Título',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
                'attr' => ['class' => 'span8', 'maxlength' => 255],
            ])
            ->add('category_id', ChoiceType::class, [
                'label' => 'Categoría',
                'required' => false,
                'placeholder' => 'Sin categoría',
                'choices' => $categories,
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenido',
                'required' => false,
                'constraints' => [new Assert\Length(max: 5000)],
                'attr' => ['class' => 'span8', 'rows' => 10],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Estado',
                'choices' => $statuses,
                'constraints' => [new Assert\NotBlank()],
            ]);

        return $builder->getForm();
    }
}
