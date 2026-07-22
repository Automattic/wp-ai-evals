<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\TaskResult;

final class LatencyBelow implements EvaluatorInterface
{
    private float $maximumMilliseconds;

    public function __construct(float $maximumMilliseconds)
    {
        if ($maximumMilliseconds <= 0) {
            throw new InvalidArgumentException('Maximum latency must be greater than zero.');
        }

        $this->maximumMilliseconds = $maximumMilliseconds;
    }

    /** {@inheritDoc} */
    public function evaluate(TaskResult $result, $expected, EvaluationContext $context): EvaluatorResult
    {
        $metadata = $result->getMetadata();
        $actual = isset($metadata['duration_ms']) ? (float) $metadata['duration_ms'] : INF;
        $passed = $actual <= $this->maximumMilliseconds;
        $reason = sprintf('Task completed in %.1f ms (limit %.1f ms).', $actual, $this->maximumMilliseconds);

        return $passed
            ? EvaluatorResult::pass($this->getName(), $this->getType(), $reason)
            : EvaluatorResult::fail($this->getName(), $this->getType(), $reason);
    }

    public function getName(): string
    {
        return 'Latency';
    }

    public function getType(): string
    {
        return 'performance';
    }
}
