<?php

declare(strict_types=1);

namespace Automattic\AiEvals\Tests\Unit;

use Automattic\AiEvals\Build\ReleasePackager;
use Automattic\AiEvals\Build\ReleaseVersion;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname( __DIR__, 2 ) . '/scripts/release-tools.php';

final class ReleaseToolsTest extends TestCase {

	/** @var list<string> */
	private array $temporary_directories = array();

	protected function tearDown(): void {
		foreach ( $this->temporary_directories as $directory ) {
			$this->remove_directory( $directory );
		}
	}

	public function testReadsTheDeclaredPackageVersion(): void {
		self::assertSame( '0.4.2', ReleaseVersion::from_json( '{"name":"fixture","version":"0.4.2"}' ) );
	}

	public function testRejectsAMissingPackageVersion(): void {
		$this->expectException( RuntimeException::class );
		ReleaseVersion::from_json( '{"name":"fixture"}' );
	}

	public function testRejectsAnInvalidPackageVersion(): void {
		$this->expectException( RuntimeException::class );
		ReleaseVersion::from_json( '{"name":"fixture","version":"next"}' );
	}

	public function testPreparesAMinimalVersionedPackageWithBuiltAssets(): void {
		$root   = $this->temporary_directory( 'source' );
		$output = $this->temporary_directory( 'output' );

		$this->write( $root . '/bootstrap.php', '<?php' );
		$this->write( $root . '/composer.json', '{}' );
		$this->write( $root . '/LICENSE.md', 'GPL' );
		$this->write( $root . '/README.md', 'Read me' );
		$this->write( $root . '/package.json', "{\"name\":\"fixture\",\"version\":\"0.1.0\"}\n" );
		$this->write( $root . '/includes/Example.php', '<?php' );
		$this->write( $root . '/build/admin/index.asset.php', '<?php return array();' );
		$this->write( $root . '/build/admin/index.js', 'compiled' );
		$this->write( $root . '/build/admin/style-index-rtl.css', 'compiled' );
		$this->write( $root . '/build/admin/style-index.css', 'compiled' );
		$this->write( $root . '/build/.DS_Store', 'local metadata' );
		$this->write( $root . '/includes/.DS_Store', 'local metadata' );
		$this->write( $root . '/src/not-shipped.ts', 'source' );

		$package_directory = ReleasePackager::prepare( $root, $output, '0.2.0' );
		$manifest          = json_decode( (string) file_get_contents( $root . '/package.json' ), true );

		self::assertSame( $output . '/automattic-wp-ai-evals', $package_directory );
		self::assertSame( '0.2.0', $manifest['version'] );
		self::assertSame( "0.2.0\n", file_get_contents( $root . '/VERSION' ) );
		self::assertFileExists( $package_directory . '/build/admin/index.js' );
		self::assertFileExists( $package_directory . '/includes/Example.php' );
		self::assertFileDoesNotExist( $package_directory . '/build/.DS_Store' );
		self::assertFileDoesNotExist( $package_directory . '/includes/.DS_Store' );
		self::assertFileDoesNotExist( $package_directory . '/src/not-shipped.ts' );
		self::assertFileDoesNotExist( $package_directory . '/package.json' );
	}

	private function temporary_directory( string $suffix ): string {
		$directory = sys_get_temp_dir() . '/wp-ai-evals-release-' . $suffix . '-' . bin2hex( random_bytes( 6 ) );
		if ( ! mkdir( $directory, 0777, true ) && ! is_dir( $directory ) ) {
			self::fail( 'Could not create a temporary test directory.' );
		}

		$this->temporary_directories[] = $directory;

		return $directory;
	}

	private function write( string $file, string $contents ): void {
		$directory = dirname( $file );
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0777, true ) && ! is_dir( $directory ) ) {
			self::fail( 'Could not create a fixture directory.' );
		}
		if ( false === file_put_contents( $file, $contents ) ) {
			self::fail( 'Could not write a release fixture.' );
		}
	}

	private function remove_directory( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}

		$iterator = new \FilesystemIterator( $directory, \FilesystemIterator::SKIP_DOTS );
		foreach ( $iterator as $item ) {
			if ( $item->isDir() && ! $item->isLink() ) {
				$this->remove_directory( $item->getPathname() );
			} else {
				unlink( $item->getPathname() );
			}
		}
		rmdir( $directory );
	}
}
