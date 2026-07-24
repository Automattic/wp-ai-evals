<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\Admin\Access;
use PHPUnit\Framework\TestCase;

final class AdminAccessTest extends TestCase {

	protected function setUp(): void {
		wp_ai_evals_test_reset_state();
	}

	public function testUsesManageOptionsByDefault(): void {
		self::assertSame( 'manage_options', Access::get_capability() );
		self::assertTrue( Access::can_run() );
		self::assertSame( 'manage_options', $GLOBALS['wp_ai_evals_test_last_capability'] );
	}

	public function testUsesTheFilteredCapability(): void {
		add_filter(
			'wp_ai_evals_capability',
			static fn(): string => 'edit_posts'
		);

		self::assertSame( 'edit_posts', Access::get_capability() );
		self::assertTrue( Access::can_run() );
		self::assertSame( 'edit_posts', $GLOBALS['wp_ai_evals_test_last_capability'] );
	}

	public function testDeniesAUserWithoutTheRequiredCapability(): void {
		$GLOBALS['wp_ai_evals_test_user_can'] = false;

		self::assertFalse( Access::can_run() );
		self::assertSame( 'manage_options', $GLOBALS['wp_ai_evals_test_last_capability'] );
	}

	public function testIgnoresAnEmptyFilteredCapability(): void {
		add_filter(
			'wp_ai_evals_capability',
			static fn(): string => ' '
		);

		self::assertSame( 'manage_options', Access::get_capability() );
	}
}
