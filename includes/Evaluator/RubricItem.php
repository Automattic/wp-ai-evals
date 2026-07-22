<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use JsonSerializable;
use Automattic\AiEvals\Exception\InvalidArgumentException;

final class RubricItem implements JsonSerializable
{
    private string $id;
    private string $label;
    private string $criteria;
    private float $weight;
    private ?float $minimumScore;

    public function __construct(
        string $id,
        string $criteria,
        float $weight = 1.0,
        ?float $minimumScore = null,
        string $label = ''
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9._-]*$/', $id)) {
            throw new InvalidArgumentException(sprintf('Invalid rubric item ID "%s".', $id));
        }
        if ('' === trim($criteria)) {
            throw new InvalidArgumentException(sprintf('Rubric item "%s" requires criteria.', $id));
        }
        if ($weight <= 0.0) {
            throw new InvalidArgumentException(sprintf('Rubric item "%s" requires a positive weight.', $id));
        }
        if (null !== $minimumScore && ($minimumScore < 0.0 || $minimumScore > 1.0)) {
            throw new InvalidArgumentException(sprintf(
                'Rubric item "%s" minimum score must be between 0 and 1.',
                $id
            ));
        }

        $this->id = $id;
        $this->label = '' !== trim($label) ? trim($label) : ucwords(str_replace(['-', '_', '.'], ' ', $id));
        $this->criteria = trim($criteria);
        $this->weight = $weight;
        $this->minimumScore = $minimumScore;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCriteria(): string
    {
        return $this->criteria;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getMinimumScore(): ?float
    {
        return $this->minimumScore;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'criteria' => $this->criteria,
            'weight' => $this->weight,
            'minimum_score' => $this->minimumScore,
        ];
    }
}
