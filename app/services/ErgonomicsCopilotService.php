<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;

final class ErgonomicsCopilotService
{
    /** @var list<string> */
    private const PERSONAS = ['supervisor', 'safety_manager', 'engineer', 'auditor'];

    public function __construct(
        private readonly CopilotDeterministicService $deterministic,
        private readonly CopilotNarrativeService $narrative,
        private readonly CopilotAuditService $audit,
        private readonly ?UsageMeterService $usageMeter = null,
    ) {}

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function assist(int $organizationId, int $userId, string $persona, array $payload = []): array
    {
        $normalizedPersona = $this->normalizePersona($persona);
        $bundle = $this->deterministic->build($organizationId, $normalizedPersona, $payload);
        $llmBudget = $this->usageMeter?->llmBudget($organizationId);
        $narrative = $this->narrative->generate($normalizedPersona, $bundle, $llmBudget);

        $response = [
            'persona' => $normalizedPersona,
            'generated_at' => (new \DateTimeImmutable('now'))->format(DATE_ATOM),
            'guardrails' => is_array($bundle['guardrails'] ?? null) ? $bundle['guardrails'] : [],
            'result' => is_array($bundle['result'] ?? null) ? $bundle['result'] : [],
            'citations' => is_array($bundle['citations'] ?? null) ? $bundle['citations'] : [],
            'narrative' => is_array($narrative['narrative'] ?? null) ? $narrative['narrative'] : [],
            'llm' => is_array($narrative['llm'] ?? null) ? $narrative['llm'] : [
                'enabled' => false,
                'model' => trim((string) (getenv('COPILOT_LLM_MODEL') ?: 'gpt-4.1-mini')),
                'status' => 'fallback',
                'latency_ms' => 0,
                'error_code' => 'llm_unavailable',
            ],
        ];

        $auditId = $this->audit->log(
            $organizationId,
            $userId,
            $normalizedPersona,
            $payload,
            $bundle,
            is_array($narrative['prompt_payload'] ?? null) ? $narrative['prompt_payload'] : [],
            is_array($narrative['raw_response'] ?? null) ? $narrative['raw_response'] : null,
            $response,
            (string) ($response['llm']['status'] ?? 'fallback'),
            is_array($narrative['usage'] ?? null) ? $narrative['usage'] : [],
        );

        $response['audit_id'] = $auditId;

        return $response;
    }

    private function normalizePersona(string $persona): string
    {
        $normalized = strtolower(trim(str_replace('-', '_', $persona)));
        if (!in_array($normalized, self::PERSONAS, true)) {
            throw new RuntimeException('persona must be one of: supervisor, safety_manager, engineer, auditor');
        }

        return $normalized;
    }
}
