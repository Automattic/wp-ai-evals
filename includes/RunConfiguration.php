<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class RunConfiguration implements JsonSerializable {

	/** @var list<\Automattic\AiEvals\ModelTarget> */
	private array $model_targets;
	private ?ModelTarget $judge_model_target;

	/**
	 * @param list<\Automattic\AiEvals\ModelTarget> $model_targets
	 */
	public function __construct( array $model_targets = array(), ?ModelTarget $judge_model_target = null ) {
		$unique = array();
		foreach ( $model_targets as $target ) {
			if ( ! $target instanceof ModelTarget ) {
				throw new Exception\InvalidArgumentException( 'Model targets must be ModelTarget instances.' );
			}
			$unique[ $target->get_id() ] = $target;
		}

		$this->model_targets      = array_values( $unique );
		$this->judge_model_target = $judge_model_target;
	}

	/** @param list<string> $model_targets */
	public static function fromStrings( array $model_targets = array(), string $judge_model_target = '' ): self {
		$judge_model_target = trim( $judge_model_target );
		$targets            = array_map(
			static fn( string $target ): ModelTarget => ModelTarget::fromString( $target ),
			array_values( array_filter( array_map( 'trim', $model_targets ) ) )
		);

		return new self(
			$targets,
			'' !== $judge_model_target ? ModelTarget::fromString( $judge_model_target ) : null
		);
	}

	/** @param array<string, mixed> $configuration */
	public static function fromArray( array $configuration ): self {
		$targets = array();
		if ( isset( $configuration['model_targets'] ) && is_array( $configuration['model_targets'] ) ) {
			foreach ( $configuration['model_targets'] as $target ) {
				if ( $target instanceof ModelTarget ) {
					$targets[] = $target;
				} elseif ( is_array( $target ) ) {
					$targets[] = ModelTarget::fromArray( $target );
				}
			}
		}

		$judge_target = null;
		if ( isset( $configuration['judge_model_target'] ) ) {
			if ( $configuration['judge_model_target'] instanceof ModelTarget ) {
				$judge_target = $configuration['judge_model_target'];
			} elseif ( is_array( $configuration['judge_model_target'] ) ) {
				$judge_target = ModelTarget::fromArray( $configuration['judge_model_target'] );
			}
		}

		return new self( $targets, $judge_target );
	}

	/** @return list<\Automattic\AiEvals\ModelTarget> */
	public function getModelTargets(): array {
		return $this->model_targets;
	}

	/**
	 * A run without targets executes once using the task's normal model selection.
	 *
	 * @return list<\Automattic\AiEvals\ModelTarget|null>
	 */
	public function getExecutionTargets(): array {
		return array() === $this->model_targets ? array( null ) : $this->model_targets;
	}

	public function get_judge_model_target(): ?ModelTarget {
		return $this->judge_model_target;
	}

	public function isComparison(): bool {
		return count( $this->model_targets ) > 1;
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return array(
			'model_targets'      => array_map(
				static fn( ModelTarget $target ): array => $target->jsonSerialize(),
				$this->model_targets
			),
			'judge_model_target' => null !== $this->judge_model_target
				? $this->judge_model_target->jsonSerialize()
				: null,
			'is_comparison'      => $this->isComparison(),
		);
	}
}
