<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_TEACHER = 'ROLE_TEACHER';
    public const ROLE_STUDENT = 'ROLE_STUDENT';

    public const ROLE_LABELS = [
        'Administrador' => self::ROLE_ADMIN,
        'Docente' => self::ROLE_TEACHER,
        'Alumno' => self::ROLE_STUDENT,
    ];

    public function __construct(
        private readonly int $id,
        private readonly string $username,
        private readonly string $displayName,
        private readonly string $password,
        private readonly string $role,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        return array_unique([$this->role, 'ROLE_USER']);
    }

    /** Puede revisar, publicar y gestionar cualquier entrada. */
    public function isStaff(): bool
    {
        return \in_array($this->role, [self::ROLE_ADMIN, self::ROLE_TEACHER], true);
    }

    public function eraseCredentials(): void
    {
    }

    public function getRole(): string
    {
        return $this->role;
    }
}
