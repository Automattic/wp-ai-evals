<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\TaskResult;

interface TaskInterface {

	/** @param mixed $input */
	public function run( $input, EvaluationContext $context ): TaskResult;

	public function get_type(): string;
}
