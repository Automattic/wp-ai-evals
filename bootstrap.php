<?php

declare(strict_types=1);

use Automattic\AiEvals\Bootstrap;

if ( ! defined( 'WP_AI_EVALS_BOOTSTRAPPED' ) ) {
	define( 'WP_AI_EVALS_BOOTSTRAPPED', true );

	if ( function_exists( 'add_action' ) ) {
		Bootstrap::register();
	}
}
