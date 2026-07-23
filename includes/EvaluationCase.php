<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Evaluator\EvaluatorInterface;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Task\CallableTask;
use Automattic\AiEvals\Task\ModelCallableTask;
use Automattic\AiEvals\Task\TaskInterface;

final class EvaluationCase {

	private string $id;
	private string $label;

	/** @var mixed */
	private $input = null;

	/** @var mixed */
	private $expected = null;

	private ?TaskInterface $task = null;

	/** @var list<\Automattic\AiEvals\Evaluator\EvaluatorInterface> */
	private array $evaluators = array();

	/** @var list<string> */
	private array $tags = array();

	/** @var array<string, mixed> */
	private array $metadata = array();

	private function __construct( string $id, string $label ) {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9._-]*$/', $id ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid case ID "%s". Use lowercase letters, numbers, dots, underscores, or hyphens.',
					esc_html( $id )
				)
			);
		}

		$this->id    = $id;
		$this->label = $label;
	}

	public static function make( string $id, string $label = '' ): self {
		return new self( $id, '' !== $label ? $label : $id );
	}

	/** @param mixed $input */
	public function input( $input ): self {
		$this->input = $input;

		return $this;
	}

	/** @param mixed $expected */
	public function expected( $expected ): self {
		$this->expected = $expected;

		return $this;
	}

	/** @param \Automattic\AiEvals\Task\TaskInterface|callable $task */
	public function task( $task ): self {
		if ( $task instanceof TaskInterface ) {
			$this->task = $task;

			return $this;
		}

		if ( ! is_callable( $task ) ) {
			throw new InvalidArgumentException( 'An eval task must implement TaskInterface or be callable.' );
		}

		$this->task = new CallableTask( $task );

		return $this;
	}

	/**
	 * Registers a callable task that promises to honor the run's exact model target.
	 *
	 * The callable receives the same ($input, EvaluationContext $context) arguments
	 * as a regular callable task and should pass $context->get_model_target() to the
	 * AI client code it exercises.
	 */
	public function model_task( callable $task, string $type = 'callable:model' ): self {
		$this->task = new ModelCallableTask( $task, $type );

		return $this;
	}

	public function evaluate_with( EvaluatorInterface $evaluator ): self {
		$this->evaluators[] = $evaluator;

		return $this;
	}

	public function tag( string ...$tags ): self {
		foreach ( $tags as $tag ) {
			$tag = strtolower( trim( $tag ) );
			if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9._-]*$/', $tag ) ) {
				throw new InvalidArgumentException( sprintf( 'Invalid eval tag "%s".', esc_html( $tag ) ) );
			}
			if ( in_array( $tag, $this->tags, true ) ) {
				continue;
			}

			$this->tags[] = $tag;
		}

		return $this;
	}

	/** @param array<string, mixed> $metadata */
	public function metadata( array $metadata ): self {
		$this->metadata = $metadata;

		return $this;
	}

	public function get_id(): string {
		return $this->id;
	}

	public function get_label(): string {
		return $this->label;
	}

	/** @return mixed */
	public function get_input() {
		return $this->input;
	}

	/** @return mixed */
	public function get_expected() {
		return $this->expected;
	}

	public function get_task(): TaskInterface {
		if ( null === $this->task ) {
			throw new InvalidArgumentException( sprintf( 'Eval case "%s" has no task.', esc_html( $this->id ) ) );
		}

		return $this->task;
	}

	/** @return list<\Automattic\AiEvals\Evaluator\EvaluatorInterface> */
	public function get_evaluators(): array {
		return $this->evaluators;
	}

	/** @return list<string> */
	public function get_tags(): array {
		return $this->tags;
	}

	/** @return array<string, mixed> */
	public function get_metadata(): array {
		return $this->metadata;
	}
}
