<?php

declare(strict_types=1);

namespace Automattic\AiEvals;

use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Loader\DirectoryLoader;

final class Registry {

	/** @var array<string, \Automattic\AiEvals\Suite> */
	private array $suites = array();

	public function register( Suite $suite ): self {
		$id = $suite->get_id();

		if ( isset( $this->suites[ $id ] ) ) {
			throw new InvalidArgumentException( sprintf( 'The eval suite "%s" is already registered.', esc_html( $id ) ) );
		}

		$this->suites[ $id ] = $suite;

		return $this;
	}

	public function load_directory( string $directory ): self {
		return ( new DirectoryLoader() )->load( $this, $directory );
	}

	public function has( string $id ): bool {
		return isset( $this->suites[ $id ] );
	}

	public function get( string $id ): Suite {
		if ( ! $this->has( $id ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown eval suite "%s".', esc_html( $id ) ) );
		}

		return $this->suites[ $id ];
	}

	/** @return array<string, \Automattic\AiEvals\Suite> */
	public function all(): array {
		return $this->suites;
	}

	/** @return list<string> */
	public function tags(): array {
		$tags = array();

		foreach ( $this->suites as $suite ) {
			foreach ( $suite->get_cases() as $evaluation_case ) {
				foreach ( $evaluation_case->get_tags() as $tag ) {
					$tags[ $tag ] = true;
				}
			}
		}

		$result = array_keys( $tags );
		sort( $result );

		return array_values( $result );
	}
}
