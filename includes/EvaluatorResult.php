<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class EvaluatorResult implements JsonSerializable
{
    private string $name;
    private string $type;
    private bool $passed;
    private float $score;
    private string $reason;

    /** @var array<string, mixed> */
    private array $metadata;

    /** @param array<string, mixed> $metadata */
    public function __construct(
        string $name,
        string $type,
        bool $passed,
        float $score,
        string $reason = '',
        array $metadata = []
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->passed = $passed;
        $this->score = max(0.0, min(1.0, $score));
        $this->reason = $reason;
        $this->metadata = $metadata;
    }

    public static function pass(string $name, string $type, string $reason = '', float $score = 1.0): self
    {
        return new self($name, $type, true, $score, $reason);
    }

    public static function fail(string $name, string $type, string $reason, float $score = 0.0): self
    {
        return new self($name, $type, false, $score, $reason);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function hasPassed(): bool
    {
        return $this->passed;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function withMetric(string $name, float $value): self
    {
        $clone = clone $this;
        $clone->metadata[$name] = $value;

        return $clone;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'passed' => $this->passed,
            'score' => $this->score,
            'reason' => $this->reason,
            'metadata' => TaskResult::normalize($this->metadata),
        ];
    }
}
