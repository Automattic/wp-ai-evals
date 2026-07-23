<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\TaskResult;

final class LatencyBelow implements EvaluatorInterface {

	private float $maximum_milliseconds;

	public function __construct( float $maximum_milliseconds ) {
		if ( $maximum_milliseconds <= 0 ) {
			throw new InvalidArgumentException( 'Maximum latency must be greater than zero.' );
		}

		$this->maximum_milliseconds = $maximum_milliseconds;
	}

	/** {@inheritDoc} */
	public function evaluate( TaskResult $result, $expected, EvaluationContext $context ): EvaluatorResult {
		$metadata = $result->get_metadata();
		$actual   = isset( $metadata['duration_ms'] ) ? (float) $metadata['duration_ms'] : INF;
		$passed   = $actual <= $this->maximum_milliseconds;
		$reason   = sprintf( 'Task completed in %.1f ms (limit %.1f ms).', $actual, $this->maximum_milliseconds );

		return $passed
			? EvaluatorResult::pass( $this->get_name(), $this->get_type(), $reason )
			: EvaluatorResult::fail( $this->get_name(), $this->get_type(), $reason );
	}

	public function get_name(): string {
		return 'Latency';
	}

	public function get_type(): string {
		return 'performance';
	}
}
