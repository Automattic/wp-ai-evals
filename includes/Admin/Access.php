<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Admin;

final class Access {

	public static function get_capability(): string {
		$capability = 'manage_options';

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wp_ai_evals_capability', $capability );
			if ( is_string( $filtered ) && '' !== trim( $filtered ) ) {
				$capability = trim( $filtered );
			}
		}

		return $capability;
	}

	public static function can_run(): bool {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Site administrators may filter the required capability.
		return function_exists( 'current_user_can' ) && current_user_can( self::get_capability() );
	}
}
