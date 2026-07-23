<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Evaluator;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use JsonSerializable;

final class Rubric implements JsonSerializable {

	/** @var array<string, \Automattic\AiEvals\Evaluator\RubricItem> */
	private array $items = array();

	public static function make(): self {
		return new self();
	}

	/**
	 * @param string|array<mixed>|self $definition
	 */
	public static function from( $definition ): self {
		if ( $definition instanceof self ) {
			return $definition;
		}

		$rubric = self::make();
		if ( is_string( $definition ) ) {
			return $rubric->item( 'overall', $definition, 1.0, null, 'Overall quality' );
		}
		if ( ! is_array( $definition ) || array() === $definition ) {
			throw new InvalidArgumentException( 'An LLM judge requires criteria or a non-empty rubric.' );
		}

		foreach ( $definition as $key => $value ) {
			if ( $value instanceof RubricItem ) {
				$rubric->addItem( $value );
				continue;
			}

			if ( is_string( $value ) ) {
				$id = is_string( $key ) ? $key : 'criterion-' . ( (int) $key + 1 );
				$rubric->item( $id, $value );
				continue;
			}

			if ( ! is_array( $value ) ) {
				throw new InvalidArgumentException( 'Rubric definitions must contain strings, arrays, or RubricItem objects.' );
			}

			$id       = isset( $value['id'] ) ? (string) $value['id'] : ( is_string( $key ) ? $key : '' );
			$criteria = isset( $value['criteria'] ) ? (string) $value['criteria'] : '';
			$weight   = isset( $value['weight'] ) ? (float) $value['weight'] : 1.0;
			$minimum  = isset( $value['minimum_score'] ) ? (float) $value['minimum_score'] : null;
			$label    = isset( $value['label'] ) ? (string) $value['label'] : '';
			$rubric->item( $id, $criteria, $weight, $minimum, $label );
		}

		return $rubric;
	}

	public function item(
		string $id,
		string $criteria,
		float $weight = 1.0,
		?float $minimum_score = null,
		string $label = ''
	): self {
		return $this->addItem( new RubricItem( $id, $criteria, $weight, $minimum_score, $label ) );
	}

	public function addItem( RubricItem $item ): self {
		if ( isset( $this->items[ $item->get_id() ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Duplicate rubric item "%s".', esc_html( $item->get_id() ) ) );
		}

		$this->items[ $item->get_id() ] = $item;

		return $this;
	}

	/** @return array<string, \Automattic\AiEvals\Evaluator\RubricItem> */
	public function getItems(): array {
		return $this->items;
	}

	public function isMultiItem(): bool {
		return count( $this->items ) > 1;
	}

	/** @return list<array<string, mixed>> */
	public function jsonSerialize(): array {
		return array_values(
			array_map(
				static fn( RubricItem $item ): array => $item->jsonSerialize(),
				$this->items
			)
		);
	}
}
