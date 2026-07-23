<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use JsonSerializable;

final class TaskResult implements JsonSerializable
{
    /** @var mixed */
    private $output;

    /** @var array<string, mixed> */
    private array $metadata;

    /** @param mixed $output @param array<string, mixed> $metadata */
    private function __construct($output, array $metadata)
    {
        $this->output = $output;
        $this->metadata = $metadata;
    }

    /** @param mixed $output @param array<string, mixed> $metadata */
    public static function fromOutput($output, array $metadata = []): self
    {
        return new self($output, $metadata);
    }

    /** @return mixed */
    public function getOutput()
    {
        return $this->output;
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

    /** @param array<string, mixed> $metadata */
    public function withMetadata(array $metadata): self
    {
        $clone = clone $this;
        $clone->metadata = array_merge($clone->metadata, $metadata);

        return $clone;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'output' => self::normalize($this->output),
            'metadata' => self::normalize($this->metadata),
        ];
    }

    /** @param mixed $value @return mixed */
    public static function normalize($value)
    {
        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        if (is_object($value)) {
            return get_class($value);
        }
        if (is_array($value)) {
            return array_map([self::class, 'normalize'], $value);
        }

        return $value;
    }
}
