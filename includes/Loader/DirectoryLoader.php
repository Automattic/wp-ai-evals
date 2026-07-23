<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Loader;

use Automattic\AiEvals\EvaluationCase;
use Automattic\AiEvals\Exception\InvalidArgumentException;
use Automattic\AiEvals\Registry;
use Automattic\AiEvals\Suite;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class DirectoryLoader {

	public function load( Registry $registry, string $directory ): Registry {
		$root = realpath( $directory );
		if ( false === $root || ! is_dir( $root ) || ! is_readable( $root ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Eval directory "%s" is not readable.', esc_html( $directory ) )
			);
		}

		$loaded = 0;
		foreach ( $this->top_level_php_files( $root ) as $file ) {
			$loaded += $this->register_suites( $registry, $this->require_file( $file, $root ), $file );
		}

		foreach ( $this->suite_directories( $root ) as $suite_directory ) {
			$manifest = $suite_directory . '/suite.php';
			if ( ! is_readable( $manifest ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'Eval suite directory "%s" requires a readable suite.php manifest.',
						esc_html( $suite_directory )
					)
				);
			}

			$suite = $this->require_file( $manifest, $root );
			if ( ! $suite instanceof Suite ) {
				throw new InvalidArgumentException(
					sprintf( 'Eval manifest "%s" must return a Suite.', esc_html( $manifest ) )
				);
			}

			$cases_directory = $suite_directory . '/cases';
			if ( is_dir( $cases_directory ) ) {
				foreach ( $this->recursive_php_files( $cases_directory ) as $case_file ) {
					$this->add_cases( $suite, $this->require_file( $case_file, $root ), $case_file );
				}
			}

			$registry->register( $suite );
			++$loaded;
		}

		if ( 0 === $loaded ) {
			throw new InvalidArgumentException(
				sprintf( 'Eval directory "%s" did not contain any suites.', esc_html( $directory ) )
			);
		}

		return $registry;
	}

	/** @return list<string> */
	private function top_level_php_files( string $root ): array {
		$files = glob( $root . '/*.php' );
		if ( false === $files ) {
			return array();
		}

		sort( $files, SORT_STRING );

		return array_values( $files );
	}

	/** @return list<string> */
	private function suite_directories( string $root ): array {
		$directories = glob( $root . '/*', GLOB_ONLYDIR );
		if ( false === $directories ) {
			return array();
		}

		sort( $directories, SORT_STRING );

		return array_values( $directories );
	}

	/** @return list<string> */
	private function recursive_php_files( string $directory ): array {
		$files    = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		);

		/** @var \SplFileInfo $file */
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
				continue;
			}

			$files[] = $file->getPathname();
		}

		sort( $files, SORT_STRING );

		return $files;
	}

	/** @return mixed */
	private function require_file( string $file, string $root ) {
		$resolved    = realpath( $file );
		$root_prefix = rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		if (
			false === $resolved
			|| ! is_file( $resolved )
			|| ! is_readable( $resolved )
			|| 0 !== strpos( $resolved, $root_prefix )
		) {
			throw new InvalidArgumentException(
				sprintf( 'Eval file "%s" must resolve to a readable PHP file inside the eval root.', esc_html( $file ) )
			);
		}

		return ( static function ( string $isolated_file ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- The canonical path is checked above and constrained to the registered eval root.
			return require $isolated_file;
		} )( $resolved );
	}

	/** @param mixed $value */
	private function register_suites( Registry $registry, $value, string $file ): int {
		if ( $value instanceof Suite ) {
			$registry->register( $value );

			return 1;
		}

		if ( ! is_iterable( $value ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Eval file "%s" must return a Suite or iterable of suites.',
					esc_html( $file )
				)
			);
		}

		$count = 0;
		foreach ( $value as $suite ) {
			if ( ! $suite instanceof Suite ) {
				throw new InvalidArgumentException(
					sprintf( 'Eval file "%s" returned a non-Suite value.', esc_html( $file ) )
				);
			}
			$registry->register( $suite );
			++$count;
		}

		return $count;
	}

	/** @param mixed $value */
	private function add_cases( Suite $suite, $value, string $file ): void {
		if ( $value instanceof EvaluationCase ) {
			$suite->add_case( $value );
			return;
		}

		if ( ! is_iterable( $value ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Eval case file "%s" must return an EvaluationCase or iterable of cases.',
					esc_html( $file )
				)
			);
		}

		foreach ( $value as $evaluation_case ) {
			if ( ! $evaluation_case instanceof EvaluationCase ) {
				throw new InvalidArgumentException(
					sprintf(
						'Eval case file "%s" returned a non-EvaluationCase value.',
						esc_html( $file )
					)
				);
			}
			$suite->add_case( $evaluation_case );
		}
	}
}
