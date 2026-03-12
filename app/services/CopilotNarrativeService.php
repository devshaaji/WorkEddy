<?php

declare(strict_types=1);

namespace WorkEddy\Services;

use RuntimeException;

final class CopilotNarrativeService
{
    /** @var callable(array<string,mixed>,int):array<string,mixed>|null */
    private $transport;

    /**
     * @param callable(array<string,mixed>,int):array<string,mixed>|null $transport
     */
    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    /**
     * @param array<string,mixed> $deterministicBundle
     * @return array<string,mixed>
     */
    public function generate(string $persona, array $deterministicBundle): array
    {
        $enabled = $this->envBool('COPILOT_LLM_ENABLED', true);
        $model = trim((string) (getenv('COPILOT_LLM_MODEL') ?: 'gpt-4.1-mini'));
        $timeoutMs = max(1000, (int) (getenv('COPILOT_LLM_TIMEOUT_MS') ?: 6000));

        if (!$enabled) {
            return [
                'narrative' => $this->fallbackNarrative($deterministicBundle),
                'llm' => [
                    'enabled' => false,
                    'model' => $model,
                    'status' => 'disabled',
                    'latency_ms' => 0,
                    'error_code' => null,
                ],
                'prompt_payload' => [],
                'raw_response' => null,
            ];
        }

        $apiKey = trim((string) (getenv('OPENAI_API_KEY') ?: ''));
        if ($apiKey === '') {
            return [
                'narrative' => $this->fallbackNarrative($deterministicBundle),
                'llm' => [
                    'enabled' => true,
                    'model' => $model,
                    'status' => 'fallback',
                    'latency_ms' => 0,
                    'error_code' => 'missing_api_key',
                ],
                'prompt_payload' => [],
                'raw_response' => null,
            ];
        }

        $promptPayload = $this->buildPromptPayload($persona, $deterministicBundle, $model);

        $start = microtime(true);
        try {
            $raw = $this->sendRequest($promptPayload, $timeoutMs);
            $latency = (int) round((microtime(true) - $start) * 1000);

            $content = $this->extractMessageContent($raw);
            $parsed = $this->parseNarrative($content);

            return [
                'narrative' => $parsed,
                'llm' => [
                    'enabled' => true,
                    'model' => $model,
                    'status' => 'success',
                    'latency_ms' => $latency,
                    'error_code' => null,
                ],
                'prompt_payload' => $promptPayload,
                'raw_response' => $raw,
            ];
        } catch (RuntimeException $e) {
            $latency = (int) round((microtime(true) - $start) * 1000);
            return [
                'narrative' => $this->fallbackNarrative($deterministicBundle),
                'llm' => [
                    'enabled' => true,
                    'model' => $model,
                    'status' => 'fallback',
                    'latency_ms' => $latency,
                    'error_code' => $e->getMessage(),
                ],
                'prompt_payload' => $promptPayload,
                'raw_response' => null,
            ];
        }
    }

    /**
     * @param array<string,mixed> $deterministicBundle
     * @return array<string,mixed>
     */
    private function buildPromptPayload(string $persona, array $deterministicBundle, string $model): array
    {
        $system = implode("\n", [
            'You are WorkEddy Copilot.',
            'Use only the provided deterministic evidence bundle.',
            'Do not add facts not present in the bundle.',
            'Return valid JSON object only with keys:',
            'executive_summary, why_this_matters, recommended_actions_text',
            'Each value must be plain English text.',
            'No markdown, no code fences.',
        ]);

        $personaGuide = match ($persona) {
            'supervisor' => 'Audience: shift supervisor. Focus on immediate shift actions and task-level execution.',
            'safety_manager' => 'Audience: safety manager. Focus on corrective action rigor, feasibility, and verification closure.',
            'engineer' => 'Audience: engineer. Focus on design trade-offs, deployment sequence, and throughput implications.',
            'auditor' => 'Audience: auditor. Focus on traceability, evidence sufficiency, and score-change explainability.',
            default => 'Audience: operations stakeholder.',
        };

        $user = [
            'persona' => $persona,
            'instruction' => $personaGuide,
            'deterministic_bundle' => $deterministicBundle,
        ];

        return [
            'model' => $model,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function sendRequest(array $payload, int $timeoutMs): array
    {
        if (is_callable($this->transport)) {
            $response = call_user_func($this->transport, $payload, $timeoutMs);
            if (!is_array($response)) {
                throw new RuntimeException('transport_invalid');
            }
            return $response;
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('curl_unavailable');
        }

        $apiKey = trim((string) (getenv('OPENAI_API_KEY') ?: ''));
        $baseUrl = rtrim((string) (getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
        $url = $baseUrl . '/chat/completions';

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init_failed');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT_MS => $timeoutMs,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException('timeout_or_network');
        }

        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException('empty_llm_response');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('decode_error');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('http_' . $httpCode);
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $response
     */
    private function extractMessageContent(array $response): string
    {
        $choices = $response['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            throw new RuntimeException('invalid_llm_schema');
        }

        $message = $choices[0]['message'] ?? null;
        if (!is_array($message)) {
            throw new RuntimeException('invalid_llm_schema');
        }

        $content = $message['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException('invalid_llm_schema');
        }

        return trim($content);
    }

    /**
     * @return array{executive_summary:string,why_this_matters:string,recommended_actions_text:string}
     */
    private function parseNarrative(string $content): array
    {
        $normalized = trim($content);
        if (str_starts_with($normalized, '```')) {
            $normalized = preg_replace('/^```(?:json)?\s*/', '', $normalized) ?? $normalized;
            $normalized = preg_replace('/\s*```$/', '', $normalized) ?? $normalized;
        }

        $decoded = json_decode($normalized, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('decode_error');
        }

        $required = ['executive_summary', 'why_this_matters', 'recommended_actions_text'];
        foreach ($required as $key) {
            if (!isset($decoded[$key]) || !is_string($decoded[$key]) || trim($decoded[$key]) === '') {
                throw new RuntimeException('invalid_llm_schema');
            }
        }

        return [
            'executive_summary' => trim((string) $decoded['executive_summary']),
            'why_this_matters' => trim((string) $decoded['why_this_matters']),
            'recommended_actions_text' => trim((string) $decoded['recommended_actions_text']),
        ];
    }

    /**
     * @param array<string,mixed> $deterministicBundle
     * @return array<string,string>
     */
    private function fallbackNarrative(array $deterministicBundle): array
    {
        $result = is_array($deterministicBundle['result'] ?? null) ? $deterministicBundle['result'] : [];
        $title = (string) ($result['title'] ?? 'Copilot output');
        $summaryRaw = $result['summary'] ?? '';

        $summary = '';
        if (is_array($summaryRaw)) {
            $parts = [];
            foreach ($summaryRaw as $k => $v) {
                if (is_scalar($v)) {
                    $parts[] = "{$k}: {$v}";
                }
            }
            $summary = implode('; ', $parts);
        } else {
            $summary = (string) $summaryRaw;
        }

        $recs = is_array($deterministicBundle['recommendations'] ?? null) ? $deterministicBundle['recommendations'] : [];
        $actions = [];
        foreach (array_slice($recs, 0, 3) as $row) {
            if (is_array($row)) {
                $text = trim((string) ($row['action'] ?? $row['control_title'] ?? $row['option'] ?? ''));
                if ($text !== '') {
                    $actions[] = $text;
                }
            }
        }

        return [
            'executive_summary' => trim($title . '. ' . $summary),
            'why_this_matters' => 'Narrative generation is unavailable, so deterministic evidence is returned directly to preserve decision integrity.',
            'recommended_actions_text' => $actions !== [] ? implode(' | ', $actions) : 'Follow deterministic recommendations in the result payload.',
        ];
    }

    private function envBool(string $key, bool $default): bool
    {
        $raw = getenv($key);
        if ($raw === false) {
            return $default;
        }

        $normalized = strtolower(trim((string) $raw));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

