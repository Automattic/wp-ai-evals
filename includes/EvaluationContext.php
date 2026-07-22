<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

final class EvaluationContext
{
    private Suite $suite;
    private EvaluationCase $case;
    private int $iteration;

    public function __construct(Suite $suite, EvaluationCase $case, int $iteration)
    {
        $this->suite = $suite;
        $this->case = $case;
        $this->iteration = $iteration;
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
}
