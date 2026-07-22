<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Closure;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\TaskResult;

final class CallbackEvaluator implements EvaluatorInterface
{
    private string $name;
    private Closure $callback;

    public function __construct(string $name, callable $callback)
    {
        $this->name = $name;
        $this->callback = Closure::fromCallable($callback);
    }

    /** {@inheritDoc} */
    public function evaluate(TaskResult $result, $expected, EvaluationContext $context): EvaluatorResult
    {
        $evaluation = ($this->callback)($result->getOutput(), $expected, $result, $context);

        if ($evaluation instanceof EvaluatorResult) {
            return $evaluation;
        }
        if (is_bool($evaluation)) {
            return $evaluation
                ? EvaluatorResult::pass($this->getName(), $this->getType())
                : EvaluatorResult::fail($this->getName(), $this->getType(), 'Custom evaluator returned false.');
        }
        if (is_int($evaluation) || is_float($evaluation)) {
            $score = (float) $evaluation;

            return new EvaluatorResult(
                $this->getName(),
                $this->getType(),
                $score >= 0.5,
                $score,
                sprintf('Custom evaluator returned a score of %.3f.', $score)
            );
        }

        throw new RuntimeException('A custom evaluator must return bool, float, int, or EvaluatorResult.');
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return 'custom';
    }
}
