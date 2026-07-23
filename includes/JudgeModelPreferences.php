<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Throwable;

final class JudgeModelPreferences {

	/** @var list<string> */
	private static array $default_targets = array(
		'anthropic:claude-sonnet-4-6',
		'google:gemini-3.1-pro-preview',
		'openai:gpt-5.4',
	);

	/** @return list<string> */
	public static function targets(): array {
		$preferences = self::$default_targets;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wp_ai_evals_judge_model_target_preferences', $preferences );
			if ( is_array( $filtered ) ) {
				$preferences = $filtered;
			}
		}

		$targets = array();
		foreach ( $preferences as $preference ) {
			if ( ! is_string( $preference ) ) {
				continue;
			}

			try {
				$target                       = ModelTarget::fromString( $preference );
				$targets[ $target->get_id() ] = $target->get_id();
			} catch ( Throwable $error ) {
				// Ignore invalid filtered preferences instead of breaking model discovery.
				unset( $error );
			}
		}

		return array_values( $targets );
	}

	/** @return list<string> */
	public static function model_ids(): array {
		return array_values(
			array_unique(
				array_map(
					static fn( string $target ): string => ModelTarget::fromString( $target )->getModelId(),
					self::targets()
				)
			)
		);
	}

	/**
	 * @param list<array<string, mixed>> $models
	 */
	public static function select( array $models ): ?string {
		$available = array();
		foreach ( $models as $model ) {
			if ( ! isset( $model['target'] ) || ! is_string( $model['target'] ) ) {
				continue;
			}

			$available[ $model['target'] ] = true;
		}

		foreach ( self::targets() as $target ) {
			if ( isset( $available[ $target ] ) ) {
				return $target;
			}
		}

		return null;
	}
}
