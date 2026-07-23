<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;

final class Suite {

	private string $id;
	private string $label;
	private string $description = '';

	/** @var array<string, \Automattic\AiEvals\EvaluationCase> */
	private array $cases = array();

	private function __construct( string $id, string $label ) {
		self::assert_valid_id( $id );
		$this->id    = $id;
		$this->label = $label;
	}

	public static function make( string $id, string $label = '' ): self {
		return new self( $id, '' !== $label ? $label : $id );
	}

	public function describe( string $description ): self {
		$this->description = $description;

		return $this;
	}

	public function add_case( EvaluationCase $evaluation_case ): self {
		$id = $evaluation_case->get_id();

		if ( isset( $this->cases[ $id ] ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'The eval case "%s" is already registered in suite "%s".',
					esc_html( $id ),
					esc_html( $this->id )
				)
			);
		}

		$this->cases[ $id ] = $evaluation_case;

		return $this;
	}

	/** @param iterable<\Automattic\AiEvals\EvaluationCase> $cases */
	public function add_cases( iterable $cases ): self {
		foreach ( $cases as $evaluation_case ) {
			if ( ! $evaluation_case instanceof EvaluationCase ) {
				throw new InvalidArgumentException( 'Suite::add_cases() accepts only EvaluationCase instances.' );
			}
			$this->add_case( $evaluation_case );
		}

		return $this;
	}

	public function get_id(): string {
		return $this->id;
	}

	public function get_label(): string {
		return $this->label;
	}

	public function get_description(): string {
		return $this->description;
	}

	/** @return array<string, \Automattic\AiEvals\EvaluationCase> */
	public function get_cases(): array {
		return $this->cases;
	}

	private static function assert_valid_id( string $id ): void {
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9._-]*$/', $id ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Invalid suite ID "%s". Use lowercase letters, numbers, dots, underscores, or hyphens.',
					esc_html( $id )
				)
			);
		}
	}
}
