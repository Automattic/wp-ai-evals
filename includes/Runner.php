<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Task\ModelTargetAwareTaskInterface;
use Throwable;

final class Runner {

	public function run(
		Registry $registry,
		?Selection $selection = null,
		?RunConfiguration $configuration = null
	): RunReport {
		$selection     = $selection ?? Selection::all();
		$configuration = $configuration ?? new RunConfiguration();
		$started_at    = gmdate( 'c' );
		$started       = microtime( true );
		$results       = array();
		$repetitions   = $selection->get_repetitions();

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wp_ai_evals_before_run', $registry, $selection, $configuration );
		}

		foreach ( $registry->all() as $suite ) {
			foreach ( $suite->get_cases() as $evaluation_case ) {
				if ( ! $selection->matches( $suite, $evaluation_case ) ) {
					continue;
				}

				foreach ( $this->targets_for_case( $evaluation_case, $configuration ) as $model_target ) {
					for ( $iteration = 1; $iteration <= $repetitions; ++$iteration ) {
						$results[] = $this->run_case(
							$suite,
							$evaluation_case,
							$iteration,
							$model_target,
							$configuration
						);
					}
				}
			}
		}

		$report = new RunReport(
			$this->make_run_id(),
			$started_at,
			( microtime( true ) - $started ) * 1000,
			$results,
			$configuration
		);

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wp_ai_evals_after_run', $report );
		}

		return $report;
	}

	public function run_case(
		Suite $suite,
		EvaluationCase $evaluation_case,
		int $iteration = 1,
		?ModelTarget $model_target = null,
		?RunConfiguration $configuration = null
	): CaseResult {
		$configuration = $configuration ?? new RunConfiguration(
			null !== $model_target ? array( $model_target ) : array()
		);
		$context       = new EvaluationContext( $suite, $evaluation_case, $iteration, $model_target, $configuration );
		$started       = microtime( true );
		$task_type     = 'unconfigured';

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wp_ai_evals_before_case', $context );
		}

		try {
			$task      = $evaluation_case->get_task();
			$task_type = $task->get_type();
			$result    = $task->run( $evaluation_case->get_input(), $context );
			if ( null !== $model_target && $task instanceof ModelTargetAwareTaskInterface ) {
				if ( ! $model_target->matchesMetadata( $result->get_metadata() ) ) {
					$actual_provider = isset( $result->get_metadata()['provider'] )
						? (string) $result->get_metadata()['provider']
						: 'unknown';
					$actual_model    = isset( $result->get_metadata()['model'] )
						? (string) $result->get_metadata()['model']
						: 'unknown';
					throw new Exception\RuntimeException(
						sprintf(
							'Model-aware task did not use exact target "%s"; resolved "%s:%s".',
							$model_target->get_id(),
							$actual_provider,
							$actual_model
						)
					);
				}

				$result = $result->withMetadata(
					array(
						'requested_model_target' => $model_target->get_id(),
						'model_target_match'     => true,
					)
				);
			}
			$task_duration     = ( microtime( true ) - $started ) * 1000;
			$result            = $result->withMetric( 'duration_ms', $task_duration );
			$evaluator_results = array();

			if ( array() === $evaluation_case->get_evaluators() ) {
				throw new Exception\RuntimeException(
					sprintf( 'Eval case "%s" has no evaluators.', $context->get_qualified_case_id() )
				);
			}

			foreach ( $evaluation_case->get_evaluators() as $evaluator ) {
				$evaluator_started = microtime( true );
				try {
					$evaluation = $evaluator->evaluate( $result, $evaluation_case->get_expected(), $context );
				} catch ( Throwable $error ) {
					$evaluation = EvaluatorResult::fail(
						$evaluator->get_name(),
						$evaluator->get_type(),
						$error->getMessage()
					);
				}
				$evaluator_results[] = $evaluation->withMetric(
					'duration_ms',
					( microtime( true ) - $evaluator_started ) * 1000
				);
			}

			$passed = ! in_array(
				false,
				array_map(
					static fn( EvaluatorResult $evaluation ): bool => $evaluation->hasPassed(),
					$evaluator_results
				),
				true
			);
			$score  = array_sum(
				array_map(
					static fn( EvaluatorResult $evaluation ): float => $evaluation->getScore(),
					$evaluator_results
				)
			) / count( $evaluator_results );

			$case_result = new CaseResult(
				$suite->get_id(),
				$evaluation_case->get_id(),
				$evaluation_case->get_label(),
				$task_type,
				$iteration,
				$passed ? 'passed' : 'failed',
				$score,
				( microtime( true ) - $started ) * 1000,
				$result,
				$evaluator_results,
				'',
				$evaluation_case->get_input(),
				$evaluation_case->get_expected(),
				$evaluation_case->get_tags(),
				$evaluation_case->get_metadata(),
				$model_target
			);
		} catch ( Throwable $error ) {
			$case_result = new CaseResult(
				$suite->get_id(),
				$evaluation_case->get_id(),
				$evaluation_case->get_label(),
				$task_type,
				$iteration,
				'error',
				0.0,
				( microtime( true ) - $started ) * 1000,
				null,
				array(),
				$error->getMessage(),
				$evaluation_case->get_input(),
				$evaluation_case->get_expected(),
				$evaluation_case->get_tags(),
				$evaluation_case->get_metadata(),
				$model_target
			);
		}

		if ( function_exists( 'do_action' ) ) {
			do_action( 'wp_ai_evals_after_case', $case_result, $context );
		}

		return $case_result;
	}

	public function make_run_id(): string {
		try {
			return gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 4 ) );
		} catch ( Throwable $error ) {
			return uniqid( gmdate( 'Ymd-His' ) . '-', true );
		}
	}

	/**
	 * Model-independent tasks execute once instead of being duplicated in every model variant.
	 *
	 * @return list<\Automattic\AiEvals\ModelTarget|null>
	 */
	public function targets_for_case( EvaluationCase $evaluation_case, RunConfiguration $configuration ): array {
		try {
			if ( $evaluation_case->get_task() instanceof ModelTargetAwareTaskInterface ) {
				return $configuration->getExecutionTargets();
			}
		} catch ( Throwable $error ) {
			// Invalid case configuration is captured as a normal case error during execution.
			unset( $error );
		}

		return array( null );
	}
}
