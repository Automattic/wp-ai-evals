<?php

declare(strict_types=1);

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\JsonSchema;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Evaluator\LlmJudge;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Suite;
use Automattic\AiEvals\Task\AbilityTask;
use Automattic\AiEvals\Task\PromptTask;

add_action(
	'wp_ai_evals_init',
	static function ( Registry $registry ): void {
		$registry->register(
			Suite::make( 'my-plugin', 'My Plugin AI' )
				->describe( 'Representative examples of each task and evaluator style.' )
				->add_case(
					EvaluationCase::make( 'plugin-api', 'Evaluate the real plugin API' )
						->input( array( 'post_id' => 123 ) )
						->task( static fn( array $input ) => my_plugin_generate_summary( $input['post_id'] ) )
						->expected( 'WordPress' )
						->evaluate_with( new ContainsText() )
						->evaluate_with( new LatencyBelow( 5000 ) )
						->tag( 'smoke', 'fast' )
				)
				->add_case(
					EvaluationCase::make( 'core-prompt', 'Experiment with a direct prompt' )
						->input( array( 'content' => 'WordPress is open source publishing software.' ) )
						->task(
							PromptTask::text(
								static fn( array $input ): string => 'Summarize in five words: ' . $input['content'],
								static fn( $builder ) => $builder->using_temperature( 0.1 )
							)
						)
						->evaluate_with( new LlmJudge( 'A faithful summary containing no more than five words.', 0.8 ) )
						->tag( 'quality', 'model-graded' )
				)
				->add_case(
					EvaluationCase::make( 'ability-contract', 'Exercise an Ability end to end' )
						->input( array( 'post_id' => 123 ) )
						->task( new AbilityTask( 'my-plugin/get-summary' ) )
						->evaluate_with(
							new JsonSchema(
								array(
									'type'       => 'object',
									'properties' => array(
										'summary' => array( 'type' => 'string' ),
									),
									'required'   => array( 'summary' ),
								)
							)
						)
						->evaluate_with(
							new CallbackEvaluator(
								'Non-empty summary',
								static fn( array $output ): bool => '' !== trim( $output['summary'] ?? '' )
							)
						)
						->tag( 'ability', 'contract' )
				)
		);
	}
);
