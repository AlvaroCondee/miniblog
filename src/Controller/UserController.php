<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
    }

    #[Route('/', name: 'user_list', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('backend/user/list.html.twig', ['users' => $this->users->all()]);
    }

    #[Route('/new', name: 'user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->form(null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();
            if (null !== $this->users->findByUsername($d['username'])) {
                $form->get('username')->addError(new \Symfony\Component\Form\FormError('Ya existe ese usuario.'));
            } else {
                $this->users->insert($d['username'], $d['display_name'], $this->hash($d['password']), $d['role']);
                $this->addFlash('success', 'Usuario creado.');

                return $this->redirectToRoute('user_list');
            }
        }

        return $this->render('backend/user/form.html.twig', ['form' => $form, 'title' => 'Nuevo usuario']);
    }

    #[Route('/{id}/edit', name: 'user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $user = $this->users->find($id) ?? throw $this->createNotFoundException();
        $form = $this->form($user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d = $form->getData();
            $changes = ['display_name' => $d['display_name'], 'role' => $d['role']];
            if (!empty($d['password'])) {
                $changes['password'] = $this->hash($d['password']);
            }
            $this->users->update($id, $changes);
            $this->addFlash('success', 'Usuario actualizado.');

            return $this->redirectToRoute('user_list');
        }

        return $this->render('backend/user/form.html.twig', ['form' => $form, 'title' => 'Editar usuario']);
    }

    #[Route('/{id}/delete', name: 'user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $id) {
            $this->addFlash('error', 'No puedes borrar tu propio usuario.');
        } elseif ($this->isCsrfTokenValid('delete-user'.$id, (string) $request->request->get('_token'))) {
            $this->users->delete($id);
            $this->addFlash('success', 'Usuario borrado.');
        }

        return $this->redirectToRoute('user_list');
    }

    private function hash(string $plain): string
    {
        return $this->hashers->getPasswordHasher(User::class)->hash($plain);
    }

    private function form(?User $user): \Symfony\Component\Form\FormInterface
    {
        $b = $this->createFormBuilder($user ? [
            'username' => $user->getUserIdentifier(),
            'display_name' => $user->getDisplayName(),
            'role' => $user->getRole(),
        ] : ['role' => User::ROLE_STUDENT])
            ->add('username', TextType::class, [
                'label' => 'Usuario',
                'disabled' => null !== $user,
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            ])
            ->add('display_name', TextType::class, [
                'label' => 'Nombre',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('role', ChoiceType::class, ['label' => 'Rol', 'choices' => User::ROLE_LABELS])
            ->add('password', PasswordType::class, [
                'label' => null === $user ? 'Contraseña' : 'Nueva contraseña (vacío = no cambiar)',
                'required' => null === $user,
                'constraints' => null === $user
                    ? [new Assert\NotBlank(), new Assert\Length(min: 8)]
                    : [new Assert\Length(min: 8)],
            ]);

        return $b->getForm();
    }
}
