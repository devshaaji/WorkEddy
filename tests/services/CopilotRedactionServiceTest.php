<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Services;

use PHPUnit\Framework\TestCase;
use WorkEddy\Services\CopilotRedactionService;

final class CopilotRedactionServiceTest extends TestCase
{
    public function testRedactMasksSensitiveAndFreeTextFields(): void
    {
        $service = new CopilotRedactionService();

        $payload = [
            'id' => 11,
            'name' => 'Worker One',
            'email' => 'worker@example.com',
            'notes' => 'This should never be persisted in clear text.',
            'safe_metric' => 62.5,
            'nested' => [
                'assigned_to_user_id' => 99,
                'source_scan_id' => 88,
                'worker_name' => 'Jane',
                'comment' => 'Free text comment',
                'confidence' => 0.92,
            ],
        ];

        $redacted = $service->redact($payload);

        $this->assertSame('[REDACTED]', $redacted['id']);
        $this->assertSame('[REDACTED]', $redacted['name']);
        $this->assertSame('[REDACTED]', $redacted['email']);
        $this->assertSame('[REDACTED]', $redacted['notes']);
        $this->assertSame(62.5, $redacted['safe_metric']);

        $this->assertSame('[REDACTED]', $redacted['nested']['assigned_to_user_id']);
        $this->assertSame('[REDACTED]', $redacted['nested']['source_scan_id']);
        $this->assertSame('[REDACTED]', $redacted['nested']['worker_name']);
        $this->assertSame('[REDACTED]', $redacted['nested']['comment']);
        $this->assertSame(0.92, $redacted['nested']['confidence']);
    }
}
