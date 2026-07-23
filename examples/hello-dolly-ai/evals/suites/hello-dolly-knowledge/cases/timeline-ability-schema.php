<?php

declare(strict_types=1);

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\CallbackEvaluator;
use Automattic\AiEvals\Evaluator\JsonSchema;
use Automattic\AiEvals\Task\AbilityTask;
use HelloDollyAI\Abilities;

return EvaluationCase::make( 'timeline-ability-schema', 'Timeline ability returns ordered events' )
	->input( array( 'decade' => '1970s' ) )
	->task( new AbilityTask( Abilities::TIMELINE ) )
	->evaluate_with(
		new JsonSchema(
			array(
				'type'       => 'object',
				'properties' => array(
					'decade'       => array( 'type' => 'string' ),
					'events'       => array(
						'type'     => 'array',
						'minItems' => 1,
						'items'    => array(
							'type'       => 'object',
							'properties' => array(
								'year'  => array( 'type' => 'integer' ),
								'event' => array( 'type' => 'string' ),
								'topic' => array( 'type' => 'string' ),
							),
							'required'   => array( 'year', 'event', 'topic' ),
						),
					),
					'source'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'source_label' => array( 'type' => 'string' ),
				),
				'required'   => array( 'decade', 'events', 'source', 'source_label' ),
			)
		)
	)
	->evaluate_with(
		new CallbackEvaluator(
			'Chronological decade filter',
			static function ( array $output ): bool {
				$years = array_column( $output['events'] ?? array(), 'year' );

				return array() !== $years
					&& array_values( array_unique( $years ) ) === $years
					&& 1970 <= min( $years )
					&& 1980 > max( $years );
			}
		)
	)
	->tag( 'offline', 'ability', 'contract' );
