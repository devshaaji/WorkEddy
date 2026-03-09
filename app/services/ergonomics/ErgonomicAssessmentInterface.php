<?php

declare(strict_types=1);

namespace WorkEddy\Services\Ergonomics;

/**
 * Contract that every ergonomic assessment model must implement.
 *
 * Each model (RULA, REBA, NIOSH) validates its own input subset,
 * calculates a score, and returns a risk level with recommendation text.
 */
interface ErgonomicAssessmentInterface
{
    /**
     * @return string Model identifier: 'rula' | 'reba' | 'niosh'
     */
    public function modelName(): string;

    /**
     * Which input types this model supports.
     *
     * @return string[] e.g. ['manual','video'] or ['manual']
     */
    public function supportedInputTypes(): array;

    /**
     * Validate the metrics array. Throws RuntimeException on failure.
     */
    public function validate(array $metrics): void;

    /**
     * Calculate score from validated metrics.
     *
     * @return array{score: float, risk_level: string, recommendation: string,
     *               raw_score: float, normalized_score: float, risk_category: string,
     *               action_level_code?: int, action_level_label?: string, algorithm_version?: string}
     */
    public function calculateScore(array $metrics): array;

    /**
     * Map a numeric score to a risk level string.
     */
    public function getRiskLevel(float $score): string;
}