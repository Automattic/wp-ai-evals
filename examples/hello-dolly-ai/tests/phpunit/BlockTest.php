<?php

declare(strict_types=1);

namespace HelloDollyAI\Tests;

use HelloDollyAI\Block;
use PHPUnit\Framework\TestCase;

final class BlockTest extends TestCase {

	protected function setUp(): void {
		hello_dolly_ai_test_reset_state();
	}

	public function testRenderEscapesTheGreetingAndRequiresSignIn(): void {
		$GLOBALS['hello_dolly_ai_test_logged_in'] = false;

		$html = Block::render( array( 'greeting' => '<script>alert(1)</script>' ) );

		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		self::assertStringContainsString( 'Sign in to use this development chat demo.', $html );
		self::assertStringContainsString( 'disabled="disabled"', $html );
	}

	public function testRenderShowsConnectorSetupWhenTextGenerationIsUnavailable(): void {
		$GLOBALS['hello_dolly_ai_test_ai_supported'] = false;

		$html = Block::render( array() );

		self::assertStringContainsString( 'Configure an AI provider', $html );
		self::assertStringContainsString( 'options-connectors.php', $html );
		self::assertStringContainsString( 'data-ready="false"', $html );
	}

	public function testRenderExposesAnEnabledNonceProtectedChatFormWhenReady(): void {
		$html = Block::render( array() );

		self::assertStringContainsString( 'data-endpoint="https://example.test/wp-json/hello-dolly/v1/chat"', $html );
		self::assertStringContainsString( 'data-nonce="test-nonce"', $html );
		self::assertStringContainsString( 'data-ready="true"', $html );
		self::assertStringContainsString( 'class="hello-dolly-ai-chat__form"', $html );
		self::assertStringNotContainsString( 'disabled="disabled"', $html );
	}
}
