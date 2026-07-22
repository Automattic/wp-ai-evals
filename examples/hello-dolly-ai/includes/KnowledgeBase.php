<?php

declare(strict_types=1);

namespace HelloDollyAI;

final class KnowledgeBase
{
    private const FACTS = [
        'birth' => [
            'title' => 'Birth and family',
            'answer' => 'Dolly Rebecca Parton was born January 19, 1946, in Locust Ridge, Tennessee. She was the fourth of twelve children.',
            'source' => 'https://countrymusichalloffame.org/hall-of-fame/dolly-parton',
            'source_label' => 'Country Music Hall of Fame and Museum',
        ],
        'childhood' => [
            'title' => 'Appalachian childhood',
            'answer' => 'Dolly grew up in a close musical family in Locust Ridge near the Great Smoky Mountains. Her mother shared songs and ballads, and her uncle Bill Owens helped her begin performing professionally.',
            'source' => 'https://dollyparton.com/about-dolly-parton',
            'source_label' => 'Dolly Parton — Life & Career',
        ],
        'early-career' => [
            'title' => 'Early career',
            'answer' => 'She performed on local radio and television as a child, appeared at the Grand Ole Opry at thirteen, and moved to Nashville in 1964 immediately after high school.',
            'source' => 'https://countrymusichalloffame.org/hall-of-fame/dolly-parton',
            'source_label' => 'Country Music Hall of Fame and Museum',
        ],
        'porter-wagoner' => [
            'title' => 'The Porter Wagoner Show',
            'answer' => 'Dolly joined The Porter Wagoner Show in 1967. The partnership expanded her audience and led to her signing with RCA as both a duet and solo artist.',
            'source' => 'https://countrymusichalloffame.org/hall-of-fame/dolly-parton',
            'source_label' => 'Country Music Hall of Fame and Museum',
        ],
        'songwriting' => [
            'title' => 'Songwriting',
            'answer' => 'Dolly identifies first and foremost as a songwriter. Her autobiographical “Coat of Many Colors” tells a childhood story, while “Jolene,” “I Will Always Love You,” and “9 to 5” became signature works.',
            'source' => 'https://www.loc.gov/static/programs/national-recording-preservation-board/documents/Coat-of-Many-Colors_Hubbs.pdf',
            'source_label' => 'Library of Congress — National Recording Registry essay',
        ],
        'philanthropy' => [
            'title' => 'Philanthropy',
            'answer' => 'Dolly founded the Dollywood Foundation to support education in her home region. Its Imagination Library grew into an international book-gifting program for young children.',
            'source' => 'https://dollyparton.com/imagination_library/the-dollywood-foundation-formed',
            'source_label' => 'Dolly Parton — Imagination Library',
        ],
        'honors' => [
            'title' => 'Honors',
            'answer' => 'Dolly was inducted into the Country Music Hall of Fame in 1999. Her honors also include the Kennedy Center Honors, a Grammy Lifetime Achievement Award, and the Library of Congress Living Legend Award.',
            'source' => 'https://dollyparton.com/about-dolly-parton',
            'source_label' => 'Dolly Parton — Life & Career',
        ],
    ];

    private const TIMELINE = [
        ['year' => 1946, 'event' => 'Born in Locust Ridge, Tennessee.', 'topic' => 'life'],
        ['year' => 1959, 'event' => 'Appeared as a guest at the Grand Ole Opry at age thirteen.', 'topic' => 'career'],
        ['year' => 1964, 'event' => 'Moved to Nashville after graduating from high school.', 'topic' => 'career'],
        ['year' => 1967, 'event' => 'Joined The Porter Wagoner Show and signed with RCA.', 'topic' => 'career'],
        ['year' => 1971, 'event' => 'Released “Coat of Many Colors.”', 'topic' => 'music'],
        ['year' => 1973, 'event' => 'Released “Jolene” as a single.', 'topic' => 'music'],
        ['year' => 1974, 'event' => 'Released “I Will Always Love You.”', 'topic' => 'music'],
        ['year' => 1980, 'event' => 'Starred in the film 9 to 5 and released its title song.', 'topic' => 'film'],
        ['year' => 1986, 'event' => 'Dollywood opened in East Tennessee.', 'topic' => 'business'],
        ['year' => 1995, 'event' => 'The Imagination Library book-gifting program launched.', 'topic' => 'philanthropy'],
        ['year' => 1999, 'event' => 'Inducted into the Country Music Hall of Fame.', 'topic' => 'honors'],
        ['year' => 2011, 'event' => 'Received the Grammy Lifetime Achievement Award.', 'topic' => 'honors'],
    ];

    private const SONGS = [
        'coat-of-many-colors' => [
            'title' => 'Coat of Many Colors',
            'year' => 1971,
            'theme' => 'An autobiographical story about childhood poverty, a handmade coat, family love, and dignity.',
            'source' => 'https://www.loc.gov/news/2012/12-107.html',
            'source_label' => 'Library of Congress',
        ],
        'jolene' => [
            'title' => 'Jolene',
            'year' => 1973,
            'theme' => 'A direct plea to a captivating rival, built around vulnerability rather than hostility.',
            'source' => 'https://countrymusichalloffame.org/hall-of-fame/dolly-parton',
            'source_label' => 'Country Music Hall of Fame and Museum',
        ],
        'i-will-always-love-you' => [
            'title' => 'I Will Always Love You',
            'year' => 1974,
            'theme' => 'A tender farewell that expresses gratitude and enduring affection while accepting separation.',
            'source' => 'https://countrymusichalloffame.org/hall-of-fame/dolly-parton',
            'source_label' => 'Country Music Hall of Fame and Museum',
        ],
        '9-to-5' => [
            'title' => '9 to 5',
            'year' => 1980,
            'theme' => 'A witty workplace anthem about unequal power, frustration, and solidarity among workers.',
            'source' => 'https://countrymusichalloffame.org/hall-of-fame/dolly-parton',
            'source_label' => 'Country Music Hall of Fame and Museum',
        ],
    ];

    /** @return array<string, string> */
    public static function fact(string $topic): array
    {
        $topic = self::normalize($topic);
        if (!isset(self::FACTS[$topic])) {
            return [
                'topic' => $topic,
                'title' => 'Unknown topic',
                'answer' => 'No curated fact is available for that topic.',
                'source' => 'https://dollyparton.com/about-dolly-parton',
                'source_label' => 'Dolly Parton — Life & Career',
            ];
        }

        return ['topic' => $topic] + self::FACTS[$topic];
    }

    /** @return array{decade: string, events: list<array<string, mixed>>, source: string, source_label: string} */
    public static function timeline(string $decade = 'all'): array
    {
        $decade = self::normalize($decade);
        $events = self::TIMELINE;

        if ('all' !== $decade && 1 === preg_match('/^(19|20)\d0s$/', $decade)) {
            $start = (int) substr($decade, 0, 4);
            $events = array_values(array_filter(
                self::TIMELINE,
                static fn(array $event): bool => $event['year'] >= $start && $event['year'] < $start + 10
            ));
        }

        return [
            'decade' => $decade,
            'events' => $events,
            'source' => 'https://dollyparton.com/about-dolly-parton',
            'source_label' => 'Dolly Parton — Life & Career',
        ];
    }

    /** @return array<string, mixed> */
    public static function song(string $title): array
    {
        $slug = self::normalize($title);
        if (!isset(self::SONGS[$slug])) {
            return [
                'slug' => $slug,
                'title' => $title,
                'year' => 0,
                'theme' => 'No curated song note is available. Do not invent lyrics or details.',
                'source' => 'https://dollyparton.com/life-and-career',
                'source_label' => 'Dolly Parton — Life & Career',
            ];
        }

        return ['slug' => $slug] + self::SONGS[$slug];
    }

    /** @return list<string> */
    public static function factTopics(): array
    {
        return array_keys(self::FACTS);
    }

    /** @return list<string> */
    public static function songSlugs(): array
    {
        return array_keys(self::SONGS);
    }

    private static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);

        return trim((string) $value, '-');
    }
}
