<?php

declare(strict_types=1);

namespace HelloDollyAI;

final class Abilities
{
    public const FACT = 'hello-dolly/get-fact';
    public const TIMELINE = 'hello-dolly/get-timeline';
    public const SONG = 'hello-dolly/get-song';

    /** @return list<string> */
    public static function names(): array
    {
        return [self::FACT, self::TIMELINE, self::SONG];
    }

    public static function registerCategory(): void
    {
        wp_register_ability_category(
            'hello-dolly-knowledge',
            [
                'label' => __('Hello Dolly knowledge', 'hello-dolly-ai'),
                'description' => __('Read-only, curated facts about Dolly Parton.', 'hello-dolly-ai'),
            ]
        );
    }

    public static function register(): void
    {
        wp_register_ability(
            self::FACT,
            [
                'label' => __('Get a Dolly Parton fact', 'hello-dolly-ai'),
                'description' => __('Get a sourced fact about Dolly Parton. Use this for questions about her birth, childhood, early career, Porter Wagoner partnership, songwriting, philanthropy, or honors.', 'hello-dolly-ai'),
                'category' => 'hello-dolly-knowledge',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'topic' => [
                            'type' => 'string',
                            'enum' => KnowledgeBase::factTopics(),
                        ],
                    ],
                    'required' => ['topic'],
                    'additionalProperties' => false,
                ],
                'output_schema' => self::factOutputSchema(),
                'execute_callback' => static fn(array $input): array => KnowledgeBase::fact($input['topic']),
                'permission_callback' => [self::class, 'canRead'],
                'meta' => self::readOnlyMeta(),
            ]
        );

        wp_register_ability(
            self::TIMELINE,
            [
                'label' => __('Get Dolly Parton timeline events', 'hello-dolly-ai'),
                'description' => __('Get sourced milestones from Dolly Parton’s life and career, optionally limited to one decade.', 'hello-dolly-ai'),
                'category' => 'hello-dolly-knowledge',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'decade' => [
                            'type' => 'string',
                            'enum' => ['all', '1940s', '1950s', '1960s', '1970s', '1980s', '1990s', '2000s', '2010s'],
                            'default' => 'all',
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'decade' => ['type' => 'string'],
                        'events' => [
                            'type' => 'array',
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
                ],
                'execute_callback' => static fn(?array $input = null): array => KnowledgeBase::timeline($input['decade'] ?? 'all'),
                'permission_callback' => [self::class, 'canRead'],
                'meta' => self::readOnlyMeta(),
            ]
        );

        wp_register_ability(
            self::SONG,
            [
                'label' => __('Get a Dolly Parton song note', 'hello-dolly-ai'),
                'description' => __('Get a release year, short thematic summary, and source for a signature Dolly Parton song. This never returns song lyrics.', 'hello-dolly-ai'),
                'category' => 'hello-dolly-knowledge',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'enum' => KnowledgeBase::songSlugs(),
                        ],
                    ],
                    'required' => ['title'],
                    'additionalProperties' => false,
                ],
                'output_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'year' => ['type' => 'integer'],
                        'theme' => ['type' => 'string'],
                        'source' => ['type' => 'string', 'format' => 'uri'],
                        'source_label' => ['type' => 'string'],
                    ],
                    'required' => ['slug', 'title', 'year', 'theme', 'source', 'source_label'],
                ],
                'execute_callback' => static fn(array $input): array => KnowledgeBase::song($input['title']),
                'permission_callback' => [self::class, 'canRead'],
                'meta' => self::readOnlyMeta(),
            ]
        );
    }

    /** @param mixed $input */
    public static function canRead($input = null): bool
    {
        return current_user_can('read');
    }

    /** @return array<string, mixed> */
    private static function factOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'answer' => ['type' => 'string'],
                'source' => ['type' => 'string', 'format' => 'uri'],
                'source_label' => ['type' => 'string'],
            ],
            'required' => ['topic', 'title', 'answer', 'source', 'source_label'],
        ];
    }

    /** @return array<string, mixed> */
    private static function readOnlyMeta(): array
    {
        return [
            'annotations' => [
                'readonly' => true,
                'destructive' => false,
                'idempotent' => true,
            ],
            'show_in_rest' => false,
        ];
    }
}
