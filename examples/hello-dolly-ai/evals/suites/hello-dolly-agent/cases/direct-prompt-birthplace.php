<?php

declare(strict_types=1);

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Task\PromptTask;

return EvaluationCase::make('direct-prompt-birthplace', 'Direct AI Client contextual prompt baseline')
    ->input('Where was Dolly Parton born? Answer in one sentence.')
    ->task(
        PromptTask::text(
            static fn(string $input): string => $input,
            static fn($builder) => $builder
                ->using_system_instruction(
                    'Use this reference fact: Dolly Parton was born in Locust Ridge, Tennessee. '
                    . 'Answer accurately and concisely using only the reference fact.'
                )
        )
    )
    ->expected('Locust Ridge')
    ->evaluateWith(new ContainsText())
    ->evaluateWith(new LatencyBelow(30000))
    ->tag('live', 'prompt', 'baseline', 'smoke');
