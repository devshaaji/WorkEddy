<?php

declare(strict_types=1);

namespace WorkEddy\Services;

final class RecommendationPolicyDefaults
{
    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array
    {
        return [
            'thresholds' => [
                'trunk_flexion_high' => 45.0,
                'trunk_flexion_moderate' => 25.0,
                'upper_arm_elevation_high' => 60.0,
                'repetition_high' => 20,
                'lifting_load' => 12.0,
            ],
            'risk_multipliers' => [
                'high' => 1.15,
                'moderate' => 1.0,
                'low' => 0.9,
            ],
            'ranking' => [
                'cost_penalty_factor' => 1.1,
                'impact_penalty_factor' => 0.8,
                'reduction_factor' => 1.0,
                'strict_hierarchy' => true,
                'cost_weight' => ['low' => 1, 'medium' => 3, 'high' => 6],
                'impact_weight' => ['low' => 1, 'medium' => 3, 'high' => 5],
                'hierarchy_bonus' => [
                    'elimination' => 7,
                    'substitution' => 5,
                    'engineering' => 4,
                    'administrative' => 2,
                    'ppe' => 0,
                ],
            ],
            'feasibility' => [
                'minimum_total_score' => 60.0,
                'minimum_policy_compliance' => 55.0,
                'weights' => [
                    'hazard_fit' => 0.20,
                    'injury_likelihood_alignment' => 0.15,
                    'policy_compliance' => 0.15,
                    'worker_burden' => 0.10,
                    'industry_recognition' => 0.10,
                    'reliability_durability' => 0.10,
                    'availability' => 0.10,
                    'cost_effectiveness' => 0.10,
                ],
            ],
            'interim' => [
                'max_days_without_interim' => 14,
                'allow_ppe_interim' => true,
            ],
            'catalog' => [],
        ];
    }
}
