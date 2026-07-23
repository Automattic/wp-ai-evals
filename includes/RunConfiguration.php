<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class RunConfiguration implements JsonSerializable
{
    /** @var list<ModelTarget> */
    private array $modelTargets;
    private ?ModelTarget $judgeModelTarget;

    /**
     * @param list<ModelTarget> $modelTargets
     */
    public function __construct(array $modelTargets = [], ?ModelTarget $judgeModelTarget = null)
    {
        $unique = [];
        foreach ($modelTargets as $target) {
            if (!$target instanceof ModelTarget) {
                throw new Exception\InvalidArgumentException('Model targets must be ModelTarget instances.');
            }
            $unique[$target->getId()] = $target;
        }

        $this->modelTargets = array_values($unique);
        $this->judgeModelTarget = $judgeModelTarget;
    }

    /** @param list<string> $modelTargets */
    public static function fromStrings(array $modelTargets = [], string $judgeModelTarget = ''): self
    {
        $targets = array_map(
            static fn(string $target): ModelTarget => ModelTarget::fromString($target),
            array_values(array_filter(array_map('trim', $modelTargets)))
        );

        return new self(
            $targets,
            '' !== trim($judgeModelTarget) ? ModelTarget::fromString($judgeModelTarget) : null
        );
    }

    /** @param array<string, mixed> $configuration */
    public static function fromArray(array $configuration): self
    {
        $targets = [];
        if (isset($configuration['model_targets']) && is_array($configuration['model_targets'])) {
            foreach ($configuration['model_targets'] as $target) {
                if ($target instanceof ModelTarget) {
                    $targets[] = $target;
                } elseif (is_array($target)) {
                    $targets[] = ModelTarget::fromArray($target);
                }
            }
        }

        $judgeTarget = null;
        if (isset($configuration['judge_model_target'])) {
            if ($configuration['judge_model_target'] instanceof ModelTarget) {
                $judgeTarget = $configuration['judge_model_target'];
            } elseif (is_array($configuration['judge_model_target'])) {
                $judgeTarget = ModelTarget::fromArray($configuration['judge_model_target']);
            }
        }

        return new self($targets, $judgeTarget);
    }

    /** @return list<ModelTarget> */
    public function getModelTargets(): array
    {
        return $this->modelTargets;
    }

    /**
     * A run without targets executes once using the task's normal model selection.
     *
     * @return list<ModelTarget|null>
     */
    public function getExecutionTargets(): array
    {
        return [] === $this->modelTargets ? [null] : $this->modelTargets;
    }

    public function getJudgeModelTarget(): ?ModelTarget
    {
        return $this->judgeModelTarget;
    }

    public function isComparison(): bool
    {
        return count($this->modelTargets) > 1;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'model_targets' => array_map(
                static fn(ModelTarget $target): array => $target->jsonSerialize(),
                $this->modelTargets
            ),
            'judge_model_target' => null !== $this->judgeModelTarget
                ? $this->judgeModelTarget->jsonSerialize()
                : null,
            'is_comparison' => $this->isComparison(),
        ];
    }
}
