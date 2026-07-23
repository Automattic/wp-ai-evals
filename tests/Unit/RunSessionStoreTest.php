<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\RunReport;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Storage\HistoryStore;
use Automattic\AiEvals\Storage\RunSessionStore;
use Automattic\AiEvals\Suite;
use PHPUnit\Framework\TestCase;

final class RunSessionStoreTest extends TestCase {

	protected function setUp(): void {
		wp_ai_evals_test_reset_state();
	}

	public function testAdvancesOneCasePerCallThenCompletesAndPersistsAReport(): void {
		$registry = ( new Registry() )->register(
			Suite::make( 'suite' )
				->add_case(
					EvaluationCase::make( 'first' )
						->task( static fn(): string => 'ok' )
						->expected( 'ok' )
						->evaluate_with( new ExactMatch() )
				)
				->add_case(
					EvaluationCase::make( 'second' )
						->task( static fn(): string => 'ok' )
						->expected( 'ok' )
						->evaluate_with( new ExactMatch() )
				)
		);

		$store   = new RunSessionStore();
		$session = $store->start( $registry, Selection::all(), new Runner() );
		$run_id   = (string) $session['id'];

		self::assertSame( 2, $session['total'] );
		self::assertSame( 0, $session['completed'] );
		self::assertSame( 2, $session['remaining'] );
		self::assertFalse( $session['complete'] );

		$first = $store->advance( $run_id, $registry, new Runner() );
		self::assertNull( $first['report'] );
		self::assertSame( 1, $first['session']['completed'] );
		self::assertSame( 1, $first['session']['remaining'] );
		self::assertFalse( $first['session']['complete'] );

		$second = $store->advance( $run_id, $registry, new Runner() );
		self::assertInstanceOf( RunReport::class, $second['report'] );
		self::assertSame( 2, $second['report']->getTotal() );
		self::assertSame( 2, $second['report']->getPassed() );
		self::assertTrue( $second['session']['complete'] );
		self::assertSame( 0, $second['session']['remaining'] );
	}

	public function testDropsTheSessionAndRecordsHistoryOnCompletion(): void {
		$registry = ( new Registry() )->register(
			Suite::make( 'suite' )->add_case(
				EvaluationCase::make( 'only' )
					->task( static fn(): string => 'ok' )
					->expected( 'ok' )
					->evaluate_with( new ExactMatch() )
			)
		);

		$store = new RunSessionStore();
		$run_id = (string) $store->start( $registry, Selection::all(), new Runner() )['id'];
		$store->advance( $run_id, $registry, new Runner() );

		self::assertNull( $store->status( $run_id ), 'The session transient is cleared once the run completes.' );

		$history = ( new HistoryStore() )->all();
		self::assertCount( 1, $history );
		self::assertSame( $run_id, $history[0]['id'] );
	}

	public function testStatusReturnsNullForAnUnknownRun(): void {
		self::assertNull( ( new RunSessionStore() )->status( 'does-not-exist' ) );
	}
}
