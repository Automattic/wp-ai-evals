<?php
/**
 * Plugin Name: Hello Dolly AI
 * Description: A connector-powered chat block and eval fixture for learning about Dolly Parton's life and career.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Author: WordPress AI Evals contributors
 * License: GPL-2.0-or-later
 * Text Domain: hello-dolly-ai
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HELLO_DOLLY_AI_VERSION', '0.1.0' );
define( 'HELLO_DOLLY_AI_FILE', __FILE__ );
define( 'HELLO_DOLLY_AI_DIR', __DIR__ );

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/includes/KnowledgeBase.php';
require_once __DIR__ . '/includes/Abilities.php';
require_once __DIR__ . '/includes/Agent.php';
require_once __DIR__ . '/includes/RestController.php';
require_once __DIR__ . '/includes/Block.php';
require_once __DIR__ . '/includes/Plugin.php';

register_activation_hook( __FILE__, array( 'HelloDollyAI\\Plugin', 'activate' ) );
HelloDollyAI\Plugin::boot();
