<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

/** Páginas fijas del centro (horarios, normas, enlaces…), gestionadas por el administrador. */
final class PageController extends AbstractController
{
    public function __construct(
        private readonly Connection $db,
        #[Target('app.post_sanitizer')] private readonly HtmlSanitizerInterface $sanitizer,
    ) {
    }

    #[Route('/p/{slug}', name: 'page_show', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function show(string $slug): Response
    {
        $page = $this->db->fetchAssociative('SELECT * FROM page WHERE slug = ?', [$slug]);
        if (false === $page) {
            throw $this->createNotFoundException();
        }

        return $this->render('frontend/page.html.twig', ['page' => $page]);
    }

    #[Route('/admin/pages/', name: 'page_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        return $this->render('backend/page/list.html.twig', [
            'pages' => $this->db->fetchAllAssociative('SELECT * FROM page ORDER BY title'),
        ]);
    }

    #[Route('/admin/pages/new', name: 'page_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $form = $this->form(['in_menu' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== ($slug = $this->slug($form, null))) {
            $d = $form->getData();
            $this->db->insert('page', [
                'title' => $d['title'],
                'slug' => $slug,
                'content' => $this->sanitizer->sanitize((string) $d['content']),
                'in_menu' => $d['in_menu'] ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->addFlash('success', 'Página creada.');

            return $this->redirectToRoute('page_list');
        }

        return $this->render('backend/page/form.html.twig', ['form' => $form, 'title' => 'Nueva página']);
    }

    #[Route('/admin/pages/{id}/edit', name: 'page_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, int $id): Response
    {
        $page = $this->db->fetchAssociative('SELECT * FROM page WHERE id = ?', [$id]);
        if (false === $page) {
            throw $this->createNotFoundException();
        }
        $page['in_menu'] = (bool) $page['in_menu'];

        $form = $this->form($page);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && null !== ($slug = $this->slug($form, $id))) {
            $d = $form->getData();
            $this->db->update('page', [
                'title' => $d['title'],
                'slug' => $slug,
                'content' => $this->sanitizer->sanitize((string) $d['content']),
                'in_menu' => $d['in_menu'] ? 1 : 0,
            ], ['id' => $id]);
            $this->addFlash('success', 'Página actualizada.');

            return $this->redirectToRoute('page_list');
        }

        return $this->render('backend/page/form.html.twig', ['form' => $form, 'title' => 'Editar página']);
    }

    #[Route('/admin/pages/{id}/delete', name: 'page_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, int $id): Response
    {
        if ($this->isCsrfTokenValid('delete-page'.$id, (string) $request->request->get('_token'))) {
            $this->db->delete('page', ['id' => $id]);
            $this->addFlash('success', 'Página borrada.');
        }

        return $this->redirectToRoute('page_list');
    }

    /** Devuelve el slug definitivo, o null (añadiendo un error al formulario) si ya existe. */
    private function slug(FormInterface $form, ?int $selfId): ?string
    {
        $d = $form->getData();
        $slug = (new AsciiSlugger('es'))->slug(trim((string) ($d['slug'] ?: $d['title'])))->lower()->toString();
        $other = $this->db->fetchOne('SELECT id FROM page WHERE slug = ? AND id != ?', [$slug, $selfId ?? 0]);

        if ('' === $slug || false !== $other) {
            $form->get('slug')->addError(new FormError('Ya existe una página con esa dirección.'));

            return null;
        }

        return $slug;
    }

    /** @param array<string, mixed> $data */
    private function form(array $data): FormInterface
    {
        return $this->createFormBuilder($data)
            ->add('title', TextType::class, [
                'label' => 'Título',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
                'attr' => ['class' => 'span8'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Dirección (opcional; por defecto, el título)',
                'required' => false,
                'constraints' => [new Assert\Length(max: 150)],
            ])
            ->add('in_menu', CheckboxType::class, ['label' => 'Mostrar en el menú', 'required' => false])
            ->add('content', TextareaType::class, [
                'label' => 'Contenido',
                'required' => false,
                'constraints' => [new Assert\Length(max: 50000)],
            ])
            ->getForm();
    }
}
