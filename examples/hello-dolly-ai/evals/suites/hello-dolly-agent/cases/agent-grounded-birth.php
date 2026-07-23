<?php

declare(strict_types=1);

use HelloDollyAI\Abilities;
use HelloDollyAI\Agent;
use HelloDollyAI\KnowledgeBase;
use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\EvaluationContext;
use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\LlmJudge;
use Automattic\AiEvals\Evaluator\Rubric;
use Automattic\AiEvals\TaskResult;

return EvaluationCase::make('agent-grounded-birth', 'Agent grounds a biographical answer')
    ->input('Where and when was Dolly born, and how many siblings did she grow up with?')
    ->modelTask(
        static function (string $input, EvaluationContext $context) {
            $response = (new Agent())->respond($input, [], $context->getModelTarget());
            if (is_wp_error($response)) {
                return $response;
            }
            $metadata = [
                'provider' => $response['provider'],
                'model' => $response['model'],
                'tokens' => $response['tokens'],
                'tools' => $response['tools'],
                'sources' => $response['sources'],
                'reference_facts' => [
                    KnowledgeBase::fact('birth')['answer'],
                    KnowledgeBase::fact('childhood')['answer'],
                ],
            ];
            if (isset($response['cost'])) {
                $metadata['cost'] = $response['cost'];
            }

            return TaskResult::fromOutput(
                $response['answer'],
                $metadata
            );
        },
        'agent'
    )
    ->expected('The answer states Locust Ridge, January 19 1946, and correctly explains that Dolly was one of twelve children, without unsupported details.')
    ->evaluateWith(new ContainsText('Locust Ridge'))
    ->evaluateWith(
        new CallbackEvaluator(
            'Used curated fact ability',
            static function ($output, $expected, TaskResult $result): bool {
                return in_array(Abilities::FACT, $result->getMetadata()['tools'] ?? [], true);
            }
        )
    )
    ->evaluateWith(
        new LlmJudge(
            Rubric::make()
                ->item(
                    'factuality',
                    'The answer is factually consistent with the expected answer.',
                    2.0,
                    0.8
                )
                ->item(
                    'grounding',
                    'Every factual detail is supported by actual_metadata.reference_facts.',
                    2.0,
                    0.8
                )
                ->item(
                    'relevance',
                    'The answer is concise and directly responsive without unrelated detail.'
                ),
            0.8
        )
    )
    ->tag('live', 'agent', 'grounding', 'model-graded', 'smoke');
