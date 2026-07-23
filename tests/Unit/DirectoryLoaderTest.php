<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Registry;
use PHPUnit\Framework\TestCase;

final class DirectoryLoaderTest extends TestCase {

	public function testLoadsStandaloneAndModularSuitesInStableOrder(): void {
		$registry = ( new Registry() )->load_directory( dirname( __DIR__ ) . '/Fixtures/evals' );

		self::assertSame( array( 'standalone', 'modular' ), array_keys( $registry->all() ) );
		self::assertSame( array( 'first', 'second', 'third' ), array_keys( $registry->get( 'modular' )->get_cases() ) );
	}

	public function testRejectsAnUnreadableDirectory(): void {
		$this->expectException( InvalidArgumentException::class );
		( new Registry() )->load_directory( __DIR__ . '/missing' );
	}
}
