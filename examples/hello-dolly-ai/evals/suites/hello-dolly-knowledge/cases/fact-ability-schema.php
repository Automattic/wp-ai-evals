<?php

declare(strict_types=1);

use HelloDollyAI\Abilities;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\JsonSchema;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Task\AbilityTask;

return EvaluationCase::make('fact-ability-schema', 'Fact ability schema and source')
    ->input(['topic' => 'philanthropy'])
    ->task(new AbilityTask(Abilities::FACT))
    ->evaluateWith(
        new JsonSchema([
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'answer' => ['type' => 'string'],
                'source' => ['type' => 'string', 'format' => 'uri'],
                'source_label' => ['type' => 'string'],
            ],
            'required' => ['topic', 'title', 'answer', 'source', 'source_label'],
            'additionalProperties' => false,
        ])
    )
    ->evaluateWith(
        new CallbackEvaluator(
            'Trusted HTTPS source',
            static fn(array $output): bool => 0 === strpos($output['source'] ?? '', 'https://')
        )
    )
    ->evaluateWith(new LatencyBelow(100))
    ->tag('offline', 'fast', 'ability', 'contract', 'smoke');
