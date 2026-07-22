<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Cli;

use Automattic\AiEvals\Kernel;
use Automattic\AiEvals\Report\JunitFormatter;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Storage\HistoryStore;

/** Commands for discovering and running WordPress AI evaluations. */
final class Command
{
    /**
     * Lists registered suites and cases.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : table or json. Default: table.
     *
     * @subcommand list
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function list_(array $args, array $assocArgs): void
    {
        Kernel::instance()->initialize();
        $items = [];

        foreach (Kernel::instance()->getRegistry()->all() as $suite) {
            foreach ($suite->getCases() as $case) {
                $items[] = [
                    'suite' => $suite->getId(),
                    'case' => $case->getId(),
                    'label' => $case->getLabel(),
                    'type' => $case->getTask()->getType(),
                    'tags' => implode(',', $case->getTags()),
                    'evaluators' => count($case->getEvaluators()),
                ];
            }
        }

        $format = isset($assocArgs['format']) ? (string) $assocArgs['format'] : 'table';
        if ('json' === $format) {
            \WP_CLI::line($this->json($items));
            return;
        }

        \WP_CLI\Utils\format_items('table', $items, ['suite', 'case', 'label', 'type', 'tags', 'evaluators']);
    }

    /**
     * Runs registered evaluation cases.
     *
     * ## OPTIONS
     *
     * [<suite>...]
     * : Suite IDs to run.
     *
     * [--suite=<suites>]
     * : Comma-separated suite IDs.
     *
     * [--case=<cases>]
     * : Comma-separated case IDs or suite/case IDs.
     *
     * [--tag=<tags>]
     * : Comma-separated tags. A case matching any tag is selected.
     *
     * [--repeat=<count>]
     * : Number of times to run each selected case. Default: 1.
     *
     * [--format=<format>]
     * : table, json, or junit. Default: table.
     *
     * [--fail-under=<score>]
     * : Fail if aggregate score is below this 0..1 value. By default, only failed or errored cases produce a non-zero exit.
     *
     * [--no-store]
     * : Do not add the run to WP Admin history.
     *
     * @param list<string> $args
     * @param array<string, mixed> $assocArgs
     */
    public function run(array $args, array $assocArgs): void
    {
        Kernel::instance()->initialize();
        $suites = array_merge($args, $this->split($assocArgs['suite'] ?? ''));
        $cases = $this->split($assocArgs['case'] ?? '');
        $tags = $this->split($assocArgs['tag'] ?? '');
        $repeat = isset($assocArgs['repeat']) ? (int) $assocArgs['repeat'] : 1;

        $report = (new Runner())->run(
            Kernel::instance()->getRegistry(),
            new Selection($suites, $cases, $tags, $repeat)
        );

        if (!isset($assocArgs['no-store'])) {
            (new HistoryStore())->save($report);
        }

        $format = isset($assocArgs['format']) ? (string) $assocArgs['format'] : 'table';
        if ('json' === $format) {
            \WP_CLI::line($this->json($report));
        } elseif ('junit' === $format) {
            \WP_CLI::line((new JunitFormatter())->format($report));
        } else {
            $items = [];
            foreach ($report->getResults() as $result) {
                $items[] = [
                    'case' => $result->getQualifiedId(),
                    'iteration' => $result->getIteration(),
                    'status' => $result->getStatus(),
                    'score' => number_format($result->getScore(), 3),
                    'duration_ms' => number_format($result->getDurationMilliseconds(), 1),
                    'error' => $result->getError(),
                ];
            }
            \WP_CLI\Utils\format_items('table', $items, ['case', 'iteration', 'status', 'score', 'duration_ms', 'error']);
            \WP_CLI::log(sprintf(
                'Run %s: %d/%d passed; aggregate score %.3f.',
                $report->getId(),
                $report->getPassed(),
                $report->getTotal(),
                $report->getScore()
            ));
        }

        $failUnder = isset($assocArgs['fail-under']) ? (float) $assocArgs['fail-under'] : 0.0;
        if ($report->getFailed() > 0 || $report->getScore() < $failUnder) {
            \WP_CLI::halt(1);
        }
    }

    /** @param mixed $value @return list<string> */
    private function split($value): array
    {
        if (!is_string($value) || '' === trim($value)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /** @param mixed $value */
    private function json($value): string
    {
        $json = function_exists('wp_json_encode')
            ? wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return false === $json ? '{}' : $json;
    }
}
