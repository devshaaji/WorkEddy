<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;

final class CopilotAuditRepository
{
    public function __construct(private readonly Connection $db) {}

    /**
     * @param array<string,mixed> $payload
     */
    public function create(array $payload): void
    {
        $this->db->executeStatement(
            'INSERT INTO copilot_audit_logs (
                id, organization_id, user_id, persona,
                request_payload_redacted, deterministic_bundle_redacted,
                llm_prompt_redacted, llm_response_redacted, response_payload_redacted,
                llm_status, created_at
            ) VALUES (
                :id, :organization_id, :user_id, :persona,
                :request_payload_redacted, :deterministic_bundle_redacted,
                :llm_prompt_redacted, :llm_response_redacted, :response_payload_redacted,
                :llm_status, NOW()
            )',
            [
                'id' => (string) $payload['id'],
                'organization_id' => (int) $payload['organization_id'],
                'user_id' => (int) $payload['user_id'],
                'persona' => (string) $payload['persona'],
                'request_payload_redacted' => json_encode($payload['request_payload_redacted'] ?? null, JSON_UNESCAPED_UNICODE),
                'deterministic_bundle_redacted' => json_encode($payload['deterministic_bundle_redacted'] ?? null, JSON_UNESCAPED_UNICODE),
                'llm_prompt_redacted' => json_encode($payload['llm_prompt_redacted'] ?? null, JSON_UNESCAPED_UNICODE),
                'llm_response_redacted' => json_encode($payload['llm_response_redacted'] ?? null, JSON_UNESCAPED_UNICODE),
                'response_payload_redacted' => json_encode($payload['response_payload_redacted'] ?? null, JSON_UNESCAPED_UNICODE),
                'llm_status' => (string) $payload['llm_status'],
            ]
        );
    }
}

