<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/admin')]
final class BackendController extends AbstractController
{
    public function __construct(private readonly Connection $db)
    {
    }

    #[Route('/', name: 'backend', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('backend/main.html.twig', [
            'entries' => $this->db->fetchAllAssociative('SELECT * FROM entry ORDER BY id'),
        ]);
    }

    #[Route('/entry/new', name: 'entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->entryForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->db->insert('entry', [
                'title' => $data['title'],
                'content' => (string) $data['content'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->redirectToRoute('backend');
        }

        return $this->render('backend/entry/new.html.twig', ['form' => $form]);
    }

    #[Route('/entry/{id}/edit', name: 'entry_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $entry = $this->db->fetchAssociative('SELECT * FROM entry WHERE id = ?', [$id]);

        if (false === $entry) {
            return $this->redirectToRoute('backend');
        }

        $form = $this->entryForm($entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->db->update(
                'entry',
                ['title' => $data['title'], 'content' => (string) $data['content']],
                ['id' => $id],
            );

            return $this->redirectToRoute('backend');
        }

        return $this->render('backend/entry/edit.html.twig', ['entry' => $entry, 'form' => $form]);
    }

    #[Route('/entry/{id}/delete', name: 'entry_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, (string) $request->request->get('_token'))) {
            $this->db->delete('entry', ['id' => $id]);
        }

        return $this->redirectToRoute('backend');
    }

    /**
     * @param array<string, mixed>|null $entry
     */
    private function entryForm(?array $entry = null): FormInterface
    {
        $builder = $this->createFormBuilder($entry, ['csrf_protection' => true])
            ->add('title', TextType::class, [
                'label' => 'Título',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
                'attr' => ['class' => 'span8', 'maxlength' => 255],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenido',
                'required' => false,
                'constraints' => [new Assert\Length(max: 5000)],
                'attr' => ['class' => 'span8', 'rows' => 10],
            ]);

        if (null !== $entry) {
            $builder->add('created_at', TextType::class, [
                'label' => 'Fecha creación',
                'disabled' => true,
                'attr' => ['class' => 'span8'],
            ]);
        }

        return $builder->getForm();
    }
}
