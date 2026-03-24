<?php

declare(strict_types=1);

namespace WorkEddy\Models;

final class UserFeedback
{
    public function __construct(
        public readonly int     $id,
        public readonly ?string $name,
        public readonly ?string $email,
        public readonly string  $type,
        public readonly string  $message,
        public readonly string  $status,
        public readonly string  $createdAt,
        public readonly ?string $updatedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:        (int)    $row['id'],
            name:      isset($row['name'])       ? (string) $row['name']       : null,
            email:     isset($row['email'])      ? (string) $row['email']      : null,
            type:      (string) $row['type'],
            message:   (string) $row['message'],
            status:    (string) $row['status'],
            createdAt: (string) $row['created_at'],
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'type'       => $this->type,
            'message'    => $this->message,
            'status'     => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
