<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class CaseResult implements JsonSerializable {

	private string $suite_id;
	private string $case_id;
	private string $label;
	private string $task_type;
	private int $iteration;
	private string $status;
	private float $score;
	private float $duration_milliseconds;
	private ?TaskResult $task_result;

	/** @var list<\Automattic\AiEvals\EvaluatorResult> */
	private array $evaluator_results;

	private string $error;

	/** @var mixed */
	private $input;

	/** @var mixed */
	private $expected;

	/** @var list<string> */
	private array $tags;

	/** @var array<string, mixed> */
	private array $case_metadata;
	private ?ModelTarget $model_target;

	/**
	 * @param list<\Automattic\AiEvals\EvaluatorResult> $evaluator_results
	 * @param mixed                 $input
	 * @param mixed                 $expected
	 * @param list<string>          $tags
	 * @param array<string, mixed>  $case_metadata
	 */
	public function __construct(
		string $suite_id,
		string $case_id,
		string $label,
		string $task_type,
		int $iteration,
		string $status,
		float $score,
		float $duration_milliseconds,
		?TaskResult $task_result,
		array $evaluator_results,
		string $error = '',
		$input = null,
		$expected = null,
		array $tags = array(),
		array $case_metadata = array(),
		?ModelTarget $model_target = null
	) {
		$this->suite_id              = $suite_id;
		$this->case_id               = $case_id;
		$this->label                 = $label;
		$this->task_type             = $task_type;
		$this->iteration             = $iteration;
		$this->status                = $status;
		$this->score                 = $score;
		$this->duration_milliseconds = $duration_milliseconds;
		$this->task_result           = $task_result;
		$this->evaluator_results     = $evaluator_results;
		$this->error                 = $error;
		$this->input                 = $input;
		$this->expected              = $expected;
		$this->tags                  = $tags;
		$this->case_metadata         = $case_metadata;
		$this->model_target          = $model_target;
	}

	public function getQualifiedId(): string {
		return $this->suite_id . '/' . $this->case_id;
	}

	public function getSuiteId(): string {
		return $this->suite_id;
	}

	public function getCaseId(): string {
		return $this->case_id;
	}

	public function get_label(): string {
		return $this->label;
	}

	public function getTaskType(): string {
		return $this->task_type;
	}

	public function get_iteration(): int {
		return $this->iteration;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function getScore(): float {
		return $this->score;
	}

	public function getDurationMilliseconds(): float {
		return $this->duration_milliseconds;
	}

	public function getTaskResult(): ?TaskResult {
		return $this->task_result;
	}

	/** @return list<\Automattic\AiEvals\EvaluatorResult> */
	public function getEvaluatorResults(): array {
		return $this->evaluator_results;
	}

	public function getError(): string {
		return $this->error;
	}

	public function hasPassed(): bool {
		return 'passed' === $this->status;
	}

	public function get_model_target(): ?ModelTarget {
		return $this->model_target;
	}

	/** @return array<string, mixed> */
	public function jsonSerialize(): array {
		return array(
			'suite'        => $this->suite_id,
			'case'         => $this->case_id,
			'qualified_id' => $this->getQualifiedId(),
			'label'        => $this->label,
			'task_type'    => $this->task_type,
			'input'        => TaskResult::normalize( $this->input ),
			'expected'     => TaskResult::normalize( $this->expected ),
			'tags'         => $this->tags,
			'metadata'     => TaskResult::normalize( $this->case_metadata ),
			'iteration'    => $this->iteration,
			'model_target' => $this->model_target,
			'status'       => $this->status,
			'score'        => $this->score,
			'duration_ms'  => $this->duration_milliseconds,
			'task_result'  => $this->task_result,
			'evaluators'   => $this->evaluator_results,
			'error'        => $this->error,
		);
	}
}
