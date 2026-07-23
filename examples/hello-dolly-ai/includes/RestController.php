<?php

declare(strict_types=1);

namespace HelloDollyAI;

final class RestController {

	public static function register_routes(): void {
		register_rest_route(
			'hello-dolly/v1',
			'/chat',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'chat' ),
				'permission_callback' => static fn(): bool => is_user_logged_in() && current_user_can( 'read' ),
				'args'                => array(
					'message' => array(
						'type'      => 'string',
						'required'  => true,
						'minLength' => 1,
						'maxLength' => 1000,
					),
					'history' => array(
						'type'     => 'array',
						'required' => false,
						'default'  => array(),
						'maxItems' => 10,
						'items'    => array(
							'type'                 => 'object',
							'properties'           => array(
								'role'    => array(
									'type' => 'string',
									'enum' => array( 'user', 'assistant' ),
								),
								'content' => array(
									'type'      => 'string',
									'maxLength' => 4000,
								),
							),
							'required'             => array( 'role', 'content' ),
							'additionalProperties' => false,
						),
					),
				),
			)
		);
	}

	/** @return \WP_REST_Response|\WP_Error */
	public static function chat( \WP_REST_Request $request ) {
		$rate_limit = self::check_rate_limit();
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$history = self::sanitize_history( (array) $request->get_param( 'history' ) );
		$result  = ( new Agent() )->respond( $message, $history );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/** @return true|\WP_Error */
	private static function check_rate_limit() {
		$user_id = get_current_user_id();
		$key     = 'hello_dolly_ai_rate_' . $user_id;
		$bucket  = get_transient( $key );
		$bucket  = is_array( $bucket ) ? $bucket : array( 'count' => 0 );

		if ( (int) $bucket['count'] >= 30 ) {
			return new \WP_Error(
				'hello_dolly_ai_rate_limit',
				__( 'You have reached the local demo limit of 30 messages per minute.', 'hello-dolly-ai' ),
				array( 'status' => 429 )
			);
		}

		$bucket['count'] = (int) $bucket['count'] + 1;
		set_transient( $key, $bucket, MINUTE_IN_SECONDS );

		return true;
	}

	/** @param list<mixed> $history @return list<array{role: string, content: string}> */
	private static function sanitize_history( array $history ): array {
		$clean = array();
		foreach ( array_slice( $history, -10 ) as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$role    = isset( $item['role'] ) ? sanitize_key( (string) $item['role'] ) : '';
			$content = isset( $item['content'] ) ? sanitize_textarea_field( (string) $item['content'] ) : '';
			if ( ! in_array( $role, array( 'user', 'assistant' ), true ) || '' === $content ) {
				continue;
			}

			$clean[] = array(
				'role'    => $role,
				'content' => $content,
			);
		}

		return $clean;
	}
}
