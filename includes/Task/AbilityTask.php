<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Task;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Exception\RuntimeException;
use Automattic\AiEvals\TaskResult;

final class AbilityTask implements TaskInterface
{
    private string $abilityName;

    public function __construct(string $abilityName)
    {
        $this->abilityName = $abilityName;
    }

    /** {@inheritDoc} */
    public function run($input, EvaluationContext $context): TaskResult
    {
        if (!function_exists('wp_get_ability')) {
            throw new RuntimeException('The WordPress Abilities API is unavailable.');
        }

        $ability = wp_get_ability($this->abilityName);
        if (null === $ability) {
            throw new RuntimeException(sprintf('The WordPress ability "%s" is not registered.', $this->abilityName));
        }

        $result = $ability->execute($input);
        if (function_exists('is_wp_error') && is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }

        return TaskResult::fromOutput($result, ['ability' => $this->abilityName]);
    }

    public function getType(): string
    {
        return 'ability';
    }
}
