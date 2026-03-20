<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WorkEddy\Repositories\LeadingIndicatorRepository;
use WorkEddy\Services\LeadingIndicatorService;

final class LeadingIndicatorServiceTest extends TestCase
{
    public function testSubmitNormalizesAndReturnsCreatedPayload(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->expects($this->once())
            ->method('executeStatement')
            ->with(
                $this->stringContains('INSERT INTO worker_leading_indicators'),
                $this->callback(function (array $params): bool {
                    return $params['shift_date'] === '2026-03-11'
                        && $params['checkin_type'] === 'post_shift'
                        && $params['discomfort_level'] === 6
                        && $params['fatigue_level'] === 5
                        && $params['task_rotation_quality'] === 'fair'
                        && $params['psychosocial_load'] === 'moderate';
                })
            )
            ->willReturn(1);
        $conn->method('lastInsertId')->willReturn('501');

        $service = new LeadingIndicatorService(new LeadingIndicatorRepository($conn));

        $result = $service->submit(10, 77, [
            'task_id' => 12,
            'shift_date' => '2026-03-11',
            'discomfort_level' => 6,
            'fatigue_level' => 5,
            'micro_breaks_taken' => 2,
            'recovery_minutes' => 20,
            'overtime_minutes' => 30,
            'task_rotation_quality' => 'fair',
            'psychosocial_load' => 'moderate',
            'notes' => 'Felt shoulder fatigue after hour 6',
        ]);

        $this->assertSame(501, $result['id']);
        $this->assertSame(10, $result['organization_id']);
        $this->assertSame(77, $result['user_id']);
        $this->assertSame(12, $result['task_id']);
        $this->assertSame('post_shift', $result['checkin_type']);
    }

    public function testSubmitRejectsOutOfRangeValues(): void
    {
        $conn = $this->createMock(Connection::class);
        $service = new LeadingIndicatorService(new LeadingIndicatorRepository($conn));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('discomfort_level must be between 0 and 10');

        $service->submit(10, 77, [
            'shift_date' => '2026-03-11',
            'discomfort_level' => 11,
            'fatigue_level' => 5,
            'micro_breaks_taken' => 0,
            'recovery_minutes' => 0,
            'overtime_minutes' => 0,
            'task_rotation_quality' => 'good',
            'psychosocial_load' => 'low',
        ]);
    }

    public function testSummaryMapsRepositoryTypes(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')
            ->willReturn([
                'total_checkins' => '8',
                'avg_discomfort' => '4.25',
                'avg_fatigue' => '5.50',
                'avg_micro_breaks' => '2.00',
                'avg_recovery_minutes' => '18.75',
                'avg_overtime_minutes' => '26.00',
                'high_psychosocial_count' => '2',
                'poor_rotation_count' => '1',
            ]);
        $conn->method('fetchAllAssociative')->willReturn([]);

        $service = new LeadingIndicatorService(new LeadingIndicatorRepository($conn));
        $summary = $service->summary(10, 30);

        $this->assertSame(30, $summary['window_days']);
        $this->assertSame(8, $summary['total_checkins']);
        $this->assertSame(4.25, $summary['avg_discomfort']);
        $this->assertSame(2, $summary['high_psychosocial_count']);
    }

    public function testSubmitRejectsInvalidCheckinType(): void
    {
        $conn = $this->createMock(Connection::class);
        $service = new LeadingIndicatorService(new LeadingIndicatorRepository($conn));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checkin_type must be one of: pre_shift, mid_shift, post_shift');

        $service->submit(10, 77, [
            'checkin_type' => 'weekly',
            'shift_date' => '2026-03-11',
            'discomfort_level' => 5,
            'fatigue_level' => 4,
            'micro_breaks_taken' => 0,
            'recovery_minutes' => 0,
            'overtime_minutes' => 0,
            'task_rotation_quality' => 'fair',
            'psychosocial_load' => 'moderate',
        ]);
    }
}
