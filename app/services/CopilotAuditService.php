<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;
use WorkEddy\Repositories\CopilotAuditRepository;

final class CopilotAuditService
{
    public function __construct(
        private readonly CopilotAuditRepository $repo,
        private readonly CopilotRedactionService $redactor,
    ) {}

    /**
     * @param array<string,mixed> $requestPayload
     * @param array<string,mixed> $deterministicBundle
     * @param array<string,mixed> $llmPrompt
     * @param array<string,mixed>|null $llmRawResponse
     * @param array<string,mixed> $responsePayload
     * @param array<string,mixed> $llmUsage
     */
    public function log(
        int $organizationId,
        int $userId,
        string $persona,
        array $requestPayload,
        array $deterministicBundle,
        array $llmPrompt,
        ?array $llmRawResponse,
        array $responsePayload,
        string $llmStatus,
        array $llmUsage = [],
    ): string {
        $auditId = $this->uuidV4();

        if (!$this->envBool('COPILOT_AUDIT_ENABLED', true)) {
            return $auditId;
        }

        $redact = $this->envBool('COPILOT_AUDIT_REDACT', true);
        $responseWithAuditId = $responsePayload;
        $responseWithAuditId['audit_id'] = $auditId;

        $requestStored = $redact ? $this->redactor->redact($requestPayload) : $requestPayload;
        $bundleStored = $redact ? $this->redactor->redact($deterministicBundle) : $deterministicBundle;
        $promptStored = $redact ? $this->redactor->redact($llmPrompt) : $llmPrompt;
        $rawStored = $redact ? $this->redactor->redact($llmRawResponse) : $llmRawResponse;
        $responseStored = $redact ? $this->redactor->redact($responseWithAuditId) : $responseWithAuditId;

        $status = in_array($llmStatus, ['success', 'fallback', 'disabled'], true) ? $llmStatus : 'fallback';

        $this->repo->create([
            'id' => $auditId,
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'persona' => $persona,
            'request_payload_redacted' => $requestStored,
            'deterministic_bundle_redacted' => $bundleStored,
            'llm_prompt_redacted' => $promptStored,
            'llm_response_redacted' => $rawStored,
            'response_payload_redacted' => $responseStored,
            'llm_status' => $status,
            'llm_request_count' => max(0, (int) ($llmUsage['request_count'] ?? 0)),
            'llm_prompt_tokens' => isset($llmUsage['prompt_tokens']) ? max(0, (int) $llmUsage['prompt_tokens']) : null,
            'llm_completion_tokens' => isset($llmUsage['completion_tokens']) ? max(0, (int) $llmUsage['completion_tokens']) : null,
            'llm_total_tokens' => isset($llmUsage['total_tokens']) ? max(0, (int) $llmUsage['total_tokens']) : null,
        ]);

        return $auditId;
    }

    private function uuidV4(): string
    {
        try {
            $data = random_bytes(16);
        } catch (\Throwable) {
            throw new RuntimeException('Unable to generate audit id');
        }

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split(bin2hex($data), 4)
        );
    }

    private function envBool(string $key, bool $default): bool
    {
        $raw = getenv($key);
        if ($raw === false) {
            return $default;
        }

        $normalized = strtolower(trim((string) $raw));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
