<?php

declare(strict_types=1);

namespace WorkEddy\Middleware;

use WorkEddy\Helpers\Response;
use WorkEddy\Repositories\WorkspaceRepository;

final class TenantMiddleware
{
    public function __construct(private readonly WorkspaceRepository $workspaces) {}

    /**
     * Verify the organization from JWT claims exists and return its data.
     */
    public function handle(array $claims): array
    {
        $orgId = (int) ($claims['org'] ?? 0);
        if ($orgId === 0) {
            Response::error('Unauthorized: missing organization context', 401);
        }

        try {
            return $this->workspaces->findById($orgId);
        } catch (\Throwable) {
            Response::error('Organization not found', 403);
        }
    }
}