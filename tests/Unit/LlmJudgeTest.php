<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Evaluator\LlmJudge;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\TaskResult;

final class LlmJudgeTest extends TestCase {

	protected function setUp(): void {
		wp_ai_evals_test_reset_state();
	}

	public function testAggregatesItemScoresAsAWeightedMean(): void {
		$judge = new LlmJudge(
			array(
				array(
					'id'            => 'accuracy',
					'criteria'      => 'Is it accurate?',
					'weight'        => 3.0,
					'minimum_score' => 0.5,
				),
				array(
					'id'       => 'tone',
					'criteria' => 'Is the tone warm?',
					'weight'   => 1.0,
				),
			)
		);
		$this->stageJudgeResponse(
			array(
				'accuracy' => array(
					'score'  => 1.0,
					'reason' => 'Matches the facts.',
				),
				'tone'     => array(
					'score'  => 0.0,
					'reason' => 'Too terse.',
				),
			)
		);

		$result = $judge->evaluate( TaskResult::fromOutput( 'candidate' ), 'expected', $this->context() );

		// (1.0 * 3 + 0.0 * 1) / 4 = 0.75, above the 0.7 default minimum.
		self::assertEqualsWithDelta( 0.75, $result->getScore(), 1.0e-9 );
		self::assertTrue( $result->hasPassed() );
		self::assertSame( 'weighted_mean', $result->get_metadata()['rubric']['aggregation'] );
		self::assertCount( 2, $result->get_metadata()['rubric']['items'] );
	}

	public function testFailsWhenAnItemDropsBelowItsMinimumEvenWithAHighMean(): void {
		$judge = new LlmJudge(
			array(
				array(
					'id'            => 'accuracy',
					'criteria'      => 'Is it accurate?',
					'weight'        => 1.0,
					'minimum_score' => 0.9,
				),
				array(
					'id'       => 'tone',
					'criteria' => 'Is the tone warm?',
					'weight'   => 4.0,
				),
			)
		);
		$this->stageJudgeResponse(
			array(
				'accuracy' => array(
					'score'  => 0.6,
					'reason' => 'A factual slip.',
				),
				'tone'     => array(
					'score'  => 1.0,
					'reason' => 'Very warm.',
				),
			)
		);

		$result = $judge->evaluate( TaskResult::fromOutput( 'candidate' ), 'expected', $this->context() );

		// (0.6 * 1 + 1.0 * 4) / 5 = 0.92, but accuracy is below its 0.9 minimum.
		self::assertEqualsWithDelta( 0.92, $result->getScore(), 1.0e-9 );
		self::assertFalse( $result->hasPassed() );
	}

	public function testClampsOutOfRangeItemScores(): void {
		$judge = new LlmJudge( 'Overall quality' );
		$this->stageJudgeResponse(
			array(
				'overall' => array(
					'score'  => 5,
					'reason' => 'The judge over-scored.',
				),
			)
		);

		$result = $judge->evaluate( TaskResult::fromOutput( 'candidate' ), 'expected', $this->context() );

		self::assertSame( 1.0, $result->getScore() );
		self::assertTrue( $result->hasPassed() );
	}

	public function testFailsGracefullyOnAMalformedJudgeResponse(): void {
		$judge                                      = new LlmJudge( 'Overall quality' );
		$GLOBALS['wp_ai_evals_test_judge_response'] = 'this is not JSON';

		$result = $judge->evaluate( TaskResult::fromOutput( 'candidate' ), 'expected', $this->context() );

		self::assertFalse( $result->hasPassed() );
		self::assertSame( 0.0, $result->getScore() );
		self::assertStringContainsString( 'invalid response', $result->getReason() );
	}

	public function testFailsWhenTheJudgeOmitsARubricItem(): void {
		$judge = new LlmJudge(
			array(
				array(
					'id'       => 'accuracy',
					'criteria' => 'Is it accurate?',
				),
				array(
					'id'       => 'tone',
					'criteria' => 'Is the tone warm?',
				),
			)
		);
		$this->stageJudgeResponse(
			array(
				'accuracy' => array(
					'score'  => 1.0,
					'reason' => 'Accurate.',
				),
			)
		);

		$result = $judge->evaluate( TaskResult::fromOutput( 'candidate' ), 'expected', $this->context() );

		self::assertFalse( $result->hasPassed() );
		self::assertStringContainsString( 'omitted rubric item "tone"', $result->getReason() );
	}

	/** @param array<string, array<string, mixed>> $items */
	private function stageJudgeResponse( array $items ): void {
		$GLOBALS['wp_ai_evals_test_judge_response'] = (string) json_encode( array( 'items' => $items ) );
	}

	private function context(): EvaluationContext {
		return new EvaluationContext(
			Suite::make( 'suite' ),
			EvaluationCase::make( 'case' )->input( 'the input' ),
			1
		);
	}
}
