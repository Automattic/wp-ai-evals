<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Evaluator\JsonSchema;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\TaskResult;

final class JsonSchemaTest extends TestCase
{
    /** @var array<string, mixed> */
    private const SCHEMA = [
        'type' => 'object',
        'required' => ['name'],
        'properties' => [
            'name' => ['type' => 'string'],
        ],
    ];

    protected function setUp(): void
    {
        wp_ai_evals_test_reset_state();
    }

    public function testPassesStructuredOutputThatMatchesTheSchema(): void
    {
        $evaluator = new JsonSchema(self::SCHEMA);

        $result = $evaluator->evaluate(
            TaskResult::fromOutput(['name' => 'Dolly']),
            null,
            $this->context()
        );

        self::assertTrue($result->hasPassed());
    }

    public function testDecodesAndPassesAJsonStringOutput(): void
    {
        $evaluator = new JsonSchema(self::SCHEMA);

        $result = $evaluator->evaluate(
            TaskResult::fromOutput('{"name":"Dolly"}'),
            null,
            $this->context()
        );

        self::assertTrue($result->hasPassed());
    }

    public function testFailsWhenStringOutputIsNotValidJson(): void
    {
        $evaluator = new JsonSchema(self::SCHEMA);

        $result = $evaluator->evaluate(
            TaskResult::fromOutput('{ not json'),
            null,
            $this->context()
        );

        self::assertFalse($result->hasPassed());
        self::assertStringContainsString('valid JSON', $result->getReason());
    }

    public function testFailsAndSurfacesTheSchemaErrorWhenValidationFails(): void
    {
        $evaluator = new JsonSchema(self::SCHEMA);

        $result = $evaluator->evaluate(
            TaskResult::fromOutput(['age' => 42]),
            null,
            $this->context()
        );

        self::assertFalse($result->hasPassed());
        self::assertStringContainsString('required property name', $result->getReason());
    }

    private function context(): EvaluationContext
    {
        return new EvaluationContext(Suite::make('suite'), EvaluationCase::make('case'), 1);
    }
}
