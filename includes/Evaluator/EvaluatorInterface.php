<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\TaskResult;

interface EvaluatorInterface
{
    /** @param mixed $expected */
    public function evaluate(TaskResult $result, $expected, EvaluationContext $context): EvaluatorResult;

    public function getName(): string;

    public function getType(): string;
}
