<?php

declare(strict_types=1);

namespace WorkEddy\Services\Ergonomics;

use RuntimeException;

/**
 * Assessment engine that routes scoring requests to the correct model.
 *
 * Usage:
 *   $engine = new AssessmentEngine();
 *   $result = $engine->assess('rula', $metricsArray);
 */
final class AssessmentEngine
{
    /** @var array<string, ErgonomicAssessmentInterface> */
    private array $models;

    public function __construct()
    {
        $this->register(new RulaService());
        $this->register(new RebaService());
        $this->register(new NioshService());
    }

    /**
     * Register a model implementation.
     */
    public function register(ErgonomicAssessmentInterface $model): void
    {
        $this->models[$model->modelName()] = $model;
    }

    /**
     * @return string[]  Available model names.
     */
    public function availableModels(): array
    {
        return array_keys($this->models);
    }

    /**
     * Return rich metadata for every registered model.
     *
     * @return list<array{value: string, label: string, desc: string, input_types: string[], fields: string[]}>
     */
    public function modelDescriptors(): array
    {
        $descriptors = [];
        $meta = [
            'rula'  => [
                'label'  => 'RULA',
                'desc'   => 'Rapid Upper Limb Assessment \u2014 upper-body posture focus (score 1\u20137)',
                'fields' => ['neck_angle','trunk_angle','upper_arm_angle','lower_arm_angle','wrist_angle','leg_score','load_weight','coupling'],
            ],
            'reba'  => [
                'label'  => 'REBA',
                'desc'   => 'Rapid Entire Body Assessment \u2014 whole-body posture (score 1\u201315)',
                'fields' => ['neck_angle','trunk_angle','upper_arm_angle','lower_arm_angle','wrist_angle','leg_score','load_weight','coupling'],
            ],
            'niosh' => [
                'label'  => 'NIOSH',
                'desc'   => 'NIOSH Lifting Equation \u2014 manual lifting tasks (Lifting Index)',
                'fields' => ['load_weight','horizontal_distance','vertical_start','vertical_travel','twist_angle','frequency','coupling'],
            ],
        ];

        foreach ($this->models as $name => $svc) {
            $info = $meta[$name] ?? ['label' => strtoupper($name), 'desc' => '', 'fields' => []];
            $descriptors[] = [
                'value'       => $name,
                'label'       => $info['label'],
                'desc'        => $info['desc'],
                'input_types' => $svc->supportedInputTypes(),
                'fields'      => $info['fields'],
            ];
        }

        return $descriptors;
    }

    /**
     * Validate that a model + input type combination is allowed.
     */
    public function validateCombination(string $model, string $inputType): void
    {
        $svc = $this->resolve($model);
        if (!in_array($inputType, $svc->supportedInputTypes(), true)) {
            throw new RuntimeException(
                "Model '{$model}' does not support input type '{$inputType}'. "
                . "Supported: " . implode(', ', $svc->supportedInputTypes())
            );
        }
    }

    /**
     * Run validation + scoring for a given model.
     *
     * @return array{score: float, risk_level: string, recommendation: string,
     *               raw_score: float, normalized_score: float, risk_category: string}
     */
    public function assess(string $model, array $metrics): array
    {
        $svc = $this->resolve($model);
        $svc->validate($metrics);
        return $svc->calculateScore($metrics);
    }

    /**
     * Resolve the service for the given model name.
     */
    public function resolve(string $model): ErgonomicAssessmentInterface
    {
        $name = strtolower(trim($model));
        if (!isset($this->models[$name])) {
            throw new RuntimeException(
                "Unknown assessment model: '{$model}'. Available: " . implode(', ', $this->availableModels())
            );
        }
        return $this->models[$name];
    }
}
