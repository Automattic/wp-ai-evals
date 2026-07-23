<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Evaluator\JsonSchema;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Evaluator\LlmJudge;
use Automattic\AiEvals\Evaluator\MatchesRegex;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Task\ModelTargetAwareTaskInterface;
use PHPUnit\Framework\TestCase;

final class ExampleFeatureCoverageTest extends TestCase {

	public function testHelloDollyEvalsCoverEveryTaskAndEvaluatorStyle(): void {
		$example = dirname( __DIR__, 2 ) . '/examples/hello-dolly-ai';
		require_once $example . '/includes/KnowledgeBase.php';
		require_once $example . '/includes/Abilities.php';

		$registry         = ( new Registry() )->load_directory( $example . '/evals/suites' );
		$taskTypes        = array();
		$evaluatorClasses = array();
		$modelAwareCases  = 0;
		$caseCount        = 0;

		foreach ( $registry->all() as $suite ) {
			foreach ( $suite->get_cases() as $evaluation_case ) {
				++$caseCount;
				$task                          = $evaluation_case->get_task();
				$taskTypes[ $task->get_type() ] = true;
				if ( $task instanceof ModelTargetAwareTaskInterface ) {
					++$modelAwareCases;
				}
				foreach ( $evaluation_case->get_evaluators() as $evaluator ) {
					$evaluatorClasses[ get_class( $evaluator ) ] = true;
				}
			}
		}

		self::assertSame( array( 'hello-dolly-agent', 'hello-dolly-knowledge' ), array_keys( $registry->all() ) );
		self::assertSame( 9, $caseCount );
		$actualTaskTypes = array_keys( $taskTypes );
		sort( $actualTaskTypes );
		self::assertSame(
			array( 'ability', 'agent', 'callable', 'prompt:text' ),
			$actualTaskTypes
		);
		self::assertSame( 4, $modelAwareCases );

		$expectedEvaluators = array(
			CallbackEvaluator::class,
			ContainsText::class,
			ExactMatch::class,
			JsonSchema::class,
			LatencyBelow::class,
			LlmJudge::class,
			MatchesRegex::class,
		);
		foreach ( $expectedEvaluators as $evaluatorClass ) {
			self::assertArrayHasKey( $evaluatorClass, $evaluatorClasses );
		}

		self::assertContains( 'offline', $registry->tags() );
		self::assertContains( 'live', $registry->tags() );
		self::assertContains( 'model-graded', $registry->tags() );
		self::assertSame(
			'curated-knowledge-base',
			$registry->get( 'hello-dolly-knowledge' )->get_cases()['birth-golden']->get_metadata()['source']
		);
	}
}
