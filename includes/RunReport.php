<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class RunReport implements JsonSerializable
{
    private string $id;
    private string $startedAt;
    private float $durationMilliseconds;

    /** @var list<CaseResult> */
    private array $results;
    private RunConfiguration $configuration;

    /** @param list<CaseResult> $results */
    public function __construct(
        string $id,
        string $startedAt,
        float $durationMilliseconds,
        array $results,
        ?RunConfiguration $configuration = null
    ) {
        $this->id = $id;
        $this->startedAt = $startedAt;
        $this->durationMilliseconds = $durationMilliseconds;
        $this->results = $results;
        $this->configuration = $configuration ?? new RunConfiguration();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStartedAt(): string
    {
        return $this->startedAt;
    }

    public function getDurationMilliseconds(): float
    {
        return $this->durationMilliseconds;
    }

    /** @return list<CaseResult> */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getTotal(): int
    {
        return count($this->results);
    }

    public function getPassed(): int
    {
        return count(array_filter($this->results, static fn(CaseResult $result): bool => $result->hasPassed()));
    }

    public function getFailed(): int
    {
        return $this->getTotal() - $this->getPassed();
    }

    public function getScore(): float
    {
        if ([] === $this->results) {
            return 0.0;
        }

        return array_sum(array_map(
            static fn(CaseResult $result): float => $result->getScore(),
            $this->results
        )) / count($this->results);
    }

    public function hasPassed(): bool
    {
        return $this->getTotal() > 0 && 0 === $this->getFailed();
    }

    public function getConfiguration(): RunConfiguration
    {
        return $this->configuration;
    }

    /** @return array<string, mixed> */
    public function getDiagnostics(): array
    {
        return $this->diagnosticsFor($this->results);
    }

    /** @return list<array<string, mixed>> */
    public function getVariants(): array
    {
        $groups = [];
        foreach ($this->results as $result) {
            $target = $result->getModelTarget();
            if (null === $target && [] !== $this->configuration->getModelTargets()) {
                continue;
            }
            $id = null !== $target ? $target->getId() : 'default';
            if (!isset($groups[$id])) {
                $groups[$id] = [
                    'id' => $id,
                    'model_target' => $target,
                    'results' => [],
                ];
            }
            $groups[$id]['results'][] = $result;
        }

        $variants = [];
        foreach ($groups as $group) {
            /** @var list<CaseResult> $results */
            $results = $group['results'];
            $total = count($results);
            $passed = count(array_filter(
                $results,
                static fn(CaseResult $result): bool => $result->hasPassed()
            ));
            $score = 0 === $total ? 0.0 : array_sum(array_map(
                static fn(CaseResult $result): float => $result->getScore(),
                $results
            )) / $total;

            $variants[] = [
                'id' => $group['id'],
                'model_target' => $group['model_target'],
                'total' => $total,
                'passed' => $passed,
                'failed' => $total - $passed,
                'score' => $score,
                'duration_ms' => array_sum(array_map(
                    static fn(CaseResult $result): float => $result->getDurationMilliseconds(),
                    $results
                )),
                'diagnostics' => $this->diagnosticsFor($results),
            ];
        }

        return $variants;
    }

    /**
     * @param list<CaseResult> $results
     * @return array<string, mixed>
     */
    private function diagnosticsFor(array $results): array
    {
        $tokens = ['input' => 0, 'output' => 0, 'total' => 0, 'thinking' => 0];
        $taskTokens = ['input' => 0, 'output' => 0, 'total' => 0, 'thinking' => 0];
        $evaluatorTokens = ['input' => 0, 'output' => 0, 'total' => 0, 'thinking' => 0];
        $costs = [];
        $taskCosts = [];
        $evaluatorCosts = [];
        $costObservations = ['total' => 0, 'task' => 0, 'evaluator' => 0];
        $tools = [];
        $providers = [];
        $models = [];

        foreach ($results as $result) {
            $metadataSets = [];
            if (null !== $result->getTaskResult()) {
                $metadataSets[] = [$result->getTaskResult()->getMetadata(), 'task'];
            }
            foreach ($result->getEvaluatorResults() as $evaluatorResult) {
                $metadataSets[] = [$evaluatorResult->getMetadata(), 'evaluator'];
            }

            foreach ($metadataSets as $metadataSet) {
                $metadata = $metadataSet[0];
                $kind = $metadataSet[1];
                foreach ($tokens as $name => $value) {
                    if (isset($metadata['tokens'][$name]) && is_numeric($metadata['tokens'][$name])) {
                        $tokenCount = (int) $metadata['tokens'][$name];
                        $tokens[$name] += $tokenCount;
                        if ('task' === $kind) {
                            $taskTokens[$name] += $tokenCount;
                        } else {
                            $evaluatorTokens[$name] += $tokenCount;
                        }
                    }
                }
                $cost = ReportedCost::from($metadata['cost'] ?? null);
                if (null !== $cost) {
                    $currency = $cost->getCurrency();
                    $amount = $cost->getAmount();
                    $costs[$currency] = ($costs[$currency] ?? 0.0) + $amount;
                    ++$costObservations['total'];
                    if ('task' === $kind) {
                        $taskCosts[$currency] = ($taskCosts[$currency] ?? 0.0) + $amount;
                        ++$costObservations['task'];
                    } else {
                        $evaluatorCosts[$currency] = ($evaluatorCosts[$currency] ?? 0.0) + $amount;
                        ++$costObservations['evaluator'];
                    }
                }
                $metadataTools = isset($metadata['tools']) && is_array($metadata['tools'])
                    ? $metadata['tools']
                    : [];
                foreach ($metadataTools as $tool) {
                    if (is_string($tool) && '' !== $tool) {
                        $tools[$tool] = true;
                    }
                }
                if (isset($metadata['provider']) && is_string($metadata['provider'])) {
                    $providers[$metadata['provider']] = true;
                }
                if (isset($metadata['model']) && is_string($metadata['model'])) {
                    $models[$metadata['model']] = true;
                }
            }
        }

        return [
            'tokens' => $tokens,
            'task_tokens' => $taskTokens,
            'evaluator_tokens' => $evaluatorTokens,
            'costs' => $costs,
            'task_costs' => $taskCosts,
            'evaluator_costs' => $evaluatorCosts,
            'cost_observations' => $costObservations,
            'tools' => array_keys($tools),
            'providers' => array_keys($providers),
            'models' => array_keys($models),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'started_at' => $this->startedAt,
            'duration_ms' => $this->durationMilliseconds,
            'configuration' => $this->configuration,
            'summary' => [
                'total' => $this->getTotal(),
                'passed' => $this->getPassed(),
                'failed' => $this->getFailed(),
                'score' => $this->getScore(),
                'diagnostics' => $this->getDiagnostics(),
            ],
            'variants' => $this->getVariants(),
            'results' => $this->results,
        ];
    }
}
