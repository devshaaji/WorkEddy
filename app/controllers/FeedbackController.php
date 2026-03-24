<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Helpers\Validator;
use WorkEddy\Services\FeedbackService;

final class FeedbackController
{
    public function __construct(private readonly FeedbackService $feedback) {}

    /* ── Public: accept a feedback submission ─────────────────────── */

    public function submit(array $body): never
    {
        Validator::requireFields($body, ['type', 'message']);

        $allowed = ['improvement', 'issue', 'feature', 'other'];
        if (! in_array($body['type'], $allowed, true)) {
            Response::error('Invalid feedback type', 422);
        }

        if (trim($body['message']) === '') {
            Response::error('Message must not be empty', 422);
        }

        $feedback = $this->feedback->submit(
            name:    isset($body['name'])  && $body['name']  !== '' ? trim($body['name'])  : null,
            email:   isset($body['email']) && $body['email'] !== '' ? trim($body['email']) : null,
            type:    $body['type'],
            message: trim($body['message']),
        );

        Response::created(['data' => $feedback->toArray()]);
    }

    /* ── super_admin: list all feedback ───────────────────────────── */

    public function index(array $claims): never
    {
        Auth::requireRoles($claims, ['super_admin']);

        $status = isset($_GET['status']) && $_GET['status'] !== ''
            ? $_GET['status']
            : null;
        $limit  = isset($_GET['limit'])  ? max(1, min(500, (int) $_GET['limit']))  : 50;
        $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;

        $items = $this->feedback->list($status, $limit, $offset);
        $total = $this->feedback->count($status);

        Response::json([
            'data'  => array_map(fn ($f) => $f->toArray(), $items),
            'total' => $total,
        ]);
    }

    /* ── super_admin: update feedback status ──────────────────────── */

    public function updateStatus(array $claims, int $id, array $body): never
    {
        Auth::requireRoles($claims, ['super_admin']);

        Validator::requireFields($body, ['status']);
        $this->feedback->updateStatus($id, $body['status']);

        Response::json(['message' => 'Status updated']);
    }
}
