<?php

declare(strict_types=1);

namespace WorkEddy\Api\Services;

use Doctrine\DBAL\Connection;

final class ObserverService
{
    public function __construct(private Connection $db)
    {
    }

<<<<<<< codex/break-down-requirements-and-start-project-setup-43uxpf
    public function rate(int $organizationId, int $scanId, int $observerId, float $score, string $category, ?string $notes): array
    {
        $scan = $this->db->fetchAssociative('SELECT id, organization_id FROM scans WHERE id = :id LIMIT 1', ['id' => $scanId]);
        if (!$scan || (int) $scan['organization_id'] !== $organizationId) {
            throw new \RuntimeException('Scan not found in organization scope');
        }

=======
    public function rate(int $scanId, int $observerId, float $score, string $category, ?string $notes): array
    {
>>>>>>> main
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
<<<<<<< codex/break-down-requirements-and-start-project-setup-43uxpf

    public function listByScan(int $organizationId, int $scanId): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT r.id, r.scan_id, r.observer_id, u.name AS observer_name, r.observer_score, r.observer_category, r.notes, r.created_at
             FROM observer_ratings r
             INNER JOIN scans s ON s.id = r.scan_id
             INNER JOIN users u ON u.id = r.observer_id
             WHERE s.organization_id = :organization_id AND r.scan_id = :scan_id
             ORDER BY r.id DESC',
            ['organization_id' => $organizationId, 'scan_id' => $scanId]
        );
    }
=======
>>>>>>> main
}
