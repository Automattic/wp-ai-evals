<?php

declare(strict_types=1);

namespace HelloDollyAI\Tests;

use HelloDollyAI\RestController;
use PHPUnit\Framework\TestCase;

final class RestControllerTest extends TestCase {

	protected function setUp(): void {
		hello_dolly_ai_test_reset_state();
	}

	public function testRegistersAProtectedBoundedChatRoute(): void {
		RestController::register_routes();

		$route = $GLOBALS['hello_dolly_ai_test_routes']['hello-dolly/v1/chat'];
		self::assertSame( \WP_REST_Server::CREATABLE, $route['methods'] );
		self::assertSame( 1000, $route['args']['message']['maxLength'] );
		self::assertSame( 10, $route['args']['history']['maxItems'] );
		self::assertTrue( $route['permission_callback']() );

		$GLOBALS['hello_dolly_ai_test_logged_in'] = false;
		self::assertFalse( $route['permission_callback']() );

		$GLOBALS['hello_dolly_ai_test_logged_in']     = true;
		$GLOBALS['hello_dolly_ai_test_user_can_read'] = false;
		self::assertFalse( $route['permission_callback']() );
	}

	public function testChatReturnsAServiceErrorWhenTheAbilityResolverIsUnavailable(): void {
		$result = RestController::chat(
			new \WP_REST_Request(
				array(
					'message' => 'Tell me about Dolly.',
					'history' => array(),
				)
			)
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'hello_dolly_ai_unavailable', $result->get_error_code() );
		self::assertSame( 503, $result->get_error_data()['status'] );
		self::assertSame( 1, $GLOBALS['hello_dolly_ai_test_transients']['hello_dolly_ai_rate_7']['count'] );
	}

	public function testChatEnforcesThePerUserRateLimit(): void {
		$GLOBALS['hello_dolly_ai_test_transients']['hello_dolly_ai_rate_7'] = array( 'count' => 30 );

		$result = RestController::chat(
			new \WP_REST_Request(
				array(
					'message' => 'One more question.',
					'history' => array(),
				)
			)
		);

		self::assertInstanceOf( \WP_Error::class, $result );
		self::assertSame( 'hello_dolly_ai_rate_limit', $result->get_error_code() );
		self::assertSame( 429, $result->get_error_data()['status'] );
	}
}
