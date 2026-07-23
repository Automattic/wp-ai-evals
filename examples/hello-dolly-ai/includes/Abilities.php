<?php

declare(strict_types=1);

namespace HelloDollyAI;

final class Abilities {

	public const FACT     = 'hello-dolly/get-fact';
	public const TIMELINE = 'hello-dolly/get-timeline';
	public const SONG     = 'hello-dolly/get-song';

	/** @return list<string> */
	public static function names(): array {
		return array( self::FACT, self::TIMELINE, self::SONG );
	}

	public static function register_category(): void {
		wp_register_ability_category(
			'hello-dolly-knowledge',
			array(
				'label'       => __( 'Hello Dolly knowledge', 'hello-dolly-ai' ),
				'description' => __( 'Read-only, curated facts about Dolly Parton.', 'hello-dolly-ai' ),
			)
		);
	}

	public static function register(): void {
		wp_register_ability(
			self::FACT,
			array(
				'label'               => __( 'Get a Dolly Parton fact', 'hello-dolly-ai' ),
				'description'         => __( 'Get a sourced fact about Dolly Parton. Use this for questions about her birth, childhood, early career, Porter Wagoner partnership, songwriting, philanthropy, or honors.', 'hello-dolly-ai' ),
				'category'            => 'hello-dolly-knowledge',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'topic' => array(
							'type' => 'string',
							'enum' => KnowledgeBase::fact_topics(),
						),
					),
					'required'             => array( 'topic' ),
					'additionalProperties' => false,
				),
				'output_schema'       => self::fact_output_schema(),
				'execute_callback'    => static fn( array $input ): array => KnowledgeBase::fact( $input['topic'] ),
				'permission_callback' => array( self::class, 'can_read' ),
				'meta'                => self::read_only_meta(),
			)
		);

		wp_register_ability(
			self::TIMELINE,
			array(
				'label'               => __( 'Get Dolly Parton timeline events', 'hello-dolly-ai' ),
				'description'         => __( 'Get sourced milestones from Dolly Parton’s life and career, optionally limited to one decade.', 'hello-dolly-ai' ),
				'category'            => 'hello-dolly-knowledge',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'decade' => array(
							'type'    => 'string',
							'enum'    => array( 'all', '1940s', '1950s', '1960s', '1970s', '1980s', '1990s', '2000s', '2010s' ),
							'default' => 'all',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'decade'       => array( 'type' => 'string' ),
						'events'       => array(
							'type'  => 'array',
							'items' => array(
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
				),
				'execute_callback'    => static fn( ?array $input = null ): array => KnowledgeBase::timeline( $input['decade'] ?? 'all' ),
				'permission_callback' => array( self::class, 'can_read' ),
				'meta'                => self::read_only_meta(),
			)
		);

		wp_register_ability(
			self::SONG,
			array(
				'label'               => __( 'Get a Dolly Parton song note', 'hello-dolly-ai' ),
				'description'         => __( 'Get a release year, short thematic summary, and source for a signature Dolly Parton song. This never returns song lyrics.', 'hello-dolly-ai' ),
				'category'            => 'hello-dolly-knowledge',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'title' => array(
							'type' => 'string',
							'enum' => KnowledgeBase::song_slugs(),
						),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'slug'         => array( 'type' => 'string' ),
						'title'        => array( 'type' => 'string' ),
						'year'         => array( 'type' => 'integer' ),
						'theme'        => array( 'type' => 'string' ),
						'source'       => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'source_label' => array( 'type' => 'string' ),
					),
					'required'   => array( 'slug', 'title', 'year', 'theme', 'source', 'source_label' ),
				),
				'execute_callback'    => static fn( array $input ): array => KnowledgeBase::song( $input['title'] ),
				'permission_callback' => array( self::class, 'can_read' ),
				'meta'                => self::read_only_meta(),
			)
		);
	}

	/** @param mixed $input */
	public static function can_read( $input = null ): bool {
		unset( $input );

		return current_user_can( 'read' );
	}

	/** @return array<string, mixed> */
	private static function fact_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'topic'        => array( 'type' => 'string' ),
				'title'        => array( 'type' => 'string' ),
				'answer'       => array( 'type' => 'string' ),
				'source'       => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'source_label' => array( 'type' => 'string' ),
			),
			'required'   => array( 'topic', 'title', 'answer', 'source', 'source_label' ),
		);
	}

	/** @return array<string, mixed> */
	private static function read_only_meta(): array {
		return array(
			'annotations'  => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'show_in_rest' => false,
		);
	}
}
