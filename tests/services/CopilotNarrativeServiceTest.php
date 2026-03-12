<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Services\CopilotNarrativeService;

final class CopilotNarrativeServiceTest extends TestCase
{
    /** @var array<string,string|false> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupEnv([
            'OPENAI_API_KEY',
            'COPILOT_LLM_ENABLED',
            'COPILOT_LLM_MODEL',
            'COPILOT_LLM_TIMEOUT_MS',
        ]);

        $this->setEnv('OPENAI_API_KEY', 'test-key');
        $this->setEnv('COPILOT_LLM_ENABLED', 'true');
        $this->setEnv('COPILOT_LLM_MODEL', 'gpt-4.1-mini');
        $this->setEnv('COPILOT_LLM_TIMEOUT_MS', '2500');
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === false) {
                putenv($key);
                continue;
            }
            putenv($key . '=' . $value);
        }

        parent::tearDown();
    }

    public function testGenerateReturnsParsedNarrativeOnValidSchema(): void
    {
        $service = new CopilotNarrativeService(
            static fn (): array => [
                'choices' => [[
                    'message' => [
                        'content' => '{"executive_summary":"A","why_this_matters":"B","recommended_actions_text":"C"}',
                    ],
                ]],
            ]
        );

        $result = $service->generate('supervisor', $this->bundle());

        $this->assertSame('success', $result['llm']['status']);
        $this->assertSame('A', $result['narrative']['executive_summary']);
        $this->assertSame('B', $result['narrative']['why_this_matters']);
        $this->assertSame('C', $result['narrative']['recommended_actions_text']);
    }

    public function testGenerateFallsBackWhenSchemaIsMalformed(): void
    {
        $service = new CopilotNarrativeService(
            static fn (): array => [
                'choices' => [[
                    'message' => [
                        'content' => '{"executive_summary":"Only one field"}',
                    ],
                ]],
            ]
        );

        $result = $service->generate('supervisor', $this->bundle());

        $this->assertSame('fallback', $result['llm']['status']);
        $this->assertSame('invalid_llm_schema', $result['llm']['error_code']);
        $this->assertNotEmpty($result['narrative']['executive_summary']);
        $this->assertNotEmpty($result['narrative']['recommended_actions_text']);
    }

    public function testGenerateReturnsDisabledWhenLlmIsOff(): void
    {
        $this->setEnv('COPILOT_LLM_ENABLED', 'false');

        $service = new CopilotNarrativeService(
            static fn (): array => [
                'choices' => [[
                    'message' => [
                        'content' => '{"executive_summary":"A","why_this_matters":"B","recommended_actions_text":"C"}',
                    ],
                ]],
            ]
        );

        $result = $service->generate('supervisor', $this->bundle());

        $this->assertSame('disabled', $result['llm']['status']);
        $this->assertFalse($result['llm']['enabled']);
        $this->assertNull($result['llm']['error_code']);
    }

    /** @return array<string,mixed> */
    private function bundle(): array
    {
        return [
            'facts' => ['window_days' => 7, 'high_risk_scans' => 3],
            'recommendations' => [
                ['priority' => 'high', 'action' => 'Assign control action owner'],
            ],
            'citations' => [
                [
                    'source_type' => 'scans_aggregate',
                    'source_id' => 'org:5',
                    'metric' => 'high_risk_scans',
                    'value' => 3,
                    'time_window' => '7d',
                    'confidence' => 0.98,
                ],
            ],
            'guardrails' => ['workflow_scoped_output_only'],
            'result' => [
                'title' => 'Shift risk brief (7d)',
                'summary' => 'High-risk scans remain elevated.',
            ],
        ];
    }

    /** @param list<string> $keys */
    private function backupEnv(array $keys): void
    {
        foreach ($keys as $key) {
            $this->envBackup[$key] = getenv($key);
        }
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
    }
}
