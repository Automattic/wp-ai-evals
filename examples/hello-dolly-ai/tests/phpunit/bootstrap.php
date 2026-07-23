<?php

declare(strict_types=1);

$pluginDirectory = dirname(__DIR__, 2);

require_once __DIR__ . '/wordpress-stubs.php';

if (!defined('HELLO_DOLLY_AI_VERSION')) {
    define('HELLO_DOLLY_AI_VERSION', '0.1.0-test');
}
if (!defined('HELLO_DOLLY_AI_FILE')) {
    define('HELLO_DOLLY_AI_FILE', $pluginDirectory . '/hello-dolly-ai.php');
}
if (!defined('HELLO_DOLLY_AI_DIR')) {
    define('HELLO_DOLLY_AI_DIR', $pluginDirectory);
}
if (!defined('WP_AI_EVALS_BOOTSTRAPPED')) {
    define('WP_AI_EVALS_BOOTSTRAPPED', true);
}

require_once $pluginDirectory . '/includes/KnowledgeBase.php';
require_once $pluginDirectory . '/includes/Abilities.php';
require_once $pluginDirectory . '/includes/Agent.php';
require_once $pluginDirectory . '/includes/RestController.php';
require_once $pluginDirectory . '/includes/Block.php';
require_once $pluginDirectory . '/includes/Plugin.php';
