<?php

declare(strict_types=1);

namespace WorkEddy\Controllers;

use WorkEddy\Helpers\Auth;
use WorkEddy\Helpers\Response;
use WorkEddy\Services\ErgonomicsCopilotService;

final class CopilotController
{
    public function __construct(private readonly ErgonomicsCopilotService $service) {}

    /**
     * @param array<string,mixed> $body
     */
    public function assist(array $claims, string $persona, array $body): never
    {
        $normalizedPersona = strtolower(trim(str_replace('-', '_', $persona)));
        $this->authorizePersona($claims, $normalizedPersona);

        $data = $this->service->assist(
            Auth::orgId($claims),
            Auth::userId($claims),
            $normalizedPersona,
            $body
        );

        Response::json(['data' => $data]);
    }

    private function authorizePersona(array $claims, string $persona): void
    {
        if (in_array($persona, ['supervisor', 'safety_manager', 'engineer'], true)) {
            Auth::requireRoles($claims, ['admin', 'supervisor']);
            return;
        }

        if ($persona === 'auditor') {
            Auth::requireRoles($claims, ['admin', 'supervisor', 'observer']);
            return;
        }

        Response::error('Unsupported copilot persona', 422);
    }
}
