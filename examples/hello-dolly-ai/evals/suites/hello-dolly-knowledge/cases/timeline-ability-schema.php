<?php

declare(strict_types=1);

use HelloDollyAI\Abilities;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\JsonSchema;
use Automattic\AiEvals\Task\AbilityTask;

return EvaluationCase::make('timeline-ability-schema', 'Timeline ability returns ordered events')
    ->input(['decade' => '1970s'])
    ->task(new AbilityTask(Abilities::TIMELINE))
    ->evaluateWith(
        new JsonSchema([
            'type' => 'object',
            'properties' => [
                'decade' => ['type' => 'string'],
                'events' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'year' => ['type' => 'integer'],
                            'event' => ['type' => 'string'],
                            'topic' => ['type' => 'string'],
                        ],
                        'required' => ['year', 'event', 'topic'],
                    ],
                ],
                'source' => ['type' => 'string', 'format' => 'uri'],
                'source_label' => ['type' => 'string'],
            ],
            'required' => ['decade', 'events', 'source', 'source_label'],
        ])
    )
    ->evaluateWith(
        new CallbackEvaluator(
            'Chronological decade filter',
            static function (array $output): bool {
                $years = array_column($output['events'] ?? [], 'year');

                return [] !== $years
                    && $years === array_values(array_unique($years))
                    && min($years) >= 1970
                    && max($years) < 1980;
            }
        )
    )
    ->tag('offline', 'ability', 'contract');
