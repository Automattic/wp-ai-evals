<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Suite;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase {

	public function testRegistersMultipleSuitesAndCollectsTags(): void {
		$first  = Suite::make( 'first' )->add_case(
			EvaluationCase::make( 'one' )
				->task( static fn(): string => 'ok' )
				->expected( 'ok' )
				->evaluate_with( new ExactMatch() )
				->tag( 'smoke', 'fast' )
		);
		$second = Suite::make( 'second' )->add_case(
			EvaluationCase::make( 'two' )
				->task( static fn(): string => 'ok' )
				->expected( 'ok' )
				->evaluate_with( new ExactMatch() )
				->tag( 'regression', 'smoke' )
		);

		$registry = ( new Registry() )->register( $first )->register( $second );

		self::assertSame( array( 'first', 'second' ), array_keys( $registry->all() ) );
		self::assertSame( array( 'fast', 'regression', 'smoke' ), $registry->tags() );
	}

	public function testRejectsDuplicateSuiteIds(): void {
		$registry = ( new Registry() )->register( Suite::make( 'duplicate' ) );

		$this->expectException( InvalidArgumentException::class );
		$registry->register( Suite::make( 'duplicate' ) );
	}

	public function testAddsParameterizedCasesFromAnIterable(): void {
		$cases = ( static function (): iterable {
			yield EvaluationCase::make( 'first' )->task( static fn(): string => 'ok' );
			yield EvaluationCase::make( 'second' )->task( static fn(): string => 'ok' );
		} )();

		$suite = Suite::make( 'dataset' )->add_cases( $cases );

		self::assertSame( array( 'first', 'second' ), array_keys( $suite->get_cases() ) );
	}
}
