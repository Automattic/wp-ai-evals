<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\TaskResult;
use Closure;

final class ModelCallableTask implements ModelTargetAwareTaskInterface {

	private Closure $callback;
	private string $type;

	public function __construct( callable $callback, string $type = 'callable:model' ) {
		$this->callback = Closure::fromCallable( $callback );
		$this->type     = $type;
	}

	/** {@inheritDoc} */
	public function run( $input, EvaluationContext $context ): TaskResult {
		$result = ( $this->callback )( $input, $context );

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			throw new RuntimeException( esc_html( $result->get_error_message() ) );
		}

		return $result instanceof TaskResult ? $result : TaskResult::fromOutput( $result );
	}

	public function get_type(): string {
		return $this->type;
	}
}
