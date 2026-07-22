<?php

declare(strict_types=1);

namespace HelloDollyAI;

final class Plugin
{
    private const PAGE_OPTION = 'hello_dolly_ai_demo_page_id';

    public static function boot(): void
    {
        add_action('wp_abilities_api_categories_init', [Abilities::class, 'registerCategory']);
        add_action('wp_abilities_api_init', [Abilities::class, 'register']);
        add_action('init', [Block::class, 'register']);
        add_action('rest_api_init', [RestController::class, 'registerRoutes']);
        add_filter(
            'plugin_action_links_' . plugin_basename(HELLO_DOLLY_AI_FILE),
            [self::class, 'pluginActionLinks']
        );
    }

    public static function activate(): void
    {
        if (version_compare(get_bloginfo('version'), '7.0', '<')) {
            return;
        }

        $existing = (int) get_option(self::PAGE_OPTION, 0);
        if ($existing > 0 && 'trash' !== get_post_status($existing)) {
            return;
        }

        $pageId = wp_insert_post(
            [
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => 'Hello Dolly AI',
                'post_name' => 'hello-dolly-ai',
                'post_content' => '<!-- wp:hello-dolly-ai/hello-dolly {"greeting":"Hi! I’m Hello Dolly. Ask me about Dolly Parton’s life, music, or philanthropy."} /-->',
            ],
            true
        );

        if (!is_wp_error($pageId)) {
            update_option(self::PAGE_OPTION, (int) $pageId, false);
        }

        flush_rewrite_rules();
    }

    /**
     * @param array<string, string> $links
     * @return array<string, string>
     */
    public static function pluginActionLinks(array $links): array
    {
        $pageId = (int) get_option(self::PAGE_OPTION, 0);
        $helloDollyLinks = [];

        if ($pageId > 0) {
            $helloDollyLinks['hello-dolly-ai-chat'] = '<a href="' . esc_url(get_permalink($pageId)) . '">' . esc_html__('View chat page', 'hello-dolly-ai') . '</a>';
        }

        $helloDollyLinks['hello-dolly-ai-connectors'] = '<a href="' . esc_url(admin_url('options-connectors.php')) . '">' . esc_html__('Configure connectors', 'hello-dolly-ai') . '</a>';

        if (defined('WP_AI_EVALS_BOOTSTRAPPED')) {
            $helloDollyLinks['hello-dolly-ai-evals'] = '<a href="' . esc_url(admin_url('tools.php?page=wp-ai-evals')) . '">' . esc_html__('Run AI evals', 'hello-dolly-ai') . '</a>';
        }

        return array_merge($helloDollyLinks, $links);
    }
}
