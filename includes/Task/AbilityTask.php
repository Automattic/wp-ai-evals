<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\TaskResult;

final class AbilityTask implements TaskInterface {

	private string $ability_name;

	public function __construct( string $ability_name ) {
		$this->ability_name = $ability_name;
	}

	/** {@inheritDoc} */
	public function run( $input, EvaluationContext $context ): TaskResult {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			throw new RuntimeException( 'The WordPress Abilities API is unavailable.' );
		}

		$ability = wp_get_ability( $this->ability_name );
		if ( null === $ability ) {
			throw new RuntimeException(
				sprintf( 'The WordPress ability "%s" is not registered.', esc_html( $this->ability_name ) )
			);
		}

		$result = $ability->execute( $input );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			throw new RuntimeException( esc_html( $result->get_error_message() ) );
		}

		return TaskResult::fromOutput( $result, array( 'ability' => $this->ability_name ) );
	}

	public function get_type(): string {
		return 'ability';
	}
}
