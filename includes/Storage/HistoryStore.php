<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Storage;

use Automattic\AiEvals\CaseResult;
use Automattic\AiEvals\RunReport;

final class HistoryStore
{
    private const OPTION = 'wp_ai_evals_history';
    private const REPORT_OPTION_PREFIX = 'wp_ai_evals_run_';
    private int $limit;

    public function __construct(int $limit = 20)
    {
        if (function_exists('apply_filters')) {
            $limit = (int) apply_filters('wp_ai_evals_history_limit', $limit);
        }

        $this->limit = max(1, $limit);
    }

    public function save(RunReport $report): void
    {
        if (!function_exists('get_option') || !function_exists('update_option')) {
            return;
        }

        $previousHistory = $this->all();
        $history = $previousHistory;
        array_unshift($history, $this->summarize($report));
        $history = array_slice($history, 0, $this->limit);

        $normalized = json_decode($this->json($report), true);
        if (is_array($normalized) && function_exists('apply_filters')) {
            $filtered = apply_filters('wp_ai_evals_report_before_store', $normalized, $report);
            $normalized = is_array($filtered) ? $filtered : $normalized;
        }
        if (is_array($normalized)) {
            $this->writeOption($this->reportOption($report->getId()), $normalized);
        }

        if (false === get_option(self::OPTION, false) && function_exists('add_option')) {
            add_option(self::OPTION, $history, '', false);
        } else {
            update_option(self::OPTION, $history, false);
        }

        $retained = array_column($history, 'id');
        foreach ($previousHistory as $previous) {
            $id = isset($previous['id']) ? (string) $previous['id'] : '';
            if ('' !== $id && !in_array($id, $retained, true) && function_exists('delete_option')) {
                delete_option($this->reportOption($id));
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        if (!function_exists('get_option')) {
            return [];
        }

        $history = get_option(self::OPTION, []);

        return is_array($history) ? array_values($history) : [];
    }

    /** @return array<string, mixed>|null */
    public function find(string $runId): ?array
    {
        if (!function_exists('get_option') || 1 !== preg_match('/^[a-zA-Z0-9._-]+$/', $runId)) {
            return null;
        }

        $report = get_option($this->reportOption($runId), null);

        return is_array($report) ? $report : null;
    }

    /** @return array<string, mixed> */
    private function summarize(RunReport $report): array
    {
        return [
            'id' => $report->getId(),
            'started_at' => $report->getStartedAt(),
            'duration_ms' => $report->getDurationMilliseconds(),
            'total' => $report->getTotal(),
            'passed' => $report->getPassed(),
            'failed' => $report->getFailed(),
            'score' => $report->getScore(),
            'diagnostics' => $report->getDiagnostics(),
            'results' => array_map(
                static fn(CaseResult $result): array => [
                    'qualified_id' => $result->getQualifiedId(),
                    'iteration' => $result->getIteration(),
                    'status' => $result->getStatus(),
                    'score' => $result->getScore(),
                    'duration_ms' => $result->getDurationMilliseconds(),
                    'error' => $result->getError(),
                ],
                $report->getResults()
            ),
        ];
    }

    /** @param array<string, mixed> $value */
    private function writeOption(string $name, array $value): void
    {
        if (false === get_option($name, false) && function_exists('add_option')) {
            add_option($name, $value, '', false);
            return;
        }

        update_option($name, $value, false);
    }

    private function reportOption(string $runId): string
    {
        return self::REPORT_OPTION_PREFIX . $runId;
    }

    /** @param mixed $value */
    private function json($value): string
    {
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return false === $json ? '{}' : $json;
    }
}
