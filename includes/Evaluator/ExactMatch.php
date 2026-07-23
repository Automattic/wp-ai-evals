<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\TaskResult;

final class ExactMatch implements EvaluatorInterface {

	private bool $case_sensitive;

	public function __construct( bool $case_sensitive = true ) {
		$this->case_sensitive = $case_sensitive;
	}

	/** {@inheritDoc} */
	public function evaluate( TaskResult $result, $expected, EvaluationContext $context ): EvaluatorResult {
		$actual = $result->getOutput();

		if ( ! $this->case_sensitive && is_string( $actual ) && is_string( $expected ) ) {
			$matches = function_exists( 'mb_strtolower' )
				? mb_strtolower( $actual ) === mb_strtolower( $expected )
				: strtolower( $actual ) === strtolower( $expected );
		} else {
			$matches = $actual === $expected;
		}

		return $matches
			? EvaluatorResult::pass( $this->get_name(), $this->get_type(), 'Output exactly matched the expected value.' )
			: EvaluatorResult::fail( $this->get_name(), $this->get_type(), 'Output did not exactly match the expected value.' );
	}

	public function get_name(): string {
		return 'Exact match';
	}

	public function get_type(): string {
		return 'deterministic';
	}
}
