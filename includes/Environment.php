<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

final class Environment
{
    public static function isEnabled(): bool
    {
        if (defined('WP_AI_EVALS_ENABLED')) {
            return (bool) WP_AI_EVALS_ENABLED;
        }

        if (!function_exists('wp_get_environment_type')) {
            return false;
        }

        return 'production' !== wp_get_environment_type();
    }

    public static function assertSupported(): void
    {
        if (!function_exists('wp_ai_client_prompt')) {
            throw new Exception\RuntimeException(
                'WordPress AI Evals requires WordPress 7.0 or newer and the built-in AI Client.'
            );
        }
    }
}
