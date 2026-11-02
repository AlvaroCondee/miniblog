<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/admin/categories')]
#[IsGranted('ROLE_ADMIN')]
final class CategoryController extends AbstractController
{
    public function __construct(private readonly Connection $db)
    {
    }

    #[Route('/', name: 'category_list', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('name', TextType::class, [
                'label' => 'Nueva categoría',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = trim($form->getData()['name']);
            $slug = (new AsciiSlugger('es'))->slug($name)->lower()->toString();
            if ('' === $slug || false !== $this->db->fetchOne('SELECT id FROM category WHERE slug = ?', [$slug])) {
                $form->get('name')->addError(new \Symfony\Component\Form\FormError('Ya existe una categoría con ese nombre.'));
            } else {
                $this->db->insert('category', ['name' => $name, 'slug' => $slug]);
                $this->addFlash('success', 'Categoría creada.');

                return $this->redirectToRoute('category_list');
            }
        }

        return $this->render('backend/category/list.html.twig', [
            'categories' => $this->db->fetchAllAssociative('SELECT * FROM category ORDER BY name'),
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        if ($this->isCsrfTokenValid('delete-category'.$id, (string) $request->request->get('_token'))) {
            $this->db->update('entry', ['category_id' => null], ['category_id' => $id]);
            $this->db->delete('category', ['id' => $id]);
            $this->addFlash('success', 'Categoría borrada.');
        }

        return $this->redirectToRoute('category_list');
    }
}
