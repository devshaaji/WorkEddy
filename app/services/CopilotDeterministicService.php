<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use Doctrine\DBAL\Connection;
use RuntimeException;
use WorkEddy\Repositories\ControlActionRepository;
use WorkEddy\Repositories\ScanRepository;

final class CopilotDeterministicService
{
    /** @var list<string> */
    private const PERSONAS = ['supervisor', 'safety_manager', 'engineer', 'auditor'];

    public function __construct(
        private readonly Connection $db,
        private readonly ScanRepository $scans,
        private readonly ControlActionRepository $actions,
        private readonly ScanComparisonService $comparisons,
    ) {}

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function build(int $organizationId, string $persona, array $payload = []): array
    {
        $normalizedPersona = $this->normalizePersona($persona);

        return match ($normalizedPersona) {
            'supervisor' => $this->supervisorBundle($organizationId, $payload),
            'safety_manager' => $this->safetyManagerBundle($organizationId, $payload),
            'engineer' => $this->engineerBundle($organizationId, $payload),
            'auditor' => $this->auditorBundle($organizationId, $payload),
            default => throw new RuntimeException('Unsupported copilot persona'),
        };
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function supervisorBundle(int $organizationId, array $payload): array
    {
        $days = max(1, min(30, (int) ($payload['window_days'] ?? 7)));

        $totals = $this->db->fetchAssociative(
            'SELECT
                COUNT(*) AS total_scans,
                SUM(CASE WHEN risk_category = "high" THEN 1 ELSE 0 END) AS high_risk,
                SUM(CASE WHEN risk_category = "moderate" THEN 1 ELSE 0 END) AS moderate_risk
             FROM scans
             WHERE organization_id = :org_id
               AND status = "completed"
               AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)',
            [
                'org_id' => $organizationId,
                'days' => $days,
            ]
        ) ?: [];

        $topTasks = $this->db->fetchAllAssociative(
            'SELECT t.id, t.name,
                    COUNT(s.id) AS scan_count,
                    SUM(CASE WHEN s.risk_category = "high" THEN 1 ELSE 0 END) AS high_risk_count
             FROM scans s
             INNER JOIN tasks t ON t.id = s.task_id
             WHERE s.organization_id = :org_id
               AND s.status = "completed"
               AND s.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
             GROUP BY t.id, t.name
             ORDER BY high_risk_count DESC, scan_count DESC
             LIMIT 5',
            [
                'org_id' => $organizationId,
                'days' => $days,
            ]
        );

        $openActions = $this->actions->listByOrganization($organizationId, null, null, null, 200);
        $openActionCount = count(array_filter(
            $openActions,
            static fn (array $a): bool => in_array((string) ($a['status'] ?? ''), ['planned', 'in_progress', 'implemented'], true)
        ));

        $result = [
            'title' => "Shift risk brief ({$days}d)",
            'summary' => [
                'total_scans' => (int) ($totals['total_scans'] ?? 0),
                'high_risk_scans' => (int) ($totals['high_risk'] ?? 0),
                'moderate_risk_scans' => (int) ($totals['moderate_risk'] ?? 0),
                'open_control_actions' => $openActionCount,
            ],
            'recommended_next_steps' => [
                [
                    'priority' => 'high',
                    'action' => 'Start shift with high-risk task huddle and owner assignment',
                ],
                [
                    'priority' => 'medium',
                    'action' => 'Confirm interim administrative controls are active where permanent controls are pending',
                ],
                [
                    'priority' => 'medium',
                    'action' => 'Schedule verification scans for implemented controls this week',
                ],
            ],
            'evidence' => [
                'top_tasks' => $topTasks,
            ],
        ];

        $citations = [
            $this->citation(
                'scans_aggregate',
                'org:' . $organizationId,
                'total_scans',
                (int) ($totals['total_scans'] ?? 0),
                "{$days}d",
                0.98
            ),
            $this->citation(
                'scans_aggregate',
                'org:' . $organizationId,
                'high_risk_scans',
                (int) ($totals['high_risk'] ?? 0),
                "{$days}d",
                0.98
            ),
            $this->citation(
                'control_actions_aggregate',
                'org:' . $organizationId,
                'open_control_actions',
                $openActionCount,
                "{$days}d",
                0.95
            ),
        ];

        foreach ($topTasks as $task) {
            $taskId = (int) ($task['id'] ?? 0);
            $citations[] = $this->citation(
                'task',
                $taskId > 0 ? (string) $taskId : (string) ($task['name'] ?? 'unknown'),
                'high_risk_count',
                (int) ($task['high_risk_count'] ?? 0),
                "{$days}d",
                0.94
            );
        }

        return [
            'facts' => [
                'window_days' => $days,
                'total_scans' => (int) ($totals['total_scans'] ?? 0),
                'high_risk_scans' => (int) ($totals['high_risk'] ?? 0),
                'moderate_risk_scans' => (int) ($totals['moderate_risk'] ?? 0),
                'open_control_actions' => $openActionCount,
                'top_tasks' => $topTasks,
            ],
            'recommendations' => $result['recommended_next_steps'],
            'citations' => $citations,
            'guardrails' => [
                'workflow_scoped_output_only',
                'evidence_citations_required',
                'no_open_ended_chat_generation',
            ],
            'result' => $result,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function safetyManagerBundle(int $organizationId, array $payload): array
    {
        $days = max(1, min(90, (int) ($payload['window_days'] ?? 30)));

        $controls = $this->db->fetchAllAssociative(
            'SELECT scr.id, scr.scan_id, scr.control_code, scr.title, scr.hierarchy_level, scr.rank_order,
                    scr.expected_risk_reduction_pct, s.task_id, s.normalized_score, s.created_at
             FROM scan_control_recommendations scr
             INNER JOIN scans s ON s.id = scr.scan_id
             WHERE s.organization_id = :org_id
               AND s.status = "completed"
               AND s.risk_category = "high"
               AND s.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
               AND scr.rank_order <= 3
             ORDER BY s.created_at DESC, scr.rank_order ASC
             LIMIT 12',
            [
                'org_id' => $organizationId,
                'days' => $days,
            ]
        );

        $plan = [];
        $citations = [];
        foreach ($controls as $control) {
            $plan[] = [
                'priority' => ((string) ($control['hierarchy_level'] ?? '') === 'elimination') ? 'high' : 'medium',
                'control_code' => (string) ($control['control_code'] ?? ''),
                'control_title' => (string) ($control['title'] ?? ''),
                'hierarchy_level' => (string) ($control['hierarchy_level'] ?? ''),
                'expected_risk_reduction_pct' => (float) ($control['expected_risk_reduction_pct'] ?? 0),
                'source_scan_id' => (int) ($control['scan_id'] ?? 0),
                'task_id' => (int) ($control['task_id'] ?? 0),
            ];

            $citations[] = $this->citation(
                'scan_control_recommendation',
                (string) ($control['id'] ?? (($control['scan_id'] ?? '') . ':' . ($control['control_code'] ?? ''))),
                'expected_risk_reduction_pct',
                (float) ($control['expected_risk_reduction_pct'] ?? 0),
                "{$days}d",
                0.93
            );
        }

        $result = [
            'title' => "Corrective action draft ({$days}d high-risk window)",
            'summary' => 'Prioritize highest-order feasible controls, assign owners, and require verification scans for closure.',
            'draft_plan' => $plan,
            'evidence' => [
                'control_candidates' => count($plan),
            ],
        ];

        return [
            'facts' => [
                'window_days' => $days,
                'control_candidates' => count($plan),
                'high_risk_controls' => $controls,
            ],
            'recommendations' => $plan,
            'citations' => $citations,
            'guardrails' => [
                'workflow_scoped_output_only',
                'evidence_citations_required',
                'no_open_ended_chat_generation',
            ],
            'result' => $result,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function engineerBundle(int $organizationId, array $payload): array
    {
        $scanId = (int) ($payload['scan_id'] ?? 0);
        if ($scanId <= 0) {
            $latest = $this->db->fetchAssociative(
                'SELECT id
                 FROM scans
                 WHERE organization_id = :org_id
                   AND status = "completed"
                 ORDER BY id DESC
                 LIMIT 1',
                ['org_id' => $organizationId]
            );
            $scanId = (int) ($latest['id'] ?? 0);
        }

        if ($scanId <= 0) {
            throw new RuntimeException('No completed scans found for engineering copilot');
        }

        $scan = $this->scans->findById($organizationId, $scanId);
        $controls = is_array($scan['controls'] ?? null) ? $scan['controls'] : [];

        $options = [];
        $citations = [];
        foreach ($controls as $control) {
            if (!is_array($control)) {
                continue;
            }
            $level = (string) ($control['hierarchy_level'] ?? '');
            if (!in_array($level, ['elimination', 'substitution', 'engineering'], true)) {
                continue;
            }

            $options[] = [
                'option' => (string) ($control['title'] ?? ''),
                'hierarchy_level' => $level,
                'expected_risk_reduction_pct' => (float) ($control['expected_risk_reduction_pct'] ?? 0),
                'time_to_deploy_days' => (int) ($control['time_to_deploy_days'] ?? 0),
                'throughput_impact' => (string) ($control['throughput_impact'] ?? 'medium'),
            ];

            $citations[] = $this->citation(
                'scan_control_recommendation',
                (string) ($control['id'] ?? (($control['scan_id'] ?? '') . ':' . ($control['control_code'] ?? ''))),
                'expected_risk_reduction_pct',
                (float) ($control['expected_risk_reduction_pct'] ?? 0),
                'current_scan',
                0.92
            );
        }

        $result = [
            'title' => "Engineering redesign options (scan {$scanId})",
            'summary' => 'Use higher-order design controls first, then pair with interim administrative controls during rollout.',
            'options' => array_slice($options, 0, 5),
            'evidence' => [
                'scan_id' => $scanId,
                'risk_category' => $scan['risk_category'] ?? null,
                'model' => $scan['model'] ?? null,
            ],
        ];

        return [
            'facts' => [
                'scan_id' => $scanId,
                'risk_category' => $scan['risk_category'] ?? null,
                'model' => $scan['model'] ?? null,
                'options_count' => count($options),
            ],
            'recommendations' => $result['options'],
            'citations' => $citations,
            'guardrails' => [
                'workflow_scoped_output_only',
                'evidence_citations_required',
                'no_open_ended_chat_generation',
            ],
            'result' => $result,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function auditorBundle(int $organizationId, array $payload): array
    {
        $scanId = (int) ($payload['scan_id'] ?? 0);
        if ($scanId <= 0) {
            throw new RuntimeException('scan_id is required for auditor copilot');
        }

        $scan = $this->scans->findById($organizationId, $scanId);
        $baselineScanId = isset($payload['baseline_scan_id']) ? (int) $payload['baseline_scan_id'] : (int) ($scan['parent_scan_id'] ?? 0);

        $comparison = null;
        if ($baselineScanId > 0) {
            try {
                $comparison = $this->comparisons->compare($organizationId, $baselineScanId, $scanId);
            } catch (\Throwable) {
                $comparison = null;
            }
        }

        $evidenceChain = [
            'scan_id' => $scanId,
            'baseline_scan_id' => $baselineScanId > 0 ? $baselineScanId : null,
            'algorithm_version' => $scan['algorithm_version'] ?? null,
            'controls_count' => is_array($scan['controls'] ?? null) ? count($scan['controls']) : 0,
            'control_actions_count' => is_array($scan['control_actions'] ?? null) ? count($scan['control_actions']) : 0,
        ];

        $citations = [
            $this->citation('scan', (string) $scanId, 'algorithm_version', (string) ($scan['algorithm_version'] ?? 'unknown'), 'point_in_time', 0.99),
            $this->citation('scan', (string) $scanId, 'controls_count', (int) $evidenceChain['controls_count'], 'point_in_time', 0.95),
            $this->citation('scan', (string) $scanId, 'control_actions_count', (int) $evidenceChain['control_actions_count'], 'point_in_time', 0.95),
        ];

        if (is_array($comparison)) {
            $normDelta = $comparison['score_delta']['normalized'] ?? null;
            if ($normDelta !== null) {
                $citations[] = $this->citation('scan_comparison', "{$baselineScanId}:{$scanId}", 'normalized_score_delta', (float) $normDelta, 'comparison', 0.96);
            }
        }

        $recommendations = [
            ['priority' => 'high', 'action' => 'Retain evidence chain for this scan and linked control actions.'],
            ['priority' => 'medium', 'action' => 'Ensure follow-up verification scans use the same assessment model and algorithm version.'],
        ];

        $result = [
            'title' => "Audit evidence summary (scan {$scanId})",
            'summary' => $comparison !== null
                ? 'Score change explanation includes baseline comparison, node deltas, and improvement proof.'
                : 'No compatible baseline comparison found. Returning current scan evidence and control chain only.',
            'comparison' => $comparison,
            'evidence_chain' => $evidenceChain,
        ];

        return [
            'facts' => [
                'scan_id' => $scanId,
                'baseline_scan_id' => $baselineScanId > 0 ? $baselineScanId : null,
                'algorithm_version' => $scan['algorithm_version'] ?? null,
                'comparison_available' => $comparison !== null,
            ],
            'recommendations' => $recommendations,
            'citations' => $citations,
            'guardrails' => [
                'workflow_scoped_output_only',
                'evidence_citations_required',
                'no_open_ended_chat_generation',
            ],
            'result' => $result,
        ];
    }

    /**
     * @param string|int|float $value
     * @return array<string,mixed>
     */
    private function citation(
        string $sourceType,
        string $sourceId,
        string $metric,
        string|int|float $value,
        string $timeWindow,
        float $confidence
    ): array {
        return [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'metric' => $metric,
            'value' => $value,
            'time_window' => $timeWindow,
            'confidence' => round(max(0.0, min(1.0, $confidence)), 2),
        ];
    }

    private function normalizePersona(string $persona): string
    {
        $normalized = strtolower(trim(str_replace('-', '_', $persona)));
        if (!in_array($normalized, self::PERSONAS, true)) {
            throw new RuntimeException('persona must be one of: supervisor, safety_manager, engineer, auditor');
        }
        return $normalized;
    }
}

