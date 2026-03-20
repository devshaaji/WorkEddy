<?php

declare(strict_types=1);

namespace WorkEddy\Repositories;

use Doctrine\DBAL\Connection;

final class ControlRecommendationRepository
{
    public function __construct(private readonly Connection $db) {}

    /**
     * @param list<array{
     *   rank_order:int,
     *   hierarchy_level:string,
     *   control_code:string,
     *   title:string,
     *   expected_risk_reduction_pct:float,
     *   implementation_cost:string,
     *   time_to_deploy_days:int,
     *   throughput_impact:string,
     *   control_type?:string,
     *   feasibility_score?:float,
     *   feasibility_status?:string,
     *   interim_for_control_code?:string|null,
     *   rationale:string,
     *   evidence:array
     * }> $controls
     */
    public function replaceForScan(int $scanId, array $controls): void
    {
        $this->db->transactional(function () use ($scanId, $controls): void {
            $this->db->executeStatement('DELETE FROM scan_control_recommendations WHERE scan_id = :scan_id', [
                'scan_id' => $scanId,
            ]);

            foreach ($controls as $control) {
                $this->db->executeStatement(
                    'INSERT INTO scan_control_recommendations (
                        scan_id, rank_order, hierarchy_level, control_code, title,
                        expected_risk_reduction_pct, implementation_cost, time_to_deploy_days,
                        throughput_impact, control_type, feasibility_score, feasibility_status,
                        interim_for_control_code, rationale, evidence_json, recommendation_engine_version, created_at
                    ) VALUES (
                        :scan_id, :rank_order, :hierarchy_level, :control_code, :title,
                        :expected_risk_reduction_pct, :implementation_cost, :time_to_deploy_days,
                        :throughput_impact, :control_type, :feasibility_score, :feasibility_status,
                        :interim_for_control_code, :rationale, :evidence_json, :recommendation_engine_version, NOW()
                    )',
                    [
                        'scan_id' => $scanId,
                        'rank_order' => (int) $control['rank_order'],
                        'hierarchy_level' => (string) $control['hierarchy_level'],
                        'control_code' => (string) $control['control_code'],
                        'title' => (string) $control['title'],
                        'expected_risk_reduction_pct' => (float) $control['expected_risk_reduction_pct'],
                        'implementation_cost' => (string) $control['implementation_cost'],
                        'time_to_deploy_days' => (int) $control['time_to_deploy_days'],
                        'throughput_impact' => (string) $control['throughput_impact'],
                        'control_type' => in_array((string) ($control['control_type'] ?? 'permanent'), ['permanent', 'interim'], true)
                            ? (string) ($control['control_type'] ?? 'permanent')
                            : 'permanent',
                        'feasibility_score' => round((float) ($control['feasibility_score'] ?? 0.0), 2),
                        'feasibility_status' => in_array((string) ($control['feasibility_status'] ?? 'conditional'), ['feasible', 'conditional', 'not_feasible'], true)
                            ? (string) ($control['feasibility_status'] ?? 'conditional')
                            : 'conditional',
                        'interim_for_control_code' => isset($control['interim_for_control_code']) && $control['interim_for_control_code'] !== ''
                            ? (string) $control['interim_for_control_code']
                            : null,
                        'rationale' => (string) $control['rationale'],
                        'evidence_json' => json_encode($control['evidence'], JSON_UNESCAPED_UNICODE),
                        'recommendation_engine_version' => (string) ($control['recommendation_engine_version'] ?? 'ctrl_rec_v1'),
                    ]
                );
            }
        });
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listByScan(int $scanId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT id, scan_id, rank_order, hierarchy_level, control_code, title,
                    expected_risk_reduction_pct, implementation_cost, time_to_deploy_days,
                    throughput_impact, control_type, feasibility_score, feasibility_status,
                    interim_for_control_code, rationale, evidence_json, recommendation_engine_version, created_at
             FROM scan_control_recommendations
             WHERE scan_id = :scan_id
             ORDER BY rank_order ASC, id ASC',
            ['scan_id' => $scanId]
        );

        foreach ($rows as &$row) {
            $decoded = [];
            if (isset($row['evidence_json']) && is_string($row['evidence_json']) && $row['evidence_json'] !== '') {
                $decoded = json_decode($row['evidence_json'], true) ?: [];
            }
            $row['evidence'] = $decoded;
            unset($row['evidence_json']);
        }
        unset($row);

        return $rows;
    }
}
