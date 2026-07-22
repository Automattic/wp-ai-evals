<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\TaskResult;

final class ContainsText implements EvaluatorInterface
{
    private ?string $needle;
    private bool $caseSensitive;

    public function __construct(?string $needle = null, bool $caseSensitive = false)
    {
        $this->needle = $needle;
        $this->caseSensitive = $caseSensitive;
    }

    /** {@inheritDoc} */
    public function evaluate(TaskResult $result, $expected, EvaluationContext $context): EvaluatorResult
    {
        $actual = $result->getOutput();
        $needle = null !== $this->needle ? $this->needle : $expected;

        if (!is_string($actual) || !is_string($needle)) {
            return EvaluatorResult::fail(
                $this->getName(),
                $this->getType(),
                'Contains-text evaluation requires string output and expected text.'
            );
        }

        if ($this->caseSensitive) {
            $contains = false !== strpos($actual, $needle);
        } elseif (function_exists('mb_stripos')) {
            $contains = false !== mb_stripos($actual, $needle);
        } else {
            $contains = false !== stripos($actual, $needle);
        }

        return $contains
            ? EvaluatorResult::pass($this->getName(), $this->getType(), sprintf('Output contained "%s".', $needle))
            : EvaluatorResult::fail($this->getName(), $this->getType(), sprintf('Output did not contain "%s".', $needle));
    }

    public function getName(): string
    {
        return 'Contains text';
    }

    public function getType(): string
    {
        return 'deterministic';
    }
}
