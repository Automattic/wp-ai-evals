<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;

final class Suite
{
    private string $id;
    private string $label;
    private string $description = '';

    /** @var array<string, EvaluationCase> */
    private array $cases = [];

    private function __construct(string $id, string $label)
    {
        self::assertValidId($id);
        $this->id = $id;
        $this->label = $label;
    }

    public static function make(string $id, string $label = ''): self
    {
        return new self($id, '' !== $label ? $label : $id);
    }

    public function describe(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function addCase(EvaluationCase $case): self
    {
        $id = $case->getId();

        if (isset($this->cases[$id])) {
            throw new InvalidArgumentException(
                sprintf('The eval case "%s" is already registered in suite "%s".', $id, $this->id)
            );
        }

        $this->cases[$id] = $case;

        return $this;
    }

    /** @param iterable<EvaluationCase> $cases */
    public function addCases(iterable $cases): self
    {
        foreach ($cases as $case) {
            if (!$case instanceof EvaluationCase) {
                throw new InvalidArgumentException('Suite::addCases() accepts only EvaluationCase instances.');
            }
            $this->addCase($case);
        }

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return array<string, EvaluationCase> */
    public function getCases(): array
    {
        return $this->cases;
    }

    private static function assertValidId(string $id): void
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id)) {
            throw new InvalidArgumentException(
                sprintf('Invalid suite ID "%s". Use lowercase letters, numbers, dots, underscores, or hyphens.', $id)
            );
        }
    }
}
