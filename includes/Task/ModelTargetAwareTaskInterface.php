<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

/**
 * Marker for tasks that promise to honor EvaluationContext::get_model_target().
 */
interface ModelTargetAwareTaskInterface extends TaskInterface {

}
