<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Evaluator\EvaluatorInterface;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Task\CallableTask;
use Automattic\AiEvals\Task\TaskInterface;

final class EvaluationCase
{
    private string $id;
    private string $label;

    /** @var mixed */
    private $input = null;

    /** @var mixed */
    private $expected = null;

    private ?TaskInterface $task = null;

    /** @var list<EvaluatorInterface> */
    private array $evaluators = [];

    /** @var list<string> */
    private array $tags = [];

    /** @var array<string, mixed> */
    private array $metadata = [];

    private function __construct(string $id, string $label)
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id)) {
            throw new InvalidArgumentException(
                sprintf('Invalid case ID "%s". Use lowercase letters, numbers, dots, underscores, or hyphens.', $id)
            );
        }

        $this->id = $id;
        $this->label = $label;
    }

    public static function make(string $id, string $label = ''): self
    {
        return new self($id, '' !== $label ? $label : $id);
    }

    /** @param mixed $input */
    public function input($input): self
    {
        $this->input = $input;

        return $this;
    }

    /** @param mixed $expected */
    public function expected($expected): self
    {
        $this->expected = $expected;

        return $this;
    }

    /** @param TaskInterface|callable $task */
    public function task($task): self
    {
        if ($task instanceof TaskInterface) {
            $this->task = $task;

            return $this;
        }

        if (!is_callable($task)) {
            throw new InvalidArgumentException('An eval task must implement TaskInterface or be callable.');
        }

        $this->task = new CallableTask($task);

        return $this;
    }

    public function evaluateWith(EvaluatorInterface $evaluator): self
    {
        $this->evaluators[] = $evaluator;

        return $this;
    }

    public function tag(string ...$tags): self
    {
        foreach ($tags as $tag) {
            $tag = strtolower(trim($tag));
            if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]*$/', $tag)) {
                throw new InvalidArgumentException(sprintf('Invalid eval tag "%s".', $tag));
            }
            if (!in_array($tag, $this->tags, true)) {
                $this->tags[] = $tag;
            }
        }

        return $this;
    }

    /** @param array<string, mixed> $metadata */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

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

    /** @return mixed */
    public function getInput()
    {
        return $this->input;
    }

    /** @return mixed */
    public function getExpected()
    {
        return $this->expected;
    }

    public function getTask(): TaskInterface
    {
        if (null === $this->task) {
            throw new InvalidArgumentException(sprintf('Eval case "%s" has no task.', $this->id));
        }

        return $this->task;
    }

    /** @return list<EvaluatorInterface> */
    public function getEvaluators(): array
    {
        return $this->evaluators;
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
