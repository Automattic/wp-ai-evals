<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\TaskResult;

final class MatchesRegex implements EvaluatorInterface {

	private string $pattern;

	public function __construct( string $pattern ) {
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Forbidden,WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid user-authored patterns are converted to exceptions below.
		$is_valid = false !== @preg_match( $pattern, '' );
		if ( ! $is_valid ) {
			throw new InvalidArgumentException( sprintf( 'Invalid regular expression "%s".', esc_html( $pattern ) ) );
		}

		$this->pattern = $pattern;
	}

	/** {@inheritDoc} */
	public function evaluate( TaskResult $result, $expected, EvaluationContext $context ): EvaluatorResult {
		$actual = $result->getOutput();
		if ( ! is_string( $actual ) ) {
			return EvaluatorResult::fail( $this->get_name(), $this->get_type(), 'Regex evaluation requires string output.' );
		}

		$matches = 1 === preg_match( $this->pattern, $actual );

		return $matches
			? EvaluatorResult::pass( $this->get_name(), $this->get_type(), 'Output matched the regular expression.' )
			: EvaluatorResult::fail( $this->get_name(), $this->get_type(), 'Output did not match the regular expression.' );
	}

	public function get_name(): string {
		return 'Regular expression';
	}

	public function get_type(): string {
		return 'deterministic';
	}
}
