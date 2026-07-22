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

    /** @param list<CaseResult> $results */
    public function __construct(string $id, string $startedAt, float $durationMilliseconds, array $results)
    {
        $this->id = $id;
        $this->startedAt = $startedAt;
        $this->durationMilliseconds = $durationMilliseconds;
        $this->results = $results;
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

    /** @return array<string, mixed> */
    public function getDiagnostics(): array
    {
        $tokens = ['input' => 0, 'output' => 0, 'total' => 0, 'thinking' => 0];
        $tools = [];
        $providers = [];
        $models = [];

        foreach ($this->results as $result) {
            $metadataSets = [];
            if (null !== $result->getTaskResult()) {
                $metadataSets[] = $result->getTaskResult()->getMetadata();
            }
            foreach ($result->getEvaluatorResults() as $evaluatorResult) {
                $metadataSets[] = $evaluatorResult->getMetadata();
            }

            foreach ($metadataSets as $metadata) {
                foreach ($tokens as $name => $value) {
                    if (isset($metadata['tokens'][$name]) && is_numeric($metadata['tokens'][$name])) {
                        $tokens[$name] += (int) $metadata['tokens'][$name];
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
            'summary' => [
                'total' => $this->getTotal(),
                'passed' => $this->getPassed(),
                'failed' => $this->getFailed(),
                'score' => $this->getScore(),
                'diagnostics' => $this->getDiagnostics(),
            ],
            'results' => $this->results,
        ];
    }
}
