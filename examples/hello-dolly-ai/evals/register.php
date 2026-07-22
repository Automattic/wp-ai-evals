<?php

declare(strict_types=1);

use Automattic\AiEvals\Registry;

add_action(
    'wp_ai_evals_init',
    static function (Registry $registry): void {
        $registry->loadDirectory(__DIR__ . '/suites');
    }
);
