<?php

declare(strict_types=1);

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Evaluator\ContainsText;
use Automattic\AiEvals\Evaluator\ExactMatch;
use Automattic\AiEvals\Evaluator\LatencyBelow;
use Automattic\AiEvals\Evaluator\MatchesRegex;
use HelloDollyAI\KnowledgeBase;

return ( static function (): iterable {
	$facts = array(
		'birth-golden'        => array(
			'topic'    => 'birth',
			'expected' => 'Dolly Rebecca Parton was born January 19, 1946, in Locust Ridge, Tennessee. She was the fourth of twelve children.',
			'needle'   => 'Locust Ridge',
			'pattern'  => '/January 19, 1946/',
		),
		'early-career-golden' => array(
			'topic'    => 'early-career',
			'expected' => 'She performed on local radio and television as a child, appeared at the Grand Ole Opry at thirteen, and moved to Nashville in 1964 immediately after high school.',
			'needle'   => 'Grand Ole Opry',
			'pattern'  => '/Nashville in 1964/',
		),
	);

	foreach ( $facts as $id => $row ) {
		yield EvaluationCase::make( $id, ucwords( str_replace( '-', ' ', $id ) ) )
			->input( array( 'topic' => $row['topic'] ) )
			->task( static fn( array $input ): string => KnowledgeBase::fact( $input['topic'] )['answer'] )
			->expected( $row['expected'] )
			->evaluate_with( new ExactMatch() )
			->evaluate_with( new ContainsText( $row['needle'] ) )
			->evaluate_with( new MatchesRegex( $row['pattern'] ) )
			->evaluate_with( new LatencyBelow( 50 ) )
			->tag( 'offline', 'fast', 'golden', 'dataset' )
			->metadata( array( 'source' => 'curated-knowledge-base' ) );
	}
} )();
