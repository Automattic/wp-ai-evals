<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\EvaluatorResult;
use Automattic\AiEvals\TaskResult;

final class JsonSchema implements EvaluatorInterface
{
    /** @var array<string, mixed> */
    private array $schema;

    /** @param array<string, mixed> $schema */
    public function __construct(array $schema)
    {
        $this->schema = $schema;
    }

    /** {@inheritDoc} */
    public function evaluate(TaskResult $result, $expected, EvaluationContext $context): EvaluatorResult
    {
        $value = $result->getOutput();
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                return EvaluatorResult::fail($this->getName(), $this->getType(), 'Output was not valid JSON.');
            }
            $value = $decoded;
        }

        if (!function_exists('rest_validate_value_from_schema')) {
            return EvaluatorResult::fail(
                $this->getName(),
                $this->getType(),
                'WordPress REST API schema validation is unavailable.'
            );
        }

        $valid = rest_validate_value_from_schema($value, $this->schema, 'output');
        if (function_exists('is_wp_error') && is_wp_error($valid)) {
            return EvaluatorResult::fail($this->getName(), $this->getType(), $valid->get_error_message());
        }

        return EvaluatorResult::pass($this->getName(), $this->getType(), 'Output matched the JSON schema.');
    }

    public function getName(): string
    {
        return 'JSON schema';
    }

    public function getType(): string
    {
        return 'deterministic';
    }
}
