<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class RunReport implements JsonSerializable {

	private string $id;
	private string $started_at;
	private float $duration_milliseconds;

	/** @var list<\Automattic\AiEvals\CaseResult> */
	private array $results;
	private RunConfiguration $configuration;

	/** @param list<\Automattic\AiEvals\CaseResult> $results */
	public function __construct(
		string $id,
		string $started_at,
		float $duration_milliseconds,
		array $results,
		?RunConfiguration $configuration = null
	) {
		$this->id                    = $id;
		$this->started_at            = $started_at;
		$this->duration_milliseconds = $duration_milliseconds;
		$this->results               = $results;
		$this->configuration         = $configuration ?? new RunConfiguration();
	}

	public function get_id(): string {
		return $this->id;
	}

	public function getStartedAt(): string {
		return $this->started_at;
	}

	public function getDurationMilliseconds(): float {
		return $this->duration_milliseconds;
	}

	/** @return list<\Automattic\AiEvals\CaseResult> */
	public function getResults(): array {
		return $this->results;
	}

	public function getTotal(): int {
		return count( $this->results );
	}

	public function getPassed(): int {
		return count( array_filter( $this->results, static fn( CaseResult $result ): bool => $result->hasPassed() ) );
	}

	public function getFailed(): int {
		return $this->getTotal() - $this->getPassed();
	}

	public function getScore(): float {
		if ( array() === $this->results ) {
			return 0.0;
		}

		return array_sum(
			array_map(
				static fn( CaseResult $result ): float => $result->getScore(),
				$this->results
			)
		) / count( $this->results );
	}

	public function hasPassed(): bool {
		return $this->getTotal() > 0 && 0 === $this->getFailed();
	}

	public function getConfiguration(): RunConfiguration {
		return $this->configuration;
	}

	/** @return array<string, mixed> */
	public function getDiagnostics(): array {
		return $this->diagnosticsFor( $this->results );
	}

	/** @return list<array<string, mixed>> */
	public function getVariants(): array {
		$groups = array();
		foreach ( $this->results as $result ) {
			$target = $result->get_model_target();
			if ( null === $target && array() !== $this->configuration->getModelTargets() ) {
				continue;
			}
			$id = null !== $target ? $target->get_id() : 'default';
			if ( ! isset( $groups[ $id ] ) ) {
				$groups[ $id ] = array(
					'id'           => $id,
					'model_target' => $target,
					'results'      => array(),
				);
			}
			$groups[ $id ]['results'][] = $result;
		}

		$variants = array();
		foreach ( $groups as $group ) {
			/** @var list<\Automattic\AiEvals\CaseResult> $results */
			$results = $group['results'];
			$total   = count( $results );
			$passed  = count(
				array_filter(
					$results,
					static fn( CaseResult $result ): bool => $result->hasPassed()
				)
			);
			$score   = 0 === $total ? 0.0 : array_sum(
				array_map(
					static fn( CaseResult $result ): float => $result->getScore(),
					$results
				)
			) / $total;

			$variants[] = array(
				'id'           => $group['id'],
				'model_target' => $group['model_target'],
				'total'        => $total,
				'passed'       => $passed,
				'failed'       => $total - $passed,
				'score'        => $score,
				'duration_ms'  => array_sum(
					array_map(
						static fn( CaseResult $result ): float => $result->getDurationMilliseconds(),
						$results
					)
				),
				'diagnostics'  => $this->diagnosticsFor( $results ),
			);
		}

		return $variants;
	}

	/**
	 * @param list<\Automattic\AiEvals\CaseResult> $results
	 * @return array<string, mixed>
	 */
	private function diagnosticsFor( array $results ): array {
		$tokens            = array(
			'input'    => 0,
			'output'   => 0,
			'total'    => 0,
			'thinking' => 0,
		);
		$task_tokens       = array(
			'input'    => 0,
			'output'   => 0,
			'total'    => 0,
			'thinking' => 0,
		);
		$evaluator_tokens  = array(
			'input'    => 0,
			'output'   => 0,
			'total'    => 0,
			'thinking' => 0,
		);
		$costs             = array();
		$task_costs        = array();
		$evaluator_costs   = array();
		$cost_observations = array(
			'total'     => 0,
			'task'      => 0,
			'evaluator' => 0,
		);
		$tools             = array();
		$providers         = array();
		$models            = array();

		foreach ( $results as $result ) {
			$metadata_sets = array();
			if ( null !== $result->getTaskResult() ) {
				$metadata_sets[] = array( $result->getTaskResult()->get_metadata(), 'task' );
			}
			foreach ( $result->getEvaluatorResults() as $evaluator_result ) {
				$metadata_sets[] = array( $evaluator_result->get_metadata(), 'evaluator' );
			}

			foreach ( $metadata_sets as $metadata_set ) {
				$metadata = $metadata_set[0];
				$kind     = $metadata_set[1];
				foreach ( $tokens as $name => $value ) {
					if ( ! isset( $metadata['tokens'][ $name ] ) || ! is_numeric( $metadata['tokens'][ $name ] ) ) {
						continue;
					}

					$token_count      = (int) $metadata['tokens'][ $name ];
					$tokens[ $name ] += $token_count;
					if ( 'task' === $kind ) {
						$task_tokens[ $name ] += $token_count;
					} else {
						$evaluator_tokens[ $name ] += $token_count;
					}
				}
				$cost = ReportedCost::from( $metadata['cost'] ?? null );
				if ( null !== $cost ) {
					$currency           = $cost->getCurrency();
					$amount             = $cost->getAmount();
					$costs[ $currency ] = ( $costs[ $currency ] ?? 0.0 ) + $amount;
					++$cost_observations['total'];
					if ( 'task' === $kind ) {
						$task_costs[ $currency ] = ( $task_costs[ $currency ] ?? 0.0 ) + $amount;
						++$cost_observations['task'];
					} else {
						$evaluator_costs[ $currency ] = ( $evaluator_costs[ $currency ] ?? 0.0 ) + $amount;
						++$cost_observations['evaluator'];
					}
				}
				$metadata_tools = isset( $metadata['tools'] ) && is_array( $metadata['tools'] )
					? $metadata['tools']
					: array();
				foreach ( $metadata_tools as $tool ) {
					if ( ! is_string( $tool ) || '' === $tool ) {
						continue;
					}

					$tools[ $tool ] = true;
				}
				if ( isset( $metadata['provider'] ) && is_string( $metadata['provider'] ) ) {
					$providers[ $metadata['provider'] ] = true;
				}
				if ( ! isset( $metadata['model'] ) || ! is_string( $metadata['model'] ) ) {
					continue;
				}

				$models[ $metadata['model'] ] = true;
			}
		}

		return array(
			'tokens'            => $tokens,
			'task_tokens'       => $task_tokens,
			'evaluator_tokens'  => $evaluator_tokens,
			'costs'             => $costs,
			'task_costs'        => $task_costs,
			'evaluator_costs'   => $evaluator_costs,
			'cost_observations' => $cost_observations,
			'tools'             => array_keys( $tools ),
			'providers'         => array_keys( $providers ),
			'models'            => array_keys( $models ),
		);
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return array(
			'id'            => $this->id,
			'started_at'    => $this->started_at,
			'duration_ms'   => $this->duration_milliseconds,
			'configuration' => $this->configuration,
			'summary'       => array(
				'total'       => $this->getTotal(),
				'passed'      => $this->getPassed(),
				'failed'      => $this->getFailed(),
				'score'       => $this->getScore(),
				'diagnostics' => $this->getDiagnostics(),
			),
			'variants'      => $this->getVariants(),
			'results'       => $this->results,
		);
	}
}
