<?php

declare(strict_types=1);

namespace HelloDollyAI\Tests;

use HelloDollyAI\Abilities;
use PHPUnit\Framework\TestCase;

final class AbilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        hello_dolly_ai_test_reset_state();
    }

    public function testRegistersTheCategoryAndAllReadOnlyAbilities(): void
    {
        Abilities::registerCategory();
        Abilities::register();

        self::assertArrayHasKey('hello-dolly-knowledge', $GLOBALS['hello_dolly_ai_test_categories']);
        self::assertSame(Abilities::names(), array_keys($GLOBALS['hello_dolly_ai_test_abilities']));

        foreach (Abilities::names() as $name) {
            $ability = $GLOBALS['hello_dolly_ai_test_abilities'][$name];
            self::assertSame('hello-dolly-knowledge', $ability['category']);
            self::assertTrue($ability['meta']['annotations']['readonly']);
            self::assertFalse($ability['meta']['annotations']['destructive']);
            self::assertFalse($ability['meta']['show_in_rest']);
        }
    }

    public function testAbilityCallbacksReturnGroundedStructuredData(): void
    {
        Abilities::register();

        $fact = $GLOBALS['hello_dolly_ai_test_abilities'][Abilities::FACT]['execute_callback'](
            ['topic' => 'birth']
        );
        $timeline = $GLOBALS['hello_dolly_ai_test_abilities'][Abilities::TIMELINE]['execute_callback'](
            ['decade' => '1970s']
        );
        $song = $GLOBALS['hello_dolly_ai_test_abilities'][Abilities::SONG]['execute_callback'](
            ['title' => 'jolene']
        );

        self::assertStringContainsString('Locust Ridge', $fact['answer']);
        self::assertNotEmpty($timeline['events']);
        self::assertSame('Jolene', $song['title']);
        self::assertStringStartsWith('https://', $fact['source']);
        self::assertStringStartsWith('https://', $timeline['source']);
        self::assertStringStartsWith('https://', $song['source']);
    }

    public function testAbilityPermissionsRequireReadCapability(): void
    {
        self::assertTrue(Abilities::canRead());

        $GLOBALS['hello_dolly_ai_test_user_can_read'] = false;

        self::assertFalse(Abilities::canRead());
    }
}
