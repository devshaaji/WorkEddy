<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use WorkEddy\Repositories\ControlActionRepository;
use WorkEddy\Repositories\LeadingIndicatorRepository;
use WorkEddy\Repositories\ScanRepository;
use WorkEddy\Services\WorkerCoachingService;

final class WorkerCoachingServiceTest extends TestCase
{
    public function testCoachingReturnsLocalizedTipsFromEvidenceSignals(): void
    {
        $service = $this->makeService();

        $result = $service->coaching(11, 77, 'es');

        $this->assertSame('es', $result['language']);
        $this->assertSame('Español', $result['language_label']);

        $tipCodes = array_column($result['personalized_tips'], 'code');
        $this->assertContains('high_discomfort', $tipCodes);
        $this->assertContains('assigned_action', $tipCodes);
        $this->assertNotEmpty($result['pre_shift_self_checks']);
        $this->assertSame(1, $result['evidence']['open_assigned_actions']);
    }

    public function testCoachingFallsBackToEnglishForUnsupportedLanguage(): void
    {
        $service = $this->makeService();
        $result = $service->coaching(11, 77, 'unsupported');

        $this->assertSame('en', $result['language']);
        $this->assertSame('English', $result['language_label']);
    }

    private function makeService(): WorkerCoachingService
    {
        $conn = $this->createMock(Connection::class);

        $conn->method('fetchAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) {
                if (str_contains($sql, 'FROM worker_leading_indicators')) {
                    return [
                        'id' => 901,
                        'organization_id' => 11,
                        'user_id' => 77,
                        'task_id' => 9,
                        'checkin_type' => 'post_shift',
                        'shift_date' => '2026-03-12',
                        'discomfort_level' => 8,
                        'fatigue_level' => 8,
                        'micro_breaks_taken' => 0,
                        'recovery_minutes' => 10,
                        'overtime_minutes' => 60,
                        'task_rotation_quality' => 'poor',
                        'psychosocial_load' => 'high',
                        'notes' => null,
                        'created_at' => '2026-03-12 09:00:00',
                    ];
                }

                if (str_contains($sql, 'FROM scans s') && array_key_exists('user_id', $params)) {
                    return [
                        'id' => 55,
                        'organization_id' => 11,
                        'user_id' => 77,
                        'task_id' => 9,
                        'scan_type' => 'video',
                        'model' => 'reba',
                        'raw_score' => 8,
                        'normalized_score' => 62.5,
                        'risk_category' => 'high',
                        'status' => 'completed',
                        'created_at' => '2026-03-12 08:00:00',
                        'result_score' => 8,
                        'risk_level' => 'high',
                        'recommendation' => '',
                        'algorithm_version' => 'reba_official_v1',
                    ];
                }

                if (str_contains($sql, 'FROM scans s') && array_key_exists('id', $params)) {
                    return [
                        'id' => 55,
                        'organization_id' => 11,
                        'user_id' => 77,
                        'task_id' => 9,
                        'scan_type' => 'video',
                        'model' => 'reba',
                        'raw_score' => 8,
                        'normalized_score' => 62.5,
                        'risk_category' => 'high',
                        'status' => 'completed',
                        'video_path' => null,
                        'error_message' => null,
                        'parent_scan_id' => null,
                        'created_at' => '2026-03-12 08:00:00',
                        'result_score' => 8,
                        'risk_level' => 'high',
                        'recommendation' => '',
                        'algorithm_version' => 'reba_official_v1',
                    ];
                }

                if (str_contains($sql, 'FROM scan_metrics')) {
                    return ['scan_id' => 55, 'trunk_angle' => 48];
                }

                return false;
            });

        $conn->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $params = []) {
                if (str_contains($sql, 'FROM scan_control_recommendations')) {
                    return [];
                }

                if (str_contains($sql, 'FROM control_actions ca') && array_key_exists('scan_id', $params)) {
                    return [];
                }

                if (str_contains($sql, 'FROM control_actions ca')) {
                    return [
                        [
                            'id' => 3001,
                            'organization_id' => 11,
                            'source_scan_id' => 55,
                            'source_control_id' => 222,
                            'control_code' => 'ENG_LIFT_ASSIST',
                            'control_title' => 'Deploy lift assist',
                            'hierarchy_level' => 'engineering',
                            'control_type' => 'permanent',
                            'assigned_to_user_id' => 77,
                            'created_by_user_id' => 3,
                            'status' => 'planned',
                            'priority' => 'medium',
                            'target_due_date' => null,
                            'implementation_notes' => null,
                            'worker_feedback_json' => null,
                            'verification_scan_id' => null,
                            'verification_summary_json' => null,
                            'implemented_at' => null,
                            'verified_at' => null,
                            'created_at' => '2026-03-12 09:10:00',
                            'updated_at' => '2026-03-12 09:10:00',
                            'assigned_to_name' => 'Worker One',
                            'created_by_name' => 'Supervisor',
                        ],
                    ];
                }

                return [];
            });

        return new WorkerCoachingService(
            new LeadingIndicatorRepository($conn),
            new ScanRepository($conn),
            new ControlActionRepository($conn),
        );
    }
}

