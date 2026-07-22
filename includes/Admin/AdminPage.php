<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Admin;

use Automattic\AiEvals\Kernel;
use Automattic\AiEvals\Storage\HistoryStore;

final class AdminPage
{
    private const SLUG = 'wp-ai-evals';
    private Kernel $kernel;
    private string $hookSuffix = '';

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    public function registerMenu(): void
    {
        $hookSuffix = add_management_page(
            __('AI Evals', 'wp-ai-evals'),
            __('AI Evals', 'wp-ai-evals'),
            $this->capability(),
            self::SLUG,
            [$this, 'render']
        );

        $this->hookSuffix = is_string($hookSuffix) ? $hookSuffix : '';
    }

    public function enqueueAssets(string $hookSuffix): void
    {
        if ('' === $this->hookSuffix || $hookSuffix !== $this->hookSuffix) {
            return;
        }

        $root = dirname(__DIR__, 2);
        $assetFile = $root . '/build/admin/index.asset.php';
        if (!is_readable($assetFile)) {
            return;
        }

        /** @var array{dependencies?: list<string>, version?: string} $asset */
        $asset = require $assetFile;
        $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies'])
            ? $asset['dependencies']
            : [];
        $version = isset($asset['version']) ? (string) $asset['version'] : '0.1.0';

        wp_enqueue_style('wp-components');
        wp_enqueue_style(
            'wp-ai-evals-admin',
            $this->assetUrl('build/admin/style-index.css'),
            ['wp-components'],
            $version
        );
        wp_style_add_data('wp-ai-evals-admin', 'rtl', 'replace');
        wp_enqueue_script(
            'wp-ai-evals-admin',
            $this->assetUrl('build/admin/index.js'),
            $dependencies,
            $version,
            true
        );
        wp_set_script_translations('wp-ai-evals-admin', 'wp-ai-evals');

        $settings = wp_json_encode($this->initialState(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        wp_add_inline_script(
            'wp-ai-evals-admin',
            'window.wpAiEvalsSettings = ' . (false === $settings ? '{}' : $settings) . ';',
            'before'
        );
    }

    public function render(): void
    {
        if (!current_user_can($this->capability())) {
            return;
        }

        $root = dirname(__DIR__, 2);
        ?>
        <div class="wrap wp-ai-evals-wrap">
            <?php if (
                !is_readable($root . '/build/admin/index.asset.php')
                || !is_readable($root . '/build/admin/index.js')
                || !is_readable($root . '/build/admin/style-index.css')
            ) : ?>
                <div class="notice notice-error"><p>
                    <?php echo esc_html__('The AI Evals Admin assets are missing. Run npm run build in the library directory.', 'wp-ai-evals'); ?>
                </p></div>
            <?php endif; ?>
            <div id="wp-ai-evals-admin"></div>
            <noscript>
                <div class="notice notice-warning"><p>
                    <?php echo esc_html__('The AI Evals interface requires JavaScript. You can still run suites with WP-CLI.', 'wp-ai-evals'); ?>
                </p></div>
            </noscript>
        </div>
        <?php
    }

    /** @return array<string, mixed> */
    private function initialState(): array
    {
        $this->kernel->initialize();
        $registry = $this->kernel->getRegistry();
        $suites = [];

        foreach ($registry->all() as $suite) {
            $cases = [];
            foreach ($suite->getCases() as $case) {
                $cases[] = [
                    'id' => $case->getId(),
                    'qualified_id' => $suite->getId() . '/' . $case->getId(),
                    'label' => $case->getLabel(),
                    'type' => $case->getTask()->getType(),
                    'tags' => $case->getTags(),
                    'evaluators' => count($case->getEvaluators()),
                ];
            }

            $suites[] = [
                'id' => $suite->getId(),
                'label' => $suite->getLabel(),
                'description' => $suite->getDescription(),
                'case_count' => count($cases),
                'cases' => $cases,
            ];
        }

        return [
            'suites' => $suites,
            'tags' => $registry->tags(),
            'history' => (new HistoryStore())->all(),
            'platform' => $this->platformState(),
            'rest' => [
                'run' => '/wp-ai-evals/v1/run',
                'start' => '/wp-ai-evals/v1/run-sessions',
                'sessions' => '/wp-ai-evals/v1/run-sessions/',
                'runs' => '/wp-ai-evals/v1/runs/',
            ],
            'urls' => [
                'connectors' => admin_url('options-connectors.php'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function platformState(): array
    {
        $connectors = function_exists('wp_get_connectors') ? wp_get_connectors() : [];
        $aiConnectors = array_filter($connectors, static function (array $connector): bool {
            return isset($connector['type']) && in_array($connector['type'], ['ai', 'ai_provider'], true);
        });
        $textSupported = false;

        if (function_exists('wp_ai_client_prompt')) {
            $support = wp_ai_client_prompt('eval capability check')->is_supported_for_text_generation();
            $textSupported = !is_wp_error($support) && (bool) $support;
        }

        return [
            'wordpress_version' => get_bloginfo('version'),
            'connector_count' => count($aiConnectors),
            'text_supported' => $textSupported,
        ];
    }

    private function capability(): string
    {
        return (string) apply_filters('wp_ai_evals_capability', 'manage_options');
    }

    private function assetUrl(string $relativePath): string
    {
        $file = wp_normalize_path(dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/'));
        $contentDirectory = wp_normalize_path(WP_CONTENT_DIR);

        if (0 === strpos($file, $contentDirectory . '/')) {
            return content_url('/' . ltrim(substr($file, strlen($contentDirectory)), '/'));
        }

        return plugins_url(basename($file));
    }
}
