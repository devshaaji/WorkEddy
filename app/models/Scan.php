<?php

declare(strict_types=1);

namespace WorkEddy\Models;

final class Scan
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $organizationId,
        public readonly int     $userId,
        public readonly int     $taskId,
        public readonly string  $scanType,
        public readonly float   $rawScore,
        public readonly float   $normalizedScore,
        public readonly string  $riskCategory,
        public readonly ?int    $parentScanId,
        public readonly string  $status,
        public readonly ?string $videoPath,
        public readonly string  $createdAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:              (int)    $row['id'],
            organizationId:  (int)    $row['organization_id'],
            userId:          (int)    $row['user_id'],
            taskId:          (int)    $row['task_id'],
            scanType:        (string) $row['scan_type'],
            rawScore:        (float)  $row['raw_score'],
            normalizedScore: (float)  $row['normalized_score'],
            riskCategory:    (string) $row['risk_category'],
            parentScanId:    isset($row['parent_scan_id']) ? (int) $row['parent_scan_id'] : null,
            status:          (string) $row['status'],
            videoPath:       isset($row['video_path']) ? (string) $row['video_path'] : null,
            createdAt:       (string) $row['created_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'organization_id'  => $this->organizationId,
            'user_id'          => $this->userId,
            'task_id'          => $this->taskId,
            'scan_type'        => $this->scanType,
            'raw_score'        => $this->rawScore,
            'normalized_score' => $this->normalizedScore,
            'risk_category'    => $this->riskCategory,
            'parent_scan_id'   => $this->parentScanId,
            'status'           => $this->status,
            'video_path'       => $this->videoPath,
            'created_at'       => $this->createdAt,
        ];
    }
}