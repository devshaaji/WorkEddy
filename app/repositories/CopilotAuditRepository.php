<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use DateTimeInterface;
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
                llm_status, llm_request_count, llm_prompt_tokens, llm_completion_tokens,
                llm_total_tokens, created_at
            ) VALUES (
                :id, :organization_id, :user_id, :persona,
                :request_payload_redacted, :deterministic_bundle_redacted,
                :llm_prompt_redacted, :llm_response_redacted, :response_payload_redacted,
                :llm_status, :llm_request_count, :llm_prompt_tokens, :llm_completion_tokens,
                :llm_total_tokens, NOW()
            )',
            [
                'id' => (string) $payload['id'],
                'organization_id' => (int) $payload['organization_id'],
                'user_id' => (int) $payload['user_id'],
                'persona' => (string) $payload['persona'],
                'request_payload_redacted' => $this->encodeJson($payload['request_payload_redacted'] ?? null),
                'deterministic_bundle_redacted' => $this->encodeJson($payload['deterministic_bundle_redacted'] ?? null),
                'llm_prompt_redacted' => $this->encodeJson($payload['llm_prompt_redacted'] ?? null),
                'llm_response_redacted' => $this->encodeJson($payload['llm_response_redacted'] ?? null),
                'response_payload_redacted' => $this->encodeJson($payload['response_payload_redacted'] ?? null),
                'llm_status' => (string) $payload['llm_status'],
                'llm_request_count' => max(0, (int) ($payload['llm_request_count'] ?? 0)),
                'llm_prompt_tokens' => isset($payload['llm_prompt_tokens']) ? max(0, (int) $payload['llm_prompt_tokens']) : null,
                'llm_completion_tokens' => isset($payload['llm_completion_tokens']) ? max(0, (int) $payload['llm_completion_tokens']) : null,
                'llm_total_tokens' => isset($payload['llm_total_tokens']) ? max(0, (int) $payload['llm_total_tokens']) : null,
            ]
        );
    }

    public function sumLlmRequestCountForPeriod(
        int $organizationId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd
    ): int {
        $row = $this->db->fetchAssociative(
            'SELECT COALESCE(SUM(llm_request_count), 0) AS total
             FROM copilot_audit_logs
             WHERE organization_id = :org_id
               AND created_at >= :period_start
               AND created_at < :period_end',
            [
                'org_id' => $organizationId,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        return (int) ($row['total'] ?? 0);
    }

    public function sumLlmTotalTokensForPeriod(
        int $organizationId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd
    ): int {
        $row = $this->db->fetchAssociative(
            'SELECT COALESCE(SUM(llm_total_tokens), 0) AS total
             FROM copilot_audit_logs
             WHERE organization_id = :org_id
               AND created_at >= :period_start
               AND created_at < :period_end',
            [
                'org_id' => $organizationId,
                'period_start' => $periodStart->format('Y-m-d H:i:s'),
                'period_end' => $periodEnd->format('Y-m-d H:i:s'),
            ]
        );

        return (int) ($row['total'] ?? 0);
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
