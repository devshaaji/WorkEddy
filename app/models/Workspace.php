<?php

declare(strict_types=1);

namespace WorkEddy\Models;

final class Workspace
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $plan,
        public readonly string $createdAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:        (int)    $row['id'],
            name:      (string) $row['name'],
            plan:      (string) $row['plan'],
            createdAt: (string) $row['created_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'plan'       => $this->plan,
            'created_at' => $this->createdAt,
        ];
    }
}