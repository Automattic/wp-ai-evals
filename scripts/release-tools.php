<?php
/**
 * Helpers used to create tagged package releases.
 */

declare(strict_types=1);

namespace Automattic\AiEvals\Build;

use FilesystemIterator;
use RuntimeException;

final class ReleaseVersion {

	public static function from_json( string $json ): string {
		$package = json_decode( $json, true );
		if ( ! is_array( $package ) || ! isset( $package['version'] ) || ! is_string( $package['version'] ) ) {
			throw new RuntimeException( 'package.json must contain a string version.' );
		}

		self::assert_version( $package['version'] );

		return $package['version'];
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
