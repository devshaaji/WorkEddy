<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;

final class NotificationRepository
{
    public function __construct(private readonly Connection $db) {}

    /**
     * Insert a new notification row.
     */
    public function create(int $orgId, ?int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        $this->db->executeStatement(
            'INSERT INTO notifications (organization_id, user_id, type, title, body, link, is_read, created_at)
             VALUES (:org_id, :user_id, :type, :title, :body, :link, 0, NOW())',
            [
                'org_id'  => $orgId,
                'user_id' => $userId,
                'type'    => $type,
                'title'   => $title,
                'body'    => $body,
                'link'    => $link,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Recent notifications for an organisation (optionally filtered to a specific user or org-wide).
     * Returns newest-first, limited to 30 rows.
     */
    public function listForUser(int $orgId, int $userId, int $limit = 30): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, organization_id, user_id, type, title, body, link, is_read, created_at
             FROM notifications
             WHERE organization_id = :org_id
               AND (user_id IS NULL OR user_id = :user_id)
             ORDER BY created_at DESC
             LIMIT :lim',
            ['org_id' => $orgId, 'user_id' => $userId, 'lim' => $limit],
            ['org_id' => \Doctrine\DBAL\ParameterType::INTEGER, 'user_id' => \Doctrine\DBAL\ParameterType::INTEGER, 'lim' => \Doctrine\DBAL\ParameterType::INTEGER]
        );
    }

    /**
     * Count of unread notifications visible to a user.
     */
    public function unreadCount(int $orgId, int $userId): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT COUNT(*) AS cnt FROM notifications
             WHERE organization_id = :org_id
               AND (user_id IS NULL OR user_id = :user_id)
               AND is_read = 0',
            ['org_id' => $orgId, 'user_id' => $userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Mark a single notification as read (only if it belongs to the org).
     */
    public function markRead(int $orgId, int $notificationId): void
    {
        $this->db->executeStatement(
            'UPDATE notifications SET is_read = 1 WHERE id = :id AND organization_id = :org_id',
            ['id' => $notificationId, 'org_id' => $orgId]
        );
    }

    /**
     * Mark all notifications as read for a user in an org.
     */
    public function markAllRead(int $orgId, int $userId): void
    {
        $this->db->executeStatement(
            'UPDATE notifications SET is_read = 1
             WHERE organization_id = :org_id
               AND (user_id IS NULL OR user_id = :user_id)
               AND is_read = 0',
            ['org_id' => $orgId, 'user_id' => $userId]
        );
    }
}
