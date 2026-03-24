<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;
use WorkEddy\Models\UserFeedback;

final class FeedbackRepository
{
    public function __construct(private readonly Connection $db) {}

    public function create(
        ?string $name,
        ?string $email,
        string  $type,
        string  $message,
    ): UserFeedback {
        $this->db->insert('user_feedback', [
            'name'       => $name,
            'email'      => $email,
            'type'       => $type,
            'message'    => $message,
            'status'     => 'new',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }

    public function findById(int $id): UserFeedback
    {
        $row = $this->db->fetchAssociative(
            'SELECT * FROM user_feedback WHERE id = ?',
            [$id]
        );

        if ($row === false) {
            throw new \RuntimeException("Feedback #{$id} not found");
        }

        return UserFeedback::fromRow($row);
    }

    /** @return UserFeedback[] */
    public function listAll(?string $status = null, int $limit = 100, int $offset = 0): array
    {
        $sql    = 'SELECT * FROM user_feedback';
        $params = [];

        if ($status !== null) {
            $sql      .= ' WHERE status = ?';
            $params[]  = $status;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        $rows = $this->db->fetchAllAssociative($sql, $params);
        return array_map(fn (array $r) => UserFeedback::fromRow($r), $rows);
    }

    public function countAll(?string $status = null): int
    {
        $sql    = 'SELECT COUNT(*) FROM user_feedback';
        $params = [];

        if ($status !== null) {
            $sql      .= ' WHERE status = ?';
            $params[]  = $status;
        }

        return (int) $this->db->fetchOne($sql, $params);
    }

    public function updateStatus(int $id, string $status): void
    {
        $this->db->update('user_feedback', [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }
}
