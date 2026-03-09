<?php

declare(strict_types=1);

namespace WorkEddy\Models;

final class User
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $organizationId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly string $createdAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:             (int)    $row['id'],
            organizationId: (int)    $row['organization_id'],
            name:           (string) $row['name'],
            email:          (string) $row['email'],
            role:           (string) $row['role'],
            createdAt:      (string) $row['created_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organizationId,
            'name'            => $this->name,
            'email'           => $this->email,
            'role'            => $this->role,
            'created_at'      => $this->createdAt,
        ];
    }
}