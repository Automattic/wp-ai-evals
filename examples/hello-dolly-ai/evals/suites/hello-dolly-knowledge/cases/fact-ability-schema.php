<?php

declare(strict_types=1);

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\JsonSchema;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Task\AbilityTask;
use HelloDollyAI\Abilities;

return EvaluationCase::make( 'fact-ability-schema', 'Fact ability schema and source' )
	->input( array( 'topic' => 'philanthropy' ) )
	->task( new AbilityTask( Abilities::FACT ) )
	->evaluate_with(
		new JsonSchema(
			array(
				'type'                 => 'object',
				'properties'           => array(
					'topic'        => array( 'type' => 'string' ),
					'title'        => array( 'type' => 'string' ),
					'answer'       => array( 'type' => 'string' ),
					'source'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'source_label' => array( 'type' => 'string' ),
				),
				'required'             => array( 'topic', 'title', 'answer', 'source', 'source_label' ),
				'additionalProperties' => false,
			)
		)
	)
	->evaluate_with(
		new CallbackEvaluator(
			'Trusted HTTPS source',
			static fn( array $output ): bool => 0 === strpos( $output['source'] ?? '', 'https://' )
		)
	)
	->evaluate_with( new LatencyBelow( 100 ) )
	->tag( 'offline', 'fast', 'ability', 'contract', 'smoke' );
