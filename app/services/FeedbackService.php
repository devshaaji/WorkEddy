<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use WorkEddy\Models\UserFeedback;
use WorkEddy\Repositories\FeedbackRepository;

final class FeedbackService
{
    public function __construct(private readonly FeedbackRepository $repo) {}

    public function submit(
        ?string $name,
        ?string $email,
        string  $type,
        string  $message,
    ): UserFeedback {
        return $this->repo->create($name, $email, $type, $message);
    }

    /** @return UserFeedback[] */
    public function list(?string $status = null, int $limit = 100, int $offset = 0): array
    {
        return $this->repo->listAll($status, $limit, $offset);
    }

    public function count(?string $status = null): int
    {
        return $this->repo->countAll($status);
    }

    public function updateStatus(int $id, string $status): void
    {
        $allowed = ['new', 'reviewed', 'actioned'];
        if (! in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
        $this->repo->updateStatus($id, $status);
    }
}
