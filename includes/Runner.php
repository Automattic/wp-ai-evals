<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Throwable;
use Automattic\AiEvals\Task\ModelTargetAwareTaskInterface;

final class Runner
{
    public function run(
        Registry $registry,
        ?Selection $selection = null,
        ?RunConfiguration $configuration = null
    ): RunReport
    {
        $selection = $selection ?? Selection::all();
        $configuration = $configuration ?? new RunConfiguration();
        $startedAt = gmdate('c');
        $started = microtime(true);
        $results = [];

        $this->action('wp_ai_evals_before_run', $registry, $selection, $configuration);

        foreach ($registry->all() as $suite) {
            foreach ($suite->getCases() as $case) {
                if (!$selection->matches($suite, $case)) {
                    continue;
                }

                foreach ($this->targetsForCase($case, $configuration) as $modelTarget) {
                    for ($iteration = 1; $iteration <= $selection->getRepetitions(); ++$iteration) {
                        $results[] = $this->runCase(
                            $suite,
                            $case,
                            $iteration,
                            $modelTarget,
                            $configuration
                        );
                    }
                }
            }
        }

        $report = new RunReport(
            $this->makeRunId(),
            $startedAt,
            (microtime(true) - $started) * 1000,
            $results,
            $configuration
        );

        $this->action('wp_ai_evals_after_run', $report);

        return $report;
    }

    public function runCase(
        Suite $suite,
        EvaluationCase $case,
        int $iteration = 1,
        ?ModelTarget $modelTarget = null,
        ?RunConfiguration $configuration = null
    ): CaseResult {
        $configuration = $configuration ?? new RunConfiguration(
            null !== $modelTarget ? [$modelTarget] : []
        );
        $context = new EvaluationContext($suite, $case, $iteration, $modelTarget, $configuration);
        $started = microtime(true);
        $taskType = 'unconfigured';

        $this->action('wp_ai_evals_before_case', $context);

        try {
            $task = $case->getTask();
            $taskType = $task->getType();
            $result = $task->run($case->getInput(), $context);
            if (null !== $modelTarget && $task instanceof ModelTargetAwareTaskInterface) {
                if (!$modelTarget->matchesMetadata($result->getMetadata())) {
                    $actualProvider = isset($result->getMetadata()['provider'])
                        ? (string) $result->getMetadata()['provider']
                        : 'unknown';
                    $actualModel = isset($result->getMetadata()['model'])
                        ? (string) $result->getMetadata()['model']
                        : 'unknown';
                    throw new Exception\RuntimeException(sprintf(
                        'Model-aware task did not use exact target "%s"; resolved "%s:%s".',
                        $modelTarget->getId(),
                        $actualProvider,
                        $actualModel
                    ));
                }

                $result = $result->withMetadata([
                    'requested_model_target' => $modelTarget->getId(),
                    'model_target_match' => true,
                ]);
            }
            $taskDuration = (microtime(true) - $started) * 1000;
            $result = $result->withMetric('duration_ms', $taskDuration);
            $evaluatorResults = [];

            if ([] === $case->getEvaluators()) {
                throw new Exception\RuntimeException(
                    sprintf('Eval case "%s" has no evaluators.', $context->getQualifiedCaseId())
                );
            }

            foreach ($case->getEvaluators() as $evaluator) {
                $evaluatorStarted = microtime(true);
                try {
                    $evaluation = $evaluator->evaluate($result, $case->getExpected(), $context);
                } catch (Throwable $error) {
                    $evaluation = EvaluatorResult::fail(
                        $evaluator->getName(),
                        $evaluator->getType(),
                        $error->getMessage()
                    );
                }
                $evaluatorResults[] = $evaluation->withMetric(
                    'duration_ms',
                    (microtime(true) - $evaluatorStarted) * 1000
                );
            }

            $passed = !in_array(false, array_map(
                static fn(EvaluatorResult $evaluation): bool => $evaluation->hasPassed(),
                $evaluatorResults
            ), true);
            $score = array_sum(array_map(
                static fn(EvaluatorResult $evaluation): float => $evaluation->getScore(),
                $evaluatorResults
            )) / count($evaluatorResults);

            $caseResult = new CaseResult(
                $suite->getId(),
                $case->getId(),
                $case->getLabel(),
                $taskType,
                $iteration,
                $passed ? 'passed' : 'failed',
                $score,
                (microtime(true) - $started) * 1000,
                $result,
                $evaluatorResults,
                '',
                $case->getInput(),
                $case->getExpected(),
                $case->getTags(),
                $case->getMetadata(),
                $modelTarget
            );
        } catch (Throwable $error) {
            $caseResult = new CaseResult(
                $suite->getId(),
                $case->getId(),
                $case->getLabel(),
                $taskType,
                $iteration,
                'error',
                0.0,
                (microtime(true) - $started) * 1000,
                null,
                [],
                $error->getMessage(),
                $case->getInput(),
                $case->getExpected(),
                $case->getTags(),
                $case->getMetadata(),
                $modelTarget
            );
        }

        $this->action('wp_ai_evals_after_case', $caseResult, $context);

        return $caseResult;
    }

    /** @param mixed ...$arguments */
    private function action(string $name, ...$arguments): void
    {
        if (function_exists('do_action')) {
            do_action($name, ...$arguments);
        }
    }

    public function makeRunId(): string
    {
        try {
            return gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        } catch (Throwable $error) {
            return uniqid(gmdate('Ymd-His') . '-', true);
        }
    }

    /**
     * Model-independent tasks execute once instead of being duplicated in every model variant.
     *
     * @return list<ModelTarget|null>
     */
    public function targetsForCase(EvaluationCase $case, RunConfiguration $configuration): array
    {
        try {
            if ($case->getTask() instanceof ModelTargetAwareTaskInterface) {
                return $configuration->getExecutionTargets();
            }
        } catch (Throwable $error) {
            // Invalid case configuration is captured as a normal case error during execution.
        }

        return [null];
    }
}
