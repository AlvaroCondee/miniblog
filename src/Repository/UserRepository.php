<?php

declare(strict_types=1);

namespace App\Repository;

use App\Security\User;
use Doctrine\DBAL\Connection;

final class UserRepository
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function findByUsername(string $username): ?User
    {
        return $this->hydrate($this->db->fetchAssociative('SELECT * FROM user WHERE username = ?', [$username]));
    }

    public function find(int $id): ?User
    {
        return $this->hydrate($this->db->fetchAssociative('SELECT * FROM user WHERE id = ?', [$id]));
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->fetchAllAssociative('SELECT id, username, display_name, role, created_at FROM user ORDER BY display_name');
    }

    public function insert(string $username, string $displayName, string $hash, string $role): void
    {
        $this->db->insert('user', [
            'username' => $username,
            'display_name' => $displayName,
            'password' => $hash,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, string> $data */
    public function update(int $id, array $data): void
    {
        $this->db->update('user', $data, ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->update('entry', ['user_id' => null], ['user_id' => $id]);
        $this->db->delete('user', ['id' => $id]);
    }

    /** @param array<string, mixed>|false $row */
    private function hydrate(array|false $row): ?User
    {
        return $row ? new User((int) $row['id'], $row['username'], $row['display_name'], $row['password'], $row['role']) : null;
    }
}
