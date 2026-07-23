<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;

final class Selection {

	/** @var list<string> */
	private array $suite_ids;

	/** @var list<string> */
	private array $case_ids;

	/** @var list<string> */
	private array $tags;

	private int $repetitions;

	/**
	 * @param list<string> $suite_ids
	 * @param list<string> $case_ids
	 * @param list<string> $tags
	 */
	public function __construct( array $suite_ids = array(), array $case_ids = array(), array $tags = array(), int $repetitions = 1 ) {
		if ( $repetitions < 1 ) {
			throw new InvalidArgumentException( 'Repetitions must be at least 1.' );
		}

		$this->suite_ids   = self::clean( $suite_ids );
		$this->case_ids    = self::clean( $case_ids );
		$this->tags        = self::clean( $tags );
		$this->repetitions = $repetitions;
	}

	public static function all(): self {
		return new self();
	}

	public function matches( Suite $suite, EvaluationCase $evaluation_case ): bool {
		if ( array() !== $this->suite_ids && ! in_array( $suite->get_id(), $this->suite_ids, true ) ) {
			return false;
		}

		if ( array() !== $this->case_ids ) {
			$qualified_id = $suite->get_id() . '/' . $evaluation_case->get_id();
			if ( ! in_array( $evaluation_case->get_id(), $this->case_ids, true )
				&& ! in_array( $qualified_id, $this->case_ids, true )
			) {
				return false;
			}
		}

		return array() === $this->tags || array() !== array_intersect( $this->tags, $evaluation_case->get_tags() );
	}

	public function get_repetitions(): int {
		return $this->repetitions;
	}

	/** @param list<string> $items @return list<string> */
	private static function clean( array $items ): array {
		$items = array_map( static fn( string $item ): string => strtolower( trim( $item ) ), $items );

		return array_values( array_unique( array_filter( $items, static fn( string $item ): bool => '' !== $item ) ) );
	}
}
