<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Admin\AdminPage;
use Automattic\AiEvals\Admin\RestController;
use Automattic\AiEvals\Cli\Command;

final class Bootstrap {

	private static bool $registered = false;

	public static function register(): void {
		if ( self::$registered || ! Environment::is_enabled() ) {
			return;
		}

		self::$registered = true;
		$kernel           = Kernel::instance();
		$admin            = new AdminPage( $kernel );
		$rest             = new RestController( $kernel );

		add_action( 'init', array( $kernel, 'initialize' ), PHP_INT_MAX );
		add_action( 'admin_menu', array( $admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $rest, 'register_routes' ) );

		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'ai-evals', Command::class );
	}
}
