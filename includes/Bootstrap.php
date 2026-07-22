<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Admin\AdminPage;
use Automattic\AiEvals\Admin\RestController;
use Automattic\AiEvals\Cli\Command;

final class Bootstrap
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered || !Environment::isEnabled()) {
            return;
        }

        self::$registered = true;
        $kernel = Kernel::instance();
        $admin = new AdminPage($kernel);
        $rest = new RestController($kernel);

        add_action('init', [$kernel, 'initialize'], PHP_INT_MAX);
        add_action('admin_menu', [$admin, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$admin, 'enqueueAssets']);
        add_action('rest_api_init', [$rest, 'registerRoutes']);

        if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
            \WP_CLI::add_command('ai-evals', Command::class);
        }
    }
}
