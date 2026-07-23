<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\RunConfiguration;
use Automattic\AiEvals\Runner;
use Automattic\AiEvals\Selection;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\TaskResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RunnerTest extends TestCase {

	public function testRunsSelectedCasesAndAggregatesResults(): void {
		$suite = Suite::make( 'plugin' )
			->add_case(
				EvaluationCase::make( 'pass' )
					->input( 'WordPress' )
					->task(
						static fn( string $input ): TaskResult => TaskResult::fromOutput(
							"Hello {$input}",
							array(
								'tokens'   => array(
									'input'    => 4,
									'output'   => 2,
									'total'    => 6,
									'thinking' => 0,
								),
								'cost'     => array(
									'amount'   => 0.0015,
									'currency' => 'USD',
								),
								'tools'    => array( 'plugin/search' ),
								'provider' => 'test-provider',
								'model'    => 'test-model',
							)
						)
					)
					->expected( 'WordPress' )
					->evaluate_with( new ContainsText() )
					->tag( 'smoke' )
			)
			->add_case(
				EvaluationCase::make( 'fail' )
					->task( static fn(): string => 'actual' )
					->expected( 'expected' )
					->evaluate_with( new ExactMatch() )
					->tag( 'regression' )
			);

		$report = ( new Runner() )->run(
			( new Registry() )->register( $suite ),
			new Selection( array(), array(), array( 'smoke' ), 2 )
		);

		self::assertSame( 2, $report->getTotal() );
		self::assertSame( 2, $report->getPassed() );
		self::assertSame( 1.0, $report->getScore() );
		self::assertSame( 2, $report->getResults()[1]->get_iteration() );
		self::assertArrayHasKey( 'duration_ms', $report->getResults()[0]->getTaskResult()->get_metadata() );
		self::assertArrayHasKey(
			'duration_ms',
			$report->getResults()[0]->getEvaluatorResults()[0]->get_metadata()
		);
		self::assertGreaterThanOrEqual(
			$report->getResults()[0]->getTaskResult()->get_metadata()['duration_ms'],
			$report->getResults()[0]->getDurationMilliseconds()
		);
		self::assertSame( 12, $report->getDiagnostics()['tokens']['total'] );
		self::assertSame( 0.003, $report->getDiagnostics()['task_costs']['USD'] );
		self::assertSame( 2, $report->getDiagnostics()['cost_observations']['task'] );
		self::assertSame( array( 'plugin/search' ), $report->getDiagnostics()['tools'] );

		$serialized = $report->jsonSerialize();
		self::assertSame( 'WordPress', $serialized['results'][0]->jsonSerialize()['input'] );
		self::assertSame( array( 'smoke' ), $serialized['results'][0]->jsonSerialize()['tags'] );
	}

	public function testCapturesTaskErrorsWithoutStoppingTheRun(): void {
		$suite = Suite::make( 'plugin' )
			->add_case(
				EvaluationCase::make( 'broken' )
					->task(
						static function (): void {
							throw new RuntimeException( 'Task exploded.' );
						}
					)
					->evaluate_with( new ExactMatch() )
			)
			->add_case(
				EvaluationCase::make( 'healthy' )
					->task( static fn(): string => 'ok' )
					->expected( 'ok' )
					->evaluate_with( new ExactMatch() )
			);

		$report = ( new Runner() )->run( ( new Registry() )->register( $suite ) );

		self::assertSame( 2, $report->getTotal() );
		self::assertSame( 'error', $report->getResults()[0]->getStatus() );
		self::assertSame( 'Task exploded.', $report->getResults()[0]->getError() );
		self::assertSame( 'passed', $report->getResults()[1]->getStatus() );
	}

	public function testCapturesInvalidCaseConfigurationAsAnError(): void {
		$suite = Suite::make( 'plugin' )->add_case(
			EvaluationCase::make( 'missing-task' )->evaluate_with( new ExactMatch() )
		);

		$report = ( new Runner() )->run( ( new Registry() )->register( $suite ) );

		self::assertSame( 'error', $report->getResults()[0]->getStatus() );
		self::assertStringContainsString( 'has no task', $report->getResults()[0]->getError() );
	}

	public function testRunsTheSameSelectionAcrossExactModelTargets(): void {
		$evaluation_case = EvaluationCase::make( 'comparison' )
			->model_task(
				static function ( string $input, EvaluationContext $context ): TaskResult {
					$target = $context->get_model_target();

					return TaskResult::fromOutput(
						$input,
						array(
							'provider' => $target->getProviderId(),
							'model'    => $target->getModelId(),
							'tokens'   => array(
								'input'  => 2,
								'output' => 1,
								'total'  => 3,
							),
							'cost'     => array(
								'amount'   => 0.002,
								'currency' => 'USD',
							),
						)
					);
				}
			)
			->input( 'same input' )
			->expected( 'same input' )
			->evaluate_with( new ExactMatch() );

		$configuration = RunConfiguration::fromStrings(
			array(
				'openai:gpt-test',
				'anthropic:claude-test',
			)
		);
		$report        = ( new Runner() )->run(
			( new Registry() )->register( Suite::make( 'suite' )->add_case( $evaluation_case ) ),
			null,
			$configuration
		);

		self::assertSame( 2, $report->getTotal() );
		self::assertCount( 2, $report->getVariants() );
		self::assertSame( 'openai:gpt-test', $report->getVariants()[0]['id'] );
		self::assertSame( 3, $report->getVariants()[0]['diagnostics']['task_tokens']['total'] );
		self::assertSame( 0.002, $report->getVariants()[0]['diagnostics']['task_costs']['USD'] );
		self::assertSame(
			'anthropic:claude-test',
			$report->getResults()[1]->get_model_target()->get_id()
		);
		self::assertTrue(
			$report->getResults()[1]->getTaskResult()->get_metadata()['model_target_match']
		);
	}

	public function testErrorsWhenModelAwareTaskIgnoresExactTarget(): void {
		$evaluation_case = EvaluationCase::make( 'ignored-target' )
			->model_task(
				static fn(): TaskResult => TaskResult::fromOutput(
					'ok',
					array(
						'provider' => 'other',
						'model'    => 'fallback',
					)
				)
			)
			->expected( 'ok' )
			->evaluate_with( new ExactMatch() );

		$report = ( new Runner() )->run(
			( new Registry() )->register( Suite::make( 'suite' )->add_case( $evaluation_case ) ),
			null,
			RunConfiguration::fromStrings( array( 'openai:gpt-test' ) )
		);

		self::assertSame( 'error', $report->getResults()[0]->getStatus() );
		self::assertStringContainsString( 'did not use exact target', $report->getResults()[0]->getError() );
	}

	public function testRunsModelIndependentTaskOnlyOnceDuringComparison(): void {
		$evaluation_case = EvaluationCase::make( 'contract' )
			->task( static fn(): string => 'ok' )
			->expected( 'ok' )
			->evaluate_with( new ExactMatch() );

		$report = ( new Runner() )->run(
			( new Registry() )->register( Suite::make( 'suite' )->add_case( $evaluation_case ) ),
			null,
			RunConfiguration::fromStrings( array( 'openai:gpt-test', 'anthropic:claude-test' ) )
		);

		self::assertSame( 1, $report->getTotal() );
		self::assertSame( array(), $report->getVariants() );
		self::assertNull( $report->getResults()[0]->get_model_target() );
	}
}
