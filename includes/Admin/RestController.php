<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Admin;

use Automattic\AiEvals\Kernel;
use Automattic\AiEvals\ModelCatalog;
use Automattic\AiEvals\RunConfiguration;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Storage\HistoryStore;
use Automattic\AiEvals\Storage\RunSessionStore;

final class RestController {

	private const MODEL_CATALOG_TRANSIENT = 'wp_ai_evals_model_catalog';
	private Kernel $kernel;

	public function __construct( Kernel $kernel ) {
		$this->kernel = $kernel;
	}

	public function register_routes(): void {
		register_rest_route(
			'wp-ai-evals/v1',
			'/models',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'models' ),
				'permission_callback' => array( $this, 'can_run' ),
				'args'                => array(
					'refresh' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			'wp-ai-evals/v1',
			'/run',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run' ),
				'permission_callback' => array( $this, 'can_run' ),
				'args'                => $this->selection_arguments(),
			)
		);

		register_rest_route(
			'wp-ai-evals/v1',
			'/run-sessions',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_session' ),
				'permission_callback' => array( $this, 'can_run' ),
				'args'                => $this->selection_arguments(),
			)
		);

		register_rest_route(
			'wp-ai-evals/v1',
			'/run-sessions/(?P<run_id>[a-zA-Z0-9._-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_session' ),
				'permission_callback' => array( $this, 'can_run' ),
			)
		);

		register_rest_route(
			'wp-ai-evals/v1',
			'/run-sessions/(?P<run_id>[a-zA-Z0-9._-]+)/next',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'advance_session' ),
				'permission_callback' => array( $this, 'can_run' ),
			)
		);

		register_rest_route(
			'wp-ai-evals/v1',
			'/runs/(?P<run_id>[a-zA-Z0-9._-]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_run' ),
				'permission_callback' => array( $this, 'can_run' ),
			)
		);
	}

	public function can_run(): bool {
		return Access::can_run();
	}

	public function models( \WP_REST_Request $request ): \WP_REST_Response {
		$refresh = (bool) $request->get_param( 'refresh' );
		$catalog = ! $refresh && function_exists( 'get_transient' )
			? get_transient( self::MODEL_CATALOG_TRANSIENT )
			: false;

		if ( ! is_array( $catalog ) || ! array_key_exists( 'default_judge_target', $catalog ) ) {
			$catalog = ( new ModelCatalog() )->discover();
			if ( function_exists( 'set_transient' ) ) {
				set_transient( self::MODEL_CATALOG_TRANSIENT, $catalog, 5 * MINUTE_IN_SECONDS );
			}
		}

		return rest_ensure_response( array( 'catalog' => $catalog ) );
	}

	public function run( \WP_REST_Request $request ): \WP_REST_Response {
		$this->kernel->initialize();
		try {
			$report = ( new Runner() )->run(
				$this->kernel->get_registry(),
				$this->selection( $request ),
				$this->configuration( $request )
			);
		} catch ( \Throwable $error ) {
			return new \WP_REST_Response( array( 'message' => $error->getMessage() ), 400 );
		}

		$history = new HistoryStore();
		$history->save( $report );
		$normalized = json_decode( (string) wp_json_encode( $report ), true );

		return rest_ensure_response(
			array(
				'report'  => is_array( $normalized ) ? $normalized : array(),
				'history' => $history->all(),
			)
		);
	}

	public function start_session( \WP_REST_Request $request ): \WP_REST_Response {
		$this->kernel->initialize();
		try {
			$session = ( new RunSessionStore() )->start(
				$this->kernel->get_registry(),
				$this->selection( $request ),
				new Runner(),
				$this->configuration( $request )
			);
		} catch ( \Throwable $error ) {
			return new \WP_REST_Response( array( 'message' => $error->getMessage() ), 400 );
		}

		return rest_ensure_response( array( 'session' => $session ) );
	}

	public function get_session( \WP_REST_Request $request ): \WP_REST_Response {
		$session = ( new RunSessionStore() )->status( (string) $request->get_param( 'run_id' ) );
		if ( null === $session ) {
			return new \WP_REST_Response( array( 'message' => __( 'The live evaluation run was not found or expired.', 'wp-ai-evals' ) ), 404 );
		}

		return rest_ensure_response( array( 'session' => $session ) );
	}

	public function advance_session( \WP_REST_Request $request ): \WP_REST_Response {
		$this->kernel->initialize();

		try {
			$advanced = ( new RunSessionStore() )->advance(
				(string) $request->get_param( 'run_id' ),
				$this->kernel->get_registry(),
				new Runner()
			);
		} catch ( \Throwable $error ) {
			return new \WP_REST_Response( array( 'message' => $error->getMessage() ), 404 );
		}

		return rest_ensure_response(
			array(
				'session' => $advanced['session'],
				'report'  => $this->normalize( $advanced['report'] ),
				'history' => null !== $advanced['report'] ? ( new HistoryStore() )->all() : null,
			)
		);
	}

	public function get_run( \WP_REST_Request $request ): \WP_REST_Response {
		$report = ( new HistoryStore() )->find( (string) $request->get_param( 'run_id' ) );
		if ( null === $report ) {
			return new \WP_REST_Response(
				array(
					'message' => __( 'Full details are not available for this run. Older summary-only runs cannot be expanded.', 'wp-ai-evals' ),
				),
				404
			);
		}

		return rest_ensure_response( array( 'report' => $report ) );
	}

	/** @return array<string, mixed> */
	private function list_argument(): array {
		return array(
			'type'    => 'array',
			'default' => array(),
			'items'   => array( 'type' => 'string' ),
		);
	}

	/** @return array<string, mixed> */
	private function selection_arguments(): array {
		return array(
			'suites'             => $this->list_argument(),
			'tags'               => $this->list_argument(),
			'cases'              => $this->list_argument(),
			'repetitions'        => array(
				'type'    => 'integer',
				'default' => 1,
				'minimum' => 1,
				'maximum' => 10,
			),
			'model_targets'      => $this->list_argument(),
			'judge_model_target' => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	private function selection( \WP_REST_Request $request ): Selection {
		return new Selection(
			$this->sanitize_list( $request->get_param( 'suites' ) ),
			$this->sanitize_list( $request->get_param( 'cases' ) ),
			$this->sanitize_list( $request->get_param( 'tags' ) ),
			max( 1, min( 10, (int) $request->get_param( 'repetitions' ) ) )
		);
	}

	private function configuration( \WP_REST_Request $request ): RunConfiguration {
		return RunConfiguration::fromStrings(
			$this->sanitize_list( $request->get_param( 'model_targets' ) ),
			sanitize_text_field( (string) $request->get_param( 'judge_model_target' ) )
		);
	}

	/** @param mixed $value @return array<string, mixed>|null */
	private function normalize( $value ): ?array {
		if ( null === $value ) {
			return null;
		}

		$normalized = json_decode( (string) wp_json_encode( $value ), true );

		return is_array( $normalized ) ? $normalized : array();
	}

	/** @param mixed $value @return list<string> */
	private function sanitize_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						static fn( $item ): string => sanitize_text_field( (string) $item ),
						$value
					)
				)
			)
		);
	}
}
