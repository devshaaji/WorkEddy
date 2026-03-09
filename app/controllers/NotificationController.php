<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Services\NotificationService;

final class NotificationController
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * GET /notifications — list recent notifications for the authenticated user.
     */
    public function index(array $claims): never
    {
        Auth::requireClaims($claims);
        $data = $this->notifications->listForUser(
            Auth::orgId($claims),
            Auth::userId($claims),
        );
        Response::json(['data' => $data]);
    }

    /**
     * GET /notifications/unread-count — lightweight count for badge.
     */
    public function unreadCount(array $claims): never
    {
        Auth::requireClaims($claims);
        $count = $this->notifications->unreadCount(
            Auth::orgId($claims),
            Auth::userId($claims),
        );
        Response::json(['data' => ['count' => $count]]);
    }

    /**
     * PUT /notifications/{id}/read — mark a single notification as read.
     */
    public function markRead(array $claims, int $id): never
    {
        Auth::requireClaims($claims);
        $this->notifications->markRead(Auth::orgId($claims), $id);
        Response::json(['message' => 'Notification marked as read']);
    }

    /**
     * PUT /notifications/read-all — mark all notifications as read.
     */
    public function markAllRead(array $claims): never
    {
        Auth::requireClaims($claims);
        $this->notifications->markAllRead(
            Auth::orgId($claims),
            Auth::userId($claims),
        );
        Response::json(['message' => 'All notifications marked as read']);
    }

    /**
     * POST /notifications/send — system admin sends a broadcast notification.
     */
    public function send(array $claims, array $body): never
    {
        Auth::requireRoles($claims, ['super_admin']);

        $title  = trim($body['title'] ?? '');
        $message = trim($body['message'] ?? '');
        $target = $body['target'] ?? 'all';       // 'all' | user-id
        $link   = trim($body['link'] ?? '');

        if ($title === '') {
            Response::json(['error' => 'Title is required'], 422);
        }

        $orgId  = Auth::orgId($claims);
        $userId = ($target !== 'all' && is_numeric($target)) ? (int) $target : null;

        $this->notifications->send(
            $orgId,
            $userId,
            'announcement',
            $title,
            $message !== '' ? $message : null,
            $link !== '' ? $link : null,
        );

        Response::json(['message' => 'Notification sent successfully']);
    }
}