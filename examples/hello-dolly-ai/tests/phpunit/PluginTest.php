<?php

declare(strict_types=1);

namespace HelloDollyAI\Tests;

use HelloDollyAI\Abilities;
use HelloDollyAI\Block;
use HelloDollyAI\Plugin;
use HelloDollyAI\RestController;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {

	protected function setUp(): void {
		hello_dolly_ai_test_reset_state();
	}

	public function testBootRegistersThePluginHooks(): void {
		Plugin::boot();

		self::assertContains(
			array( Abilities::class, 'register_category' ),
			$GLOBALS['hello_dolly_ai_test_actions']['wp_abilities_api_categories_init']
		);
		self::assertContains(
			array( Abilities::class, 'register' ),
			$GLOBALS['hello_dolly_ai_test_actions']['wp_abilities_api_init']
		);
		self::assertContains( array( Block::class, 'register' ), $GLOBALS['hello_dolly_ai_test_actions']['init'] );
		self::assertContains(
			array( RestController::class, 'register_routes' ),
			$GLOBALS['hello_dolly_ai_test_actions']['rest_api_init']
		);
		self::assertContains(
			array( Plugin::class, 'plugin_action_links' ),
			$GLOBALS['hello_dolly_ai_test_filters']['plugin_action_links']
		);
	}

	public function testActivationCreatesTheDemoPageOnlyOnce(): void {
		Plugin::activate();

		self::assertCount( 1, $GLOBALS['hello_dolly_ai_test_posts'] );
		$page_id = (int) $GLOBALS['hello_dolly_ai_test_options']['hello_dolly_ai_demo_page_id'];
		self::assertSame( 'hello-dolly-ai', $GLOBALS['hello_dolly_ai_test_posts'][ $page_id ]['post_name'] );
		self::assertStringContainsString( 'wp:hello-dolly-ai/hello-dolly', $GLOBALS['hello_dolly_ai_test_posts'][ $page_id ]['post_content'] );

		Plugin::activate();

		self::assertCount( 1, $GLOBALS['hello_dolly_ai_test_posts'] );
	}

	public function testPluginActionLinksExposeChatConnectorsAndEvals(): void {
		$GLOBALS['hello_dolly_ai_test_options']['hello_dolly_ai_demo_page_id'] = 123;

		$links = Plugin::plugin_action_links(
			array( 'deactivate' => '<a>Deactivate</a>' ),
			'hello-dolly-ai/hello-dolly-ai.php'
		);

		self::assertArrayHasKey( 'hello-dolly-ai-chat', $links );
		self::assertArrayHasKey( 'hello-dolly-ai-connectors', $links );
		self::assertArrayHasKey( 'hello-dolly-ai-evals', $links );
		self::assertArrayHasKey( 'deactivate', $links );
	}
}
