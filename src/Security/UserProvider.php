<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
final class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->users->findByUsername($identifier) ?? throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->users->find($user instanceof User ? $user->getId() : 0) ?? throw new UserNotFoundException();
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class;
    }
}
