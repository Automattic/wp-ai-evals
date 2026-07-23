<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\TaskResult;

final class ContainsText implements EvaluatorInterface {

	private ?string $needle;
	private bool $case_sensitive;

	public function __construct( ?string $needle = null, bool $case_sensitive = false ) {
		$this->needle         = $needle;
		$this->case_sensitive = $case_sensitive;
	}

	/** {@inheritDoc} */
	public function evaluate( TaskResult $result, $expected, EvaluationContext $context ): EvaluatorResult {
		$actual = $result->getOutput();
		$needle = null !== $this->needle ? $this->needle : $expected;

		if ( ! is_string( $actual ) || ! is_string( $needle ) ) {
			return EvaluatorResult::fail(
				$this->get_name(),
				$this->get_type(),
				'Contains-text evaluation requires string output and expected text.'
			);
		}

		if ( $this->case_sensitive ) {
			$contains = false !== strpos( $actual, $needle );
		} elseif ( function_exists( 'mb_stripos' ) ) {
			$contains = false !== mb_stripos( $actual, $needle );
		} else {
			$contains = false !== stripos( $actual, $needle );
		}

		return $contains
			? EvaluatorResult::pass( $this->get_name(), $this->get_type(), sprintf( 'Output contained "%s".', $needle ) )
			: EvaluatorResult::fail( $this->get_name(), $this->get_type(), sprintf( 'Output did not contain "%s".', $needle ) );
	}

	public function get_name(): string {
		return 'Contains text';
	}

	public function get_type(): string {
		return 'deterministic';
	}
}
