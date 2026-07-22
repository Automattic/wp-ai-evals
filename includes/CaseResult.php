<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class CaseResult implements JsonSerializable
{
    private string $suiteId;
    private string $caseId;
    private string $label;
    private string $taskType;
    private int $iteration;
    private string $status;
    private float $score;
    private float $durationMilliseconds;
    private ?TaskResult $taskResult;

    /** @var list<EvaluatorResult> */
    private array $evaluatorResults;

    private string $error;

    /** @var mixed */
    private $input;

    /** @var mixed */
    private $expected;

    /** @var list<string> */
    private array $tags;

    /** @var array<string, mixed> */
    private array $caseMetadata;

    /**
     * @param list<EvaluatorResult> $evaluatorResults
     * @param mixed $input
     * @param mixed $expected
     * @param list<string> $tags
     * @param array<string, mixed> $caseMetadata
     */
    public function __construct(
        string $suiteId,
        string $caseId,
        string $label,
        string $taskType,
        int $iteration,
        string $status,
        float $score,
        float $durationMilliseconds,
        ?TaskResult $taskResult,
        array $evaluatorResults,
        string $error = '',
        $input = null,
        $expected = null,
        array $tags = [],
        array $caseMetadata = []
    ) {
        $this->suiteId = $suiteId;
        $this->caseId = $caseId;
        $this->label = $label;
        $this->taskType = $taskType;
        $this->iteration = $iteration;
        $this->status = $status;
        $this->score = $score;
        $this->durationMilliseconds = $durationMilliseconds;
        $this->taskResult = $taskResult;
        $this->evaluatorResults = $evaluatorResults;
        $this->error = $error;
        $this->input = $input;
        $this->expected = $expected;
        $this->tags = $tags;
        $this->caseMetadata = $caseMetadata;
    }

    public function getQualifiedId(): string
    {
        return $this->suiteId . '/' . $this->caseId;
    }

    public function getSuiteId(): string
    {
        return $this->suiteId;
    }

    public function getCaseId(): string
    {
        return $this->caseId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTaskType(): string
    {
        return $this->taskType;
    }

    public function getIteration(): int
    {
        return $this->iteration;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getDurationMilliseconds(): float
    {
        return $this->durationMilliseconds;
    }

    public function getTaskResult(): ?TaskResult
    {
        return $this->taskResult;
    }

    /** @return list<EvaluatorResult> */
    public function getEvaluatorResults(): array
    {
        return $this->evaluatorResults;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function hasPassed(): bool
    {
        return 'passed' === $this->status;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'suite' => $this->suiteId,
            'case' => $this->caseId,
            'qualified_id' => $this->getQualifiedId(),
            'label' => $this->label,
            'task_type' => $this->taskType,
            'input' => TaskResult::normalize($this->input),
            'expected' => TaskResult::normalize($this->expected),
            'tags' => $this->tags,
            'metadata' => TaskResult::normalize($this->caseMetadata),
            'iteration' => $this->iteration,
            'status' => $this->status,
            'score' => $this->score,
            'duration_ms' => $this->durationMilliseconds,
            'task_result' => $this->taskResult,
            'evaluators' => $this->evaluatorResults,
            'error' => $this->error,
        ];
    }
}
