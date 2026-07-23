<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

final class EvaluationContext {

	private Suite $suite;
	private EvaluationCase $evaluation_case;
	private int $iteration;
	private ?ModelTarget $model_target;
	private RunConfiguration $run_configuration;

	public function __construct(
		Suite $suite,
		EvaluationCase $evaluation_case,
		int $iteration,
		?ModelTarget $model_target = null,
		?RunConfiguration $run_configuration = null
	) {
		$this->suite             = $suite;
		$this->evaluation_case   = $evaluation_case;
		$this->iteration         = $iteration;
		$this->model_target      = $model_target;
		$this->run_configuration = $run_configuration ?? new RunConfiguration();
	}

	public function get_suite(): Suite {
		return $this->suite;
	}

	public function get_case(): EvaluationCase {
		return $this->evaluation_case;
	}

	public function get_iteration(): int {
		return $this->iteration;
	}

	public function get_qualified_case_id(): string {
		return $this->suite->get_id() . '/' . $this->evaluation_case->get_id();
	}

	public function get_model_target(): ?ModelTarget {
		return $this->model_target;
	}

	public function get_judge_model_target(): ?ModelTarget {
		return $this->run_configuration->get_judge_model_target();
	}

	public function get_run_configuration(): RunConfiguration {
		return $this->run_configuration;
	}
}
