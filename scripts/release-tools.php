<?php
/**
 * Helpers used to create tagged package releases.
 */

declare(strict_types=1);

namespace Automattic\AiEvals\Build;

use FilesystemIterator;
use RuntimeException;

final class ReleaseVersion {

	/**
	 * @param list<string> $tags
	 */
	public static function next_version( array $tags, string $initial_version, string $bump ): string {
		self::assert_version( $initial_version );

		if ( ! in_array( $bump, array( 'patch', 'minor', 'major' ), true ) ) {
			throw new RuntimeException( sprintf( 'Unsupported release bump "%s".', $bump ) );
		}

		$versions = array();
		foreach ( $tags as $tag ) {
			$tag = trim( $tag );
			if ( 1 !== preg_match( '/^v?(\d+\.\d+\.\d+)$/', $tag, $matches ) ) {
				continue;
			}

			$versions[] = $matches[1];
		}

		if ( array() === $versions ) {
			return $initial_version;
		}

		usort(
			$versions,
			static function ( string $left, string $right ): int {
				return version_compare( $right, $left );
			}
		);

		$parts = array_map( 'intval', explode( '.', $versions[0] ) );
		if ( 'major' === $bump ) {
			return sprintf( '%d.0.0', $parts[0] + 1 );
		}
		if ( 'minor' === $bump ) {
			return sprintf( '%d.%d.0', $parts[0], $parts[1] + 1 );
		}

		return sprintf( '%d.%d.%d', $parts[0], $parts[1], $parts[2] + 1 );
	}

	public static function assert_version( string $version ): void {
		if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			throw new RuntimeException( sprintf( 'Invalid stable semantic version "%s".', $version ) );
		}
	}
}

final class ReleasePackager {

	private const FILES = array(
		'bootstrap.php',
		'composer.json',
		'LICENSE.md',
		'README.md',
		'VERSION',
	);

	private const DIRECTORIES = array(
		'build',
		'includes',
	);

	private const REQUIRED_BUILD_FILES = array(
		'build/admin/index.asset.php',
		'build/admin/index.js',
		'build/admin/style-index-rtl.css',
		'build/admin/style-index.css',
	);

	public static function prepare( string $root, string $output_directory, string $version ): string {
		ReleaseVersion::assert_version( $version );

		$root             = rtrim( $root, DIRECTORY_SEPARATOR );
		$output_directory = rtrim( $output_directory, DIRECTORY_SEPARATOR );
		self::assert_build_is_complete( $root );
		self::write_version_files( $root, $version );

		$package_directory = $output_directory . '/automattic-ai-evals';
		self::remove_directory( $package_directory );
		if ( ! is_dir( $package_directory )
			&& ! mkdir( $package_directory, 0777, true )
			&& ! is_dir( $package_directory )
		) {
			throw new RuntimeException( 'Could not create release package directory.' );
		}

		foreach ( self::FILES as $file ) {
			self::copy_file( $root . '/' . $file, $package_directory . '/' . $file );
		}
		foreach ( self::DIRECTORIES as $directory ) {
			self::copy_directory( $root . '/' . $directory, $package_directory . '/' . $directory );
		}

		return $package_directory;
	}

	private static function assert_build_is_complete( string $root ): void {
		foreach ( self::REQUIRED_BUILD_FILES as $file ) {
			if ( ! is_readable( $root . '/' . $file ) ) {
				throw new RuntimeException( sprintf( 'Required release build file "%s" is missing.', $file ) );
			}
		}
	}

	private static function write_version_files( string $root, string $version ): void {
		$package_file = $root . '/package.json';
		$package      = json_decode( (string) file_get_contents( $package_file ), true );
		if ( ! is_array( $package ) ) {
			throw new RuntimeException( 'Could not read package.json while preparing the release.' );
		}

		$package['version'] = $version;
		$json               = json_encode(
			$package,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		if ( false === $json || false === file_put_contents( $package_file, $json . "\n" ) ) {
			throw new RuntimeException( 'Could not write the release version to package.json.' );
		}
		if ( false === file_put_contents( $root . '/VERSION', $version . "\n" ) ) {
			throw new RuntimeException( 'Could not write the release VERSION file.' );
		}
	}

	private static function copy_file( string $source, string $destination ): void {
		if ( ! is_readable( $source ) || ! copy( $source, $destination ) ) {
			throw new RuntimeException( sprintf( 'Could not copy release file "%s".', $source ) );
		}
	}

	private static function copy_directory( string $source, string $destination ): void {
		if ( ! is_dir( $source ) ) {
			throw new RuntimeException( sprintf( 'Could not find release directory "%s".', $source ) );
		}
		if ( ! is_dir( $destination )
			&& ! mkdir( $destination, 0777, true )
			&& ! is_dir( $destination )
		) {
			throw new RuntimeException( sprintf( 'Could not create release directory "%s".', $destination ) );
		}

		$iterator = new FilesystemIterator( $source, FilesystemIterator::SKIP_DOTS );
		foreach ( $iterator as $item ) {
			if ( '.DS_Store' === $item->getFilename() ) {
				continue;
			}

			$target = $destination . '/' . $item->getFilename();
			if ( $item->isDir() && ! $item->isLink() ) {
				self::copy_directory( $item->getPathname(), $target );
			} else {
				self::copy_file( $item->getPathname(), $target );
			}
		}
	}

	private static function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS );
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				self::remove_directory( $item->getPathname() );
			} elseif ( ! unlink( $item->getPathname() ) ) {
				throw new RuntimeException( sprintf( 'Could not remove release file "%s".', $item->getPathname() ) );
			}
		}

		if ( ! rmdir( $directory ) ) {
			throw new RuntimeException( sprintf( 'Could not remove release directory "%s".', $directory ) );
		}
	}
}
