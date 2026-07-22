<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\TaskResult;

final class MatchesRegex implements EvaluatorInterface
{
    private string $pattern;

    public function __construct(string $pattern)
    {
        if (false === @preg_match($pattern, '')) {
            throw new InvalidArgumentException(sprintf('Invalid regular expression "%s".', $pattern));
        }

        $this->pattern = $pattern;
    }

    /** {@inheritDoc} */
    public function evaluate(TaskResult $result, $expected, EvaluationContext $context): EvaluatorResult
    {
        $actual = $result->getOutput();
        if (!is_string($actual)) {
            return EvaluatorResult::fail($this->getName(), $this->getType(), 'Regex evaluation requires string output.');
        }

        $matches = 1 === preg_match($this->pattern, $actual);

        return $matches
            ? EvaluatorResult::pass($this->getName(), $this->getType(), 'Output matched the regular expression.')
            : EvaluatorResult::fail($this->getName(), $this->getType(), 'Output did not match the regular expression.');
    }

    public function getName(): string
    {
        return 'Regular expression';
    }

    public function getType(): string
    {
        return 'deterministic';
    }
}
