<?php

declare(strict_types=1);

namespace HelloDollyAI\Tests;

use HelloDollyAI\Abilities;
use HelloDollyAI\Block;
use HelloDollyAI\Plugin;
use HelloDollyAI\RestController;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        hello_dolly_ai_test_reset_state();
    }

    public function testBootRegistersThePluginHooks(): void
    {
        Plugin::boot();

        self::assertContains(
            [Abilities::class, 'registerCategory'],
            $GLOBALS['hello_dolly_ai_test_actions']['wp_abilities_api_categories_init']
        );
        self::assertContains(
            [Abilities::class, 'register'],
            $GLOBALS['hello_dolly_ai_test_actions']['wp_abilities_api_init']
        );
        self::assertContains([Block::class, 'register'], $GLOBALS['hello_dolly_ai_test_actions']['init']);
        self::assertContains(
            [RestController::class, 'registerRoutes'],
            $GLOBALS['hello_dolly_ai_test_actions']['rest_api_init']
        );
    }

    public function testActivationCreatesTheDemoPageOnlyOnce(): void
    {
        Plugin::activate();

        self::assertCount(1, $GLOBALS['hello_dolly_ai_test_posts']);
        $pageId = (int) $GLOBALS['hello_dolly_ai_test_options']['hello_dolly_ai_demo_page_id'];
        self::assertSame('hello-dolly-ai', $GLOBALS['hello_dolly_ai_test_posts'][$pageId]['post_name']);
        self::assertStringContainsString('wp:hello-dolly-ai/hello-dolly', $GLOBALS['hello_dolly_ai_test_posts'][$pageId]['post_content']);
        self::assertSame(1, $GLOBALS['hello_dolly_ai_test_flushes']);

        Plugin::activate();

        self::assertCount(1, $GLOBALS['hello_dolly_ai_test_posts']);
        self::assertSame(1, $GLOBALS['hello_dolly_ai_test_flushes']);
    }

    public function testPluginActionLinksExposeChatConnectorsAndEvals(): void
    {
        $GLOBALS['hello_dolly_ai_test_options']['hello_dolly_ai_demo_page_id'] = 123;

        $links = Plugin::pluginActionLinks(['deactivate' => '<a>Deactivate</a>']);

        self::assertArrayHasKey('hello-dolly-ai-chat', $links);
        self::assertArrayHasKey('hello-dolly-ai-connectors', $links);
        self::assertArrayHasKey('hello-dolly-ai-evals', $links);
        self::assertArrayHasKey('deactivate', $links);
    }
}
