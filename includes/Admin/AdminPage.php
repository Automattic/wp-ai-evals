<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Admin;

use Automattic\AiEvals\Kernel;
use Automattic\AiEvals\Storage\HistoryStore;

final class AdminPage {

	private const SLUG = 'wp-ai-evals';
	private Kernel $kernel;
	private string $hook_suffix = '';

	public function __construct( Kernel $kernel ) {
		$this->kernel = $kernel;
	}

	public function register_menu(): void {
		$hook_suffix = add_management_page(
			__( 'AI Evals', 'wp-ai-evals' ),
			__( 'AI Evals', 'wp-ai-evals' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook_suffix = is_string( $hook_suffix ) ? $hook_suffix : '';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$root       = dirname( __DIR__, 2 );
		$asset_file = $root . '/build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		/** @var array{dependencies?: list<string>, version?: string} $asset */
		$asset        = require dirname( __DIR__, 2 ) . '/build/admin/index.asset.php';
		$dependencies = isset( $asset['dependencies'] ) && is_array( $asset['dependencies'] )
			? $asset['dependencies']
			: array();
		$version      = isset( $asset['version'] ) ? (string) $asset['version'] : '0.1.0';

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style(
			'wp-ai-evals-admin',
			$this->asset_url( 'build/admin/style-index.css' ),
			array( 'wp-components' ),
			$version
		);
		wp_style_add_data( 'wp-ai-evals-admin', 'rtl', 'replace' );
		wp_enqueue_script(
			'wp-ai-evals-admin',
			$this->asset_url( 'build/admin/index.js' ),
			$dependencies,
			$version,
			true
		);
		wp_set_script_translations( 'wp-ai-evals-admin', 'wp-ai-evals' );

		$settings = wp_json_encode( $this->initial_state(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		wp_add_inline_script(
			'wp-ai-evals-admin',
			'window.wpAiEvalsSettings = ' . ( false === $settings ? '{}' : $settings ) . ';',
			'before'
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$root = dirname( __DIR__, 2 );
		?>
		<div class="wrap wp-ai-evals-wrap">
			<?php
			if (
				! is_readable( $root . '/build/admin/index.asset.php' )
				|| ! is_readable( $root . '/build/admin/index.js' )
				|| ! is_readable( $root . '/build/admin/style-index.css' )
			) :
				?>
				<div class="notice notice-error"><p>
					<?php echo esc_html__( 'The AI Evals Admin assets are missing. Run pnpm build in the library directory.', 'wp-ai-evals' ); ?>
				</p></div>
			<?php endif; ?>
			<div id="wp-ai-evals-admin"></div>
			<noscript>
				<div class="notice notice-warning"><p>
					<?php echo esc_html__( 'The AI Evals interface requires JavaScript. You can still run suites with WP-CLI.', 'wp-ai-evals' ); ?>
				</p></div>
			</noscript>
		</div>
		<?php
	}

	/** @return array<string, mixed> */
	private function initial_state(): array {
		$this->kernel->initialize();
		$registry = $this->kernel->get_registry();
		$suites   = array();

		foreach ( $registry->all() as $suite ) {
			$cases = array();
			foreach ( $suite->get_cases() as $evaluation_case ) {
				$cases[] = array(
					'id'           => $evaluation_case->get_id(),
					'qualified_id' => $suite->get_id() . '/' . $evaluation_case->get_id(),
					'label'        => $evaluation_case->get_label(),
					'type'         => $evaluation_case->get_task()->get_type(),
					'tags'         => $evaluation_case->get_tags(),
					'evaluators'   => count( $evaluation_case->get_evaluators() ),
				);
			}

			$suites[] = array(
				'id'          => $suite->get_id(),
				'label'       => $suite->get_label(),
				'description' => $suite->get_description(),
				'case_count'  => count( $cases ),
				'cases'       => $cases,
			);
		}

		return array(
			'suites'   => $suites,
			'tags'     => $registry->tags(),
			'history'  => ( new HistoryStore() )->all(),
			'platform' => $this->platform_state(),
			'rest'     => array(
				'run'      => '/wp-ai-evals/v1/run',
				'start'    => '/wp-ai-evals/v1/run-sessions',
				'sessions' => '/wp-ai-evals/v1/run-sessions/',
				'runs'     => '/wp-ai-evals/v1/runs/',
				'models'   => '/wp-ai-evals/v1/models',
			),
			'urls'     => array(
				'connectors' => admin_url( 'options-connectors.php' ),
			),
		);
	}

	/** @return array<string, mixed> */
	private function platform_state(): array {
		$connectors             = function_exists( 'wp_get_connectors' ) ? wp_get_connectors() : array();
		$ai_connectors          = array_filter(
			$connectors,
			static function ( array $connector ): bool {
				return isset( $connector['type'] ) && in_array( $connector['type'], array( 'ai', 'ai_provider' ), true );
			}
		);
		$active_connector_count = 0;

		if ( class_exists( \WordPress\AiClient\AiClient::class ) ) {
			try {
				$registry = \WordPress\AiClient\AiClient::defaultRegistry();
				foreach ( array_keys( $ai_connectors ) as $connector_id ) {
					if ( ! $registry->hasProvider( (string) $connector_id )
						|| ! $registry->isProviderConfigured( (string) $connector_id )
					) {
						continue;
					}

					++$active_connector_count;
				}
			} catch ( \Throwable $error ) {
				$active_connector_count = 0;
			}
		}

		return array( 'connector_count' => $active_connector_count );
	}

	private function asset_url( string $relative_path ): string {
		$file              = wp_normalize_path( dirname( __DIR__, 2 ) . '/' . ltrim( $relative_path, '/' ) );
		$content_directory = wp_normalize_path( WP_CONTENT_DIR );

		if ( 0 === strpos( $file, $content_directory . '/' ) ) {
			return content_url( '/' . ltrim( substr( $file, strlen( $content_directory ) ), '/' ) );
		}

		return plugins_url( basename( $file ) );
	}
}
