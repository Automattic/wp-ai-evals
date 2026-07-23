<?php

declare(strict_types=1);

if (!function_exists('hello_dolly_ai_test_reset_state')) {
    function hello_dolly_ai_test_reset_state(): void
    {
        $GLOBALS['hello_dolly_ai_test_actions'] = [];
        $GLOBALS['hello_dolly_ai_test_filters'] = [];
        $GLOBALS['hello_dolly_ai_test_categories'] = [];
        $GLOBALS['hello_dolly_ai_test_abilities'] = [];
        $GLOBALS['hello_dolly_ai_test_routes'] = [];
        $GLOBALS['hello_dolly_ai_test_options'] = [];
        $GLOBALS['hello_dolly_ai_test_transients'] = [];
        $GLOBALS['hello_dolly_ai_test_posts'] = [];
        $GLOBALS['hello_dolly_ai_test_post_statuses'] = [];
        $GLOBALS['hello_dolly_ai_test_registered_blocks'] = [];
        $GLOBALS['hello_dolly_ai_test_user_can_read'] = true;
        $GLOBALS['hello_dolly_ai_test_logged_in'] = true;
        $GLOBALS['hello_dolly_ai_test_ai_supported'] = true;
        $GLOBALS['hello_dolly_ai_test_flushes'] = 0;
        $GLOBALS['hello_dolly_ai_test_unique_id'] = 0;
    }
}

hello_dolly_ai_test_reset_state();

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;

        /** @var mixed */
        private $data;

        /** @param mixed $data */
        public function __construct(string $code = '', string $message = '', $data = null)
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        /** @return mixed */
        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server
    {
        public const CREATABLE = 'POST';
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        /** @var array<string, mixed> */
        private array $params;

        /** @param array<string, mixed> $params */
        public function __construct(array $params = [])
        {
            $this->params = $params;
        }

        /** @return mixed */
        public function get_param(string $name)
        {
            return $this->params[$name] ?? null;
        }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var mixed */
        private $data;

        /** @param mixed $data */
        public function __construct($data)
        {
            $this->data = $data;
        }

        /** @return mixed */
        public function get_data()
        {
            return $this->data;
        }
    }
}

if (!class_exists('HelloDollyAiTestPromptBuilder')) {
    class HelloDollyAiTestPromptBuilder
    {
        public function using_abilities(string ...$abilities): self
        {
            return $this;
        }

        public function is_supported_for_text_generation(): bool
        {
            return (bool) $GLOBALS['hello_dolly_ai_test_ai_supported'];
        }
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = ''): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return $url;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return $url;
    }
}

if (!function_exists('add_action')) {
    /** @param callable $callback */
    function add_action(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['hello_dolly_ai_test_actions'][$hook][] = $callback;

        return true;
    }
}

if (!function_exists('add_filter')) {
    /** @param callable $callback */
    function add_filter(string $hook, $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['hello_dolly_ai_test_filters'][$hook][] = $callback;

        return true;
    }
}

if (!function_exists('wp_register_ability_category')) {
    /** @param array<string, mixed> $args */
    function wp_register_ability_category(string $name, array $args): void
    {
        $GLOBALS['hello_dolly_ai_test_categories'][$name] = $args;
    }
}

if (!function_exists('wp_register_ability')) {
    /** @param array<string, mixed> $args */
    function wp_register_ability(string $name, array $args): void
    {
        $GLOBALS['hello_dolly_ai_test_abilities'][$name] = $args;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return 'read' === $capability
            ? (bool) $GLOBALS['hello_dolly_ai_test_user_can_read']
            : true;
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return (bool) $GLOBALS['hello_dolly_ai_test_logged_in'];
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return 7;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string
    {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('get_option')) {
    /** @param mixed $default @return mixed */
    function get_option(string $name, $default = false)
    {
        return $GLOBALS['hello_dolly_ai_test_options'][$name] ?? $default;
    }
}

if (!function_exists('update_option')) {
    /** @param mixed $value @param mixed $autoload */
    function update_option(string $name, $value, $autoload = null): bool
    {
        $GLOBALS['hello_dolly_ai_test_options'][$name] = $value;

        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = ''): string
    {
        return '7.0.2';
    }
}

if (!function_exists('get_post_status')) {
    function get_post_status(int $postId): string
    {
        return $GLOBALS['hello_dolly_ai_test_post_statuses'][$postId] ?? 'publish';
    }
}

if (!function_exists('wp_insert_post')) {
    /** @param array<string, mixed> $post */
    function wp_insert_post(array $post, bool $wpError = false): int
    {
        $id = count($GLOBALS['hello_dolly_ai_test_posts']) + 101;
        $GLOBALS['hello_dolly_ai_test_posts'][$id] = $post;
        $GLOBALS['hello_dolly_ai_test_post_statuses'][$id] = (string) ($post['post_status'] ?? 'draft');

        return $id;
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(): void
    {
        ++$GLOBALS['hello_dolly_ai_test_flushes'];
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink(int $postId): string
    {
        return 'https://example.test/?page_id=' . $postId;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action): string
    {
        return 'test-nonce';
    }
}

if (!function_exists('wp_unique_id')) {
    function wp_unique_id(string $prefix = ''): string
    {
        ++$GLOBALS['hello_dolly_ai_test_unique_id'];

        return $prefix . $GLOBALS['hello_dolly_ai_test_unique_id'];
    }
}

if (!function_exists('get_block_wrapper_attributes')) {
    /** @param array<string, string> $attributes */
    function get_block_wrapper_attributes(array $attributes = []): string
    {
        $pairs = [];
        foreach ($attributes as $name => $value) {
            $pairs[] = esc_attr($name) . '="' . esc_attr($value) . '"';
        }

        return implode(' ', $pairs);
    }
}

if (!function_exists('disabled')) {
    /** @param mixed $disabled @param mixed $current */
    function disabled($disabled, $current = true, bool $display = true): string
    {
        $result = $disabled == $current ? ' disabled="disabled"' : '';
        if ($display) {
            echo $result;
        }

        return $result;
    }
}

if (!function_exists('register_block_type')) {
    /** @param array<string, mixed> $args */
    function register_block_type(string $path, array $args = []): void
    {
        $GLOBALS['hello_dolly_ai_test_registered_blocks'][$path] = $args;
    }
}

if (!function_exists('register_rest_route')) {
    /** @param array<string, mixed> $args */
    function register_rest_route(string $namespace, string $route, array $args): void
    {
        $GLOBALS['hello_dolly_ai_test_routes'][$namespace . $route] = $args;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $value): string
    {
        return trim(strip_tags($value));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $value): string
    {
        return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?? '';
    }
}

if (!function_exists('get_transient')) {
    /** @return mixed */
    function get_transient(string $key)
    {
        return $GLOBALS['hello_dolly_ai_test_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    /** @param mixed $value */
    function set_transient(string $key, $value, int $expiration = 0): bool
    {
        $GLOBALS['hello_dolly_ai_test_transients'][$key] = $value;

        return true;
    }
}

if (!function_exists('rest_ensure_response')) {
    /** @param mixed $value */
    function rest_ensure_response($value): WP_REST_Response
    {
        return new WP_REST_Response($value);
    }
}

if (!function_exists('is_wp_error')) {
    /** @param mixed $value */
    function is_wp_error($value): bool
    {
        return $value instanceof WP_Error;
    }
}

if (!function_exists('wp_ai_client_prompt')) {
    /** @param mixed $prompt */
    function wp_ai_client_prompt($prompt = ''): HelloDollyAiTestPromptBuilder
    {
        return new HelloDollyAiTestPromptBuilder();
    }
}
