<?php

declare(strict_types=1);

namespace HelloDollyAI;

final class Plugin {

	private const PAGE_OPTION = 'hello_dolly_ai_demo_page_id';

	public static function boot(): void {
		add_action( 'wp_abilities_api_categories_init', array( Abilities::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( Abilities::class, 'register' ) );
		add_action( 'init', array( Block::class, 'register' ) );
		add_action( 'rest_api_init', array( RestController::class, 'register_routes' ) );
		add_filter(
			'plugin_action_links',
			array( self::class, 'plugin_action_links' ),
			10,
			2
		);
	}

	public static function activate(): void {
		if ( version_compare( get_bloginfo( 'version' ), '7.0', '<' ) ) {
			return;
		}

		$existing = (int) get_option( self::PAGE_OPTION, 0 );
		if ( $existing > 0 && 'trash' !== get_post_status( $existing ) ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Hello Dolly AI',
				'post_name'    => 'hello-dolly-ai',
				'post_content' => '<!-- wp:hello-dolly-ai/hello-dolly {"greeting":"Hi! I’m Hello Dolly. Ask me about Dolly Parton’s life, music, or philanthropy."} /-->',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return;
		}

		update_option( self::PAGE_OPTION, (int) $page_id, false );
	}

	/**
	 * @param array<string, string> $links
	 * @return array<string, string>
	 */
	public static function plugin_action_links( array $links, string $plugin_file ): array {
		if ( plugin_basename( HELLO_DOLLY_AI_FILE ) !== $plugin_file ) {
			return $links;
		}

		$page_id           = (int) get_option( self::PAGE_OPTION, 0 );
		$hello_dolly_links = array();

		if ( $page_id > 0 ) {
			$hello_dolly_links['hello-dolly-ai-chat'] = '<a href="' . esc_url( get_permalink( $page_id ) ) . '">' . esc_html__( 'View chat page', 'hello-dolly-ai' ) . '</a>';
		}

		$hello_dolly_links['hello-dolly-ai-connectors'] = '<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">' . esc_html__( 'Configure connectors', 'hello-dolly-ai' ) . '</a>';

		if ( defined( 'WP_AI_EVALS_BOOTSTRAPPED' ) ) {
			$hello_dolly_links['hello-dolly-ai-evals'] = '<a href="' . esc_url( admin_url( 'tools.php?page=wp-ai-evals' ) ) . '">' . esc_html__( 'Run AI evals', 'hello-dolly-ai' ) . '</a>';
		}

		return array_merge( $hello_dolly_links, $links );
	}
}
