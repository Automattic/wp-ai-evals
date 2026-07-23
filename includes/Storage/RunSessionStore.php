<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Storage;

use Automattic\AiEvals\CaseResult;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\RunReport;
use Automattic\AiEvals\RunConfiguration;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;

final class RunSessionStore
{
    private const TRANSIENT_PREFIX = 'wp_ai_evals_session_';
    private int $ttl;

    public function __construct(int $ttl = 3600)
    {
        if (function_exists('apply_filters')) {
            $ttl = (int) apply_filters('wp_ai_evals_run_session_ttl', $ttl);
        }

        $this->ttl = max(60, $ttl);
    }

    /** @return array<string, mixed> */
    public function start(
        Registry $registry,
        Selection $selection,
        Runner $runner,
        ?RunConfiguration $configuration = null
    ): array {
        $this->assertAvailable();
        $configuration = $configuration ?? new RunConfiguration();
        $queue = [];

        foreach ($registry->all() as $suite) {
            foreach ($suite->getCases() as $case) {
                if (!$selection->matches($suite, $case)) {
                    continue;
                }

                foreach ($runner->targetsForCase($case, $configuration) as $modelTarget) {
                    for ($iteration = 1; $iteration <= $selection->getRepetitions(); ++$iteration) {
                        $queue[] = [
                            'suite' => $suite->getId(),
                            'case' => $case->getId(),
                            'iteration' => $iteration,
                            'model_target' => null !== $modelTarget ? $modelTarget->jsonSerialize() : null,
                        ];
                    }
                }
            }
        }

        $session = [
            'id' => $runner->makeRunId(),
            'started_at' => gmdate('c'),
            'started_microtime' => microtime(true),
            'total' => count($queue),
            'queue' => $queue,
            'results' => [],
            'configuration' => $configuration->jsonSerialize(),
        ];

        $this->write($session);

        return $this->normalize($session, false);
    }

    /** @return array<string, mixed>|null */
    public function status(string $runId): ?array
    {
        $session = $this->read($runId);

        return null === $session ? null : $this->normalize($session, false);
    }

    /**
     * Advances a live run by one case variant.
     *
     * This performs an unlocked read-modify-write on the session transient. The
     * Admin app awaits each `/next` request before issuing the following one, so
     * calls are serialized in practice. Concurrent advances of the same run (for
     * example, the same session driven from two browser tabs) can race and
     * double-process a queue item; callers that cannot guarantee serialization
     * should add their own locking.
     *
     * @return array{session: array<string, mixed>, report: RunReport|null}
     */
    public function advance(string $runId, Registry $registry, Runner $runner): array
    {
        $session = $this->read($runId);
        if (null === $session) {
            throw new RuntimeException(sprintf('Unknown or expired evaluation run session "%s".', $runId));
        }

        if ([] !== $session['queue']) {
            $next = array_shift($session['queue']);
            $suite = $registry->get((string) $next['suite']);
            $cases = $suite->getCases();
            $caseId = (string) $next['case'];
            if (!isset($cases[$caseId])) {
                throw new RuntimeException(sprintf('Unknown evaluation case "%s/%s".', $suite->getId(), $caseId));
            }

            $configuration = $this->configuration($session);
            $modelTarget = isset($next['model_target']) && is_array($next['model_target'])
                ? \Automattic\AiEvals\ModelTarget::fromArray($next['model_target'])
                : null;
            $session['results'][] = $runner->runCase(
                $suite,
                $cases[$caseId],
                (int) $next['iteration'],
                $modelTarget,
                $configuration
            );
        }

        if ([] !== $session['queue']) {
            $this->write($session);

            return [
                'session' => $this->normalize($session, false),
                'report' => null,
            ];
        }

        $report = $this->report($session);
        (new HistoryStore())->save($report);
        delete_transient($this->key($runId));

        return [
            'session' => $this->normalize($session, true),
            'report' => $report,
        ];
    }

    /** @param array<string, mixed> $session */
    private function write(array $session): void
    {
        set_transient($this->key((string) $session['id']), $session, $this->ttl);
    }

    /** @return array<string, mixed>|null */
    private function read(string $runId): ?array
    {
        $this->assertAvailable();
        if (1 !== preg_match('/^[a-zA-Z0-9._-]+$/', $runId)) {
            return null;
        }

        $session = get_transient($this->key($runId));

        return is_array($session) ? $session : null;
    }

    /** @param array<string, mixed> $session @return array<string, mixed> */
    private function normalize(array $session, bool $complete): array
    {
        $report = $this->report($session);

        return [
            'id' => $session['id'],
            'started_at' => $session['started_at'],
            'total' => $session['total'],
            'completed' => count($session['results']),
            'remaining' => count($session['queue']),
            'complete' => $complete,
            'duration_ms' => $report->getDurationMilliseconds(),
            'configuration' => $report->getConfiguration()->jsonSerialize(),
            'summary' => $report->jsonSerialize()['summary'],
            'variants' => $report->getVariants(),
            'results' => array_map(
                static fn(CaseResult $result): array => $result->jsonSerialize(),
                $session['results']
            ),
        ];
    }

    /** @param array<string, mixed> $session */
    private function report(array $session): RunReport
    {
        return new RunReport(
            (string) $session['id'],
            (string) $session['started_at'],
            (microtime(true) - (float) $session['started_microtime']) * 1000,
            $session['results'],
            $this->configuration($session)
        );
    }

    /** @param array<string, mixed> $session */
    private function configuration(array $session): RunConfiguration
    {
        return isset($session['configuration']) && is_array($session['configuration'])
            ? RunConfiguration::fromArray($session['configuration'])
            : new RunConfiguration();
    }

    private function key(string $runId): string
    {
        return self::TRANSIENT_PREFIX . $runId;
    }

    private function assertAvailable(): void
    {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            throw new RuntimeException('WordPress transient storage is unavailable for live evaluation runs.');
        }
    }
}
