<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;

final class Selection
{
    /** @var list<string> */
    private array $suiteIds;

    /** @var list<string> */
    private array $caseIds;

    /** @var list<string> */
    private array $tags;

    private int $repetitions;

    /**
     * @param list<string> $suiteIds
     * @param list<string> $caseIds
     * @param list<string> $tags
     */
    public function __construct(array $suiteIds = [], array $caseIds = [], array $tags = [], int $repetitions = 1)
    {
        if ($repetitions < 1) {
            throw new InvalidArgumentException('Repetitions must be at least 1.');
        }

        $this->suiteIds = self::clean($suiteIds);
        $this->caseIds = self::clean($caseIds);
        $this->tags = self::clean($tags);
        $this->repetitions = $repetitions;
    }

    public static function all(): self
    {
        return new self();
    }

    public function matches(Suite $suite, EvaluationCase $case): bool
    {
        if ([] !== $this->suiteIds && !in_array($suite->getId(), $this->suiteIds, true)) {
            return false;
        }

        if ([] !== $this->caseIds) {
            $qualifiedId = $suite->getId() . '/' . $case->getId();
            if (!in_array($case->getId(), $this->caseIds, true)
                && !in_array($qualifiedId, $this->caseIds, true)
            ) {
                return false;
            }
        }

        if ([] !== $this->tags && [] === array_intersect($this->tags, $case->getTags())) {
            return false;
        }

        return true;
    }

    public function getRepetitions(): int
    {
        return $this->repetitions;
    }

    /** @param list<string> $items @return list<string> */
    private static function clean(array $items): array
    {
        $items = array_map(static fn(string $item): string => strtolower(trim($item)), $items);

        return array_values(array_unique(array_filter($items, static fn(string $item): bool => '' !== $item)));
    }
}
