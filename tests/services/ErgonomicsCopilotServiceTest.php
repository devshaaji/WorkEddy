<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Repositories\CopilotAuditRepository;
use WorkEddy\Repositories\ControlActionRepository;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Services\CopilotAuditService;
use WorkEddy\Services\CopilotDeterministicService;
use WorkEddy\Services\CopilotNarrativeService;
use WorkEddy\Services\CopilotRedactionService;
use WorkEddy\Services\Ergonomics\AssessmentEngine;
use WorkEddy\Services\ErgonomicsCopilotService;
use WorkEddy\Services\ScanComparisonService;

final class ErgonomicsCopilotServiceTest extends TestCase
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
            'COPILOT_AUDIT_ENABLED',
            'COPILOT_AUDIT_REDACT',
        ]);

        $this->setEnv('OPENAI_API_KEY', 'test-key');
        $this->setEnv('COPILOT_LLM_ENABLED', 'true');
        $this->setEnv('COPILOT_LLM_MODEL', 'gpt-4.1-mini');
        $this->setEnv('COPILOT_LLM_TIMEOUT_MS', '6000');
        $this->setEnv('COPILOT_AUDIT_ENABLED', 'false');
        $this->setEnv('COPILOT_AUDIT_REDACT', 'true');
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

    public function testSupervisorPersonaReturnsEnrichedEvidenceBackedBrief(): void
    {
        $service = $this->makeService(
            static fn (): array => [
                'choices' => [[
                    'message' => [
                        'content' => '{"executive_summary":"Shift risk is concentrated in one task cluster.","why_this_matters":"High-risk scans remain above target.","recommended_actions_text":"Assign owners, verify interim controls, and run closure scans."}',
                    ],
                ]],
            ]
        );

        $result = $service->assist(5, 99, 'supervisor', ['window_days' => 14]);

        $this->assertSame('supervisor', $result['persona']);
        $this->assertSame('Shift risk brief (14d)', $result['result']['title']);
        $this->assertSame(12, $result['result']['summary']['total_scans']);
        $this->assertSame(3, $result['result']['summary']['high_risk_scans']);
        $this->assertSame(1, $result['result']['summary']['open_control_actions']);

        $this->assertIsArray($result['citations']);
        $this->assertNotEmpty($result['citations']);
        $this->assertArrayHasKey('source_type', $result['citations'][0]);
        $this->assertArrayHasKey('source_id', $result['citations'][0]);
        $this->assertArrayHasKey('metric', $result['citations'][0]);
        $this->assertArrayHasKey('value', $result['citations'][0]);
        $this->assertArrayHasKey('time_window', $result['citations'][0]);
        $this->assertArrayHasKey('confidence', $result['citations'][0]);

        $this->assertSame('success', $result['llm']['status']);
        $this->assertSame('gpt-4.1-mini', $result['llm']['model']);
        $this->assertNotEmpty($result['narrative']['executive_summary']);
        $this->assertNotEmpty($result['audit_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f\-]{36}$/', $result['audit_id']);
    }

    public function testFallbackPathReturnsDeterministicOutputWhenLlmSchemaFails(): void
    {
        $service = $this->makeService(
            static fn (): array => [
                'choices' => [[
                    'message' => [
                        'content' => 'not valid json',
                    ],
                ]],
            ]
        );

        $result = $service->assist(5, 99, 'supervisor', ['window_days' => 14]);

        $this->assertSame('fallback', $result['llm']['status']);
        $this->assertSame('Shift risk brief (14d)', $result['result']['title']);
        $this->assertNotEmpty($result['narrative']['executive_summary']);
        $this->assertNotEmpty($result['narrative']['recommended_actions_text']);
        $this->assertNotEmpty($result['audit_id']);
    }

    public function testAssistRejectsUnsupportedPersona(): void
    {
        $service = $this->makeService(
            static fn (): array => [
                'choices' => [[
                    'message' => [
                        'content' => '{"executive_summary":"ok","why_this_matters":"ok","recommended_actions_text":"ok"}',
                    ],
                ]],
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('persona must be one of: supervisor, safety_manager, engineer, auditor');

        $service->assist(5, 99, 'free_chat', []);
    }

    /**
     * @param callable(array<string,mixed>,int):array<string,mixed> $transport
     */
    private function makeService(callable $transport): ErgonomicsCopilotService
    {
        $conn = $this->createMock(Connection::class);

        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql): array|false {
                if (str_contains($sql, 'COUNT(*) AS total_scans')) {
                    return [
                        'total_scans' => '12',
                        'high_risk' => '3',
                        'moderate_risk' => '5',
                    ];
                }

                return false;
            });

        $conn->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql): array {
                if (str_contains($sql, 'GROUP BY t.id, t.name')) {
                    return [[
                        'id' => 11,
                        'name' => 'Pallet Lift',
                        'scan_count' => '6',
                        'high_risk_count' => '2',
                    ]];
                }

                if (str_contains($sql, 'FROM control_actions ca')) {
                    return [
                        [
                            'id' => 901,
                            'organization_id' => 5,
                            'source_scan_id' => 44,
                            'status' => 'planned',
                            'assigned_to_user_id' => 7,
                            'created_by_user_id' => 2,
                            'control_code' => 'ENG_LIFT_ASSIST',
                            'control_title' => 'Deploy lift assist',
                            'hierarchy_level' => 'engineering',
                            'control_type' => 'permanent',
                            'priority' => 'medium',
                            'target_due_date' => null,
                            'implementation_notes' => null,
                            'worker_feedback_json' => null,
                            'verification_summary_json' => null,
                            'assigned_to_name' => 'Worker One',
                            'created_by_name' => 'Supervisor',
                        ],
                        [
                            'id' => 902,
                            'organization_id' => 5,
                            'source_scan_id' => 45,
                            'status' => 'verified',
                            'assigned_to_user_id' => 7,
                            'created_by_user_id' => 2,
                            'control_code' => 'ADMIN_JOB_ROTATION',
                            'control_title' => 'Rotation standard',
                            'hierarchy_level' => 'administrative',
                            'control_type' => 'interim',
                            'priority' => 'medium',
                            'target_due_date' => null,
                            'implementation_notes' => null,
                            'worker_feedback_json' => null,
                            'verification_summary_json' => null,
                            'assigned_to_name' => 'Worker One',
                            'created_by_name' => 'Supervisor',
                        ],
                    ];
                }

                return [];
            });

        $scanRepo = new ScanRepository($conn);
        $actionRepo = new ControlActionRepository($conn);
        $comparison = new ScanComparisonService($scanRepo, new AssessmentEngine());
        $deterministic = new CopilotDeterministicService($conn, $scanRepo, $actionRepo, $comparison);
        $narrative = new CopilotNarrativeService($transport);
        $audit = new CopilotAuditService(new CopilotAuditRepository($conn), new CopilotRedactionService());

        return new ErgonomicsCopilotService($deterministic, $narrative, $audit);
    }

    /**
     * @param list<string> $keys
     */
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
