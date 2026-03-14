<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Helpers\WorkerContract;

final class WorkerContractTest extends TestCase
{
    public function test_video_contract_exposes_shared_queue_routes_and_fields(): void
    {
        self::assertSame('scan_jobs', WorkerContract::videoQueueName());
        self::assertSame('/internal/worker/jobs/next', WorkerContract::videoRoute('next_job'));
        self::assertSame(
            ['scan_id', 'organization_id', 'metrics'],
            WorkerContract::requiredFields('video', 'complete')
        );
        self::assertSame(
            [
                'scan_id' => 10,
                'organization_id' => 22,
                'video_path' => '/storage/uploads/videos/sample.mp4',
                'model' => 'reba',
            ],
            WorkerContract::videoJobPayload([
                'scan_id' => 10,
                'organization_id' => 22,
                'video_path' => '/storage/uploads/videos/sample.mp4',
                'model' => 'reba',
            ])
        );
    }

    public function test_live_contract_exposes_shared_queue_routes_and_fields(): void
    {
        self::assertSame('live_session_jobs', WorkerContract::liveQueueName());
        self::assertSame('live_session_frame_batches', WorkerContract::liveFrameQueueName());
        self::assertSame('/internal/live-worker/frames', WorkerContract::liveRoute('frames'));
        self::assertSame('/internal/live-worker/frame-batches/next', WorkerContract::liveRoute('next_batch'));
        self::assertSame(
            ['session_id', 'organization_id', 'frames'],
            WorkerContract::requiredFields('live', 'frames')
        );

        $payload = WorkerContract::liveJobPayload([
            'session_id' => 8,
            'organization_id' => 5,
            'pose_engine' => 'yolo26',
            'multi_person_mode' => false,
            'model_variant' => 'yolo26n-pose',
            'model' => 'reba',
            'target_fps' => 5.0,
            'batch_window_ms' => 500,
            'max_e2e_latency_ms' => 2000,
            'smoothing_alpha' => 0.35,
            'min_joint_confidence' => 0.45,
            'tracking_max_distance' => 0.15,
        ]);

        self::assertSame(8, $payload['session_id']);
        self::assertSame('yolo26', $payload['pose_engine']);
        self::assertSame(0.15, $payload['tracking_max_distance']);

        $batchPayload = WorkerContract::liveFrameBatchPayload([
            'session_id' => 8,
            'organization_id' => 5,
            'pose_engine' => 'yolo26',
            'multi_person_mode' => false,
            'model_variant' => 'yolo26n-pose',
            'model' => 'reba',
            'target_fps' => 5.0,
            'batch_window_ms' => 500,
            'max_e2e_latency_ms' => 2000,
            'smoothing_alpha' => 0.35,
            'min_joint_confidence' => 0.45,
            'tracking_max_distance' => 0.15,
            'stale_batch_drop_multiplier' => 1.0,
            'frames' => [['frame_number' => 1, 'image_jpeg_base64' => 'abc123']],
        ]);

        self::assertSame(1, $batchPayload['frames'][0]['frame_number']);
        self::assertSame(1.0, $batchPayload['stale_batch_drop_multiplier']);
    }
}
