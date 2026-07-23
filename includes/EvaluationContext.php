<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

final class EvaluationContext
{
    private Suite $suite;
    private EvaluationCase $case;
    private int $iteration;
    private ?ModelTarget $modelTarget;
    private RunConfiguration $runConfiguration;

    public function __construct(
        Suite $suite,
        EvaluationCase $case,
        int $iteration,
        ?ModelTarget $modelTarget = null,
        ?RunConfiguration $runConfiguration = null
    ) {
        $this->suite = $suite;
        $this->case = $case;
        $this->iteration = $iteration;
        $this->modelTarget = $modelTarget;
        $this->runConfiguration = $runConfiguration ?? new RunConfiguration();
    }

    public function getSuite(): Suite
    {
        return $this->suite;
    }

    public function getCase(): EvaluationCase
    {
        return $this->case;
    }

    public function getIteration(): int
    {
        return $this->iteration;
    }

    public function getQualifiedCaseId(): string
    {
        return $this->suite->getId() . '/' . $this->case->getId();
    }

    public function getModelTarget(): ?ModelTarget
    {
        return $this->modelTarget;
    }

    public function getJudgeModelTarget(): ?ModelTarget
    {
        return $this->runConfiguration->getJudgeModelTarget();
    }

    public function getRunConfiguration(): RunConfiguration
    {
        return $this->runConfiguration;
    }
}
