<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;

final class ObserverService
{
    public function __construct(private Connection $db)
    {
    }

    public function rate(int $scanId, int $observerId, float $score, string $category, ?string $notes): array
    {
        $this->db->executeStatement(
            'INSERT INTO observer_ratings (scan_id, observer_id, observer_score, observer_category, notes, created_at) VALUES (:scan_id, :observer_id, :observer_score, :observer_category, :notes, NOW())',
            ['scan_id' => $scanId, 'observer_id' => $observerId, 'observer_score' => $score, 'observer_category' => $category, 'notes' => $notes]
        );

        return [
            'id' => (int) $this->db->lastInsertId(),
            'scan_id' => $scanId,
            'observer_id' => $observerId,
            'observer_score' => $score,
            'observer_category' => $category,
            'notes' => $notes,
        ];
    }
}
