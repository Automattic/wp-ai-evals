<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

/**
 * Marker for tasks that promise to honor EvaluationContext::getModelTarget().
 */
interface ModelTargetAwareTaskInterface extends TaskInterface
{
}
