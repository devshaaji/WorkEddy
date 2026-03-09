<?php

declare(strict_types=1);

namespace WorkEddy\Models;

final class ScanResult
{
    public function __construct(
        public readonly int    $scanId,
        public readonly float  $rawScore,
        public readonly float  $normalizedScore,
        public readonly string $riskCategory,
        // Manual inputs
        public readonly ?float $weight              = null,
        public readonly ?float $frequency           = null,
        public readonly ?float $duration            = null,
        public readonly ?float $trunkAngleEstimate  = null,
        public readonly ?bool  $twisting            = null,
        public readonly ?bool  $overhead            = null,
        public readonly ?float $repetition          = null,
        // Video metrics
        public readonly ?float $maxTrunkAngle                = null,
        public readonly ?float $avgTrunkAngle                = null,
        public readonly ?float $shoulderElevationDuration    = null,
        public readonly ?int   $repetitionCount              = null,
        public readonly ?float $processingConfidence         = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'scan_id'                     => $this->scanId,
            'raw_score'                   => $this->rawScore,
            'normalized_score'            => $this->normalizedScore,
            'risk_category'               => $this->riskCategory,
            'weight'                      => $this->weight,
            'frequency'                   => $this->frequency,
            'duration'                    => $this->duration,
            'trunk_angle_estimate'        => $this->trunkAngleEstimate,
            'twisting'                    => $this->twisting,
            'overhead'                    => $this->overhead,
            'repetition'                  => $this->repetition,
            'max_trunk_angle'             => $this->maxTrunkAngle,
            'avg_trunk_angle'             => $this->avgTrunkAngle,
            'shoulder_elevation_duration' => $this->shoulderElevationDuration,
            'repetition_count'            => $this->repetitionCount,
            'processing_confidence'       => $this->processingConfidence,
        ], fn($v) => $v !== null);
    }
}