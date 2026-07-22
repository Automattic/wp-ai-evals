<?php

declare(strict_types=1);

use HelloDollyAI\KnowledgeBase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Evaluator\MatchesRegex;

return (static function (): iterable {
    $facts = [
        'birth-golden' => [
            'topic' => 'birth',
            'expected' => 'Dolly Rebecca Parton was born January 19, 1946, in Locust Ridge, Tennessee. She was the fourth of twelve children.',
            'needle' => 'Locust Ridge',
            'pattern' => '/January 19, 1946/',
        ],
        'early-career-golden' => [
            'topic' => 'early-career',
            'expected' => 'She performed on local radio and television as a child, appeared at the Grand Ole Opry at thirteen, and moved to Nashville in 1964 immediately after high school.',
            'needle' => 'Grand Ole Opry',
            'pattern' => '/Nashville in 1964/',
        ],
    ];

    foreach ($facts as $id => $row) {
        yield EvaluationCase::make($id, ucwords(str_replace('-', ' ', $id)))
            ->input(['topic' => $row['topic']])
            ->task(static fn(array $input): string => KnowledgeBase::fact($input['topic'])['answer'])
            ->expected($row['expected'])
            ->evaluateWith(new ExactMatch())
            ->evaluateWith(new ContainsText($row['needle']))
            ->evaluateWith(new MatchesRegex($row['pattern']))
            ->evaluateWith(new LatencyBelow(50))
            ->tag('offline', 'fast', 'golden', 'dataset')
            ->metadata(['source' => 'curated-knowledge-base']);
    }
})();
