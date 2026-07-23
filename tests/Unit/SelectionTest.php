<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Suite;
use PHPUnit\Framework\TestCase;

final class SelectionTest extends TestCase {

	public function testFiltersBySuiteQualifiedCaseAndAnyTag(): void {
		$suite = Suite::make( 'content' );
		$evaluation_case  = EvaluationCase::make( 'summary' )->tag( 'smoke', 'quality' );

		self::assertTrue( ( new Selection( array( 'content' ), array( 'content/summary' ), array( 'smoke' ) ) )->matches( $suite, $evaluation_case ) );
		self::assertTrue( ( new Selection( array(), array(), array( 'missing', 'quality' ) ) )->matches( $suite, $evaluation_case ) );
		self::assertFalse( ( new Selection( array( 'other' ) ) )->matches( $suite, $evaluation_case ) );
		self::assertFalse( ( new Selection( array(), array( 'other' ) ) )->matches( $suite, $evaluation_case ) );
		self::assertFalse( ( new Selection( array(), array(), array( 'slow' ) ) )->matches( $suite, $evaluation_case ) );
	}
}
