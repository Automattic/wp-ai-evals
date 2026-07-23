<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Evaluator\MatchesRegex;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\TaskResult;

final class EvaluatorTest extends TestCase {

	public function testBuiltInEvaluatorsProduceNormalizedScores(): void {
		$context = new EvaluationContext( Suite::make( 'suite' ), EvaluationCase::make( 'case' ), 1 );
		$result  = TaskResult::fromOutput( 'Hello WordPress 7.0' )->withMetric( 'duration_ms', 12.5 );

		self::assertTrue( ( new ContainsText() )->evaluate( $result, 'WordPress', $context )->hasPassed() );
		self::assertTrue( ( new MatchesRegex( '/WordPress\s+7\.0/' ) )->evaluate( $result, null, $context )->hasPassed() );
		self::assertTrue( ( new LatencyBelow( 20 ) )->evaluate( $result, null, $context )->hasPassed() );
		self::assertFalse( ( new ExactMatch() )->evaluate( $result, 'other', $context )->hasPassed() );
	}

	public function testCallbackEvaluatorAcceptsNumericScores(): void {
		$context   = new EvaluationContext( Suite::make( 'suite' ), EvaluationCase::make( 'case' ), 1 );
		$evaluator = new CallbackEvaluator( 'Similarity', static fn(): float => 0.8 );

		$result = $evaluator->evaluate( TaskResult::fromOutput( 'output' ), null, $context );

		self::assertTrue( $result->hasPassed() );
		self::assertSame( 0.8, $result->getScore() );
	}
}
