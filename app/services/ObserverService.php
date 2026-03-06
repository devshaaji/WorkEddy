<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class ObserverService
{
    public function __construct(private readonly Connection $db) {}

    public function rate(int $scanId, int $observerId, float $score, string $category, ?string $notes): array
    {
        // Verify scan exists
        $scan = $this->db->fetchAssociative('SELECT id FROM scans WHERE id = :id LIMIT 1', ['id' => $scanId]);
        if (!$scan) {
            throw new RuntimeException('Scan not found');
        }

        $this->db->executeStatement(
            'INSERT INTO observer_ratings (scan_id, observer_id, observer_score, observer_category, notes, created_at)
             VALUES (:scan_id, :observer_id, :score, :category, :notes, NOW())',
            [
                'scan_id'     => $scanId,
                'observer_id' => $observerId,
                'score'       => $score,
                'category'    => $category,
                'notes'       => $notes,
            ]
        );

        return [
            'id'               => (int) $this->db->lastInsertId(),
            'scan_id'          => $scanId,
            'observer_id'      => $observerId,
            'observer_score'   => $score,
            'observer_category' => $category,
            'notes'            => $notes,
        ];
    }

    public function listByScan(int $scanId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT id, scan_id, observer_id, observer_score, observer_category, notes, created_at
             FROM observer_ratings WHERE scan_id = :scan_id ORDER BY id DESC',
            ['scan_id' => $scanId]
        );
    }
}
