<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\TaskResult;

final class ExactMatch implements EvaluatorInterface
{
    private bool $caseSensitive;

    public function __construct(bool $caseSensitive = true)
    {
        $this->caseSensitive = $caseSensitive;
    }

    /** {@inheritDoc} */
    public function evaluate(TaskResult $result, $expected, EvaluationContext $context): EvaluatorResult
    {
        $actual = $result->getOutput();

        if (!$this->caseSensitive && is_string($actual) && is_string($expected)) {
            $matches = function_exists('mb_strtolower')
                ? mb_strtolower($actual) === mb_strtolower($expected)
                : strtolower($actual) === strtolower($expected);
        } else {
            $matches = $actual === $expected;
        }

        return $matches
            ? EvaluatorResult::pass($this->getName(), $this->getType(), 'Output exactly matched the expected value.')
            : EvaluatorResult::fail($this->getName(), $this->getType(), 'Output did not exactly match the expected value.');
    }

    public function getName(): string
    {
        return 'Exact match';
    }

    public function getType(): string
    {
        return 'deterministic';
    }
}
