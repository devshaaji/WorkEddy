<?php

declare(strict_types=1);

namespace WorkEddy\Models;

final class Task
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $organizationId,
        public readonly string  $name,
        public readonly ?string $description,
        public readonly ?string $workstation,
        public readonly ?string $department,
        public readonly string  $createdAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:             (int)    $row['id'],
            organizationId: (int)    $row['organization_id'],
            name:           (string) $row['name'],
            description:    isset($row['description']) ? (string) $row['description'] : null,
            workstation:    isset($row['workstation'])  ? (string) $row['workstation']  : null,
            department:     isset($row['department'])  ? (string) $row['department']  : null,
            createdAt:      (string) $row['created_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'organization_id' => $this->organizationId,
            'name'            => $this->name,
            'description'     => $this->description,
            'workstation'     => $this->workstation,
            'department'      => $this->department,
            'created_at'      => $this->createdAt,
        ];
    }
}