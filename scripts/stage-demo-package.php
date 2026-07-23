<?php
/**
 * Build the local Composer path package consumed by the nested demo plugin.
 *
 * Composer cannot reliably mirror the repository root into a vendor directory
 * that is itself below that root. Keeping a minimal staged package beside the
 * demo avoids that recursive source/target relationship.
 */

declare(strict_types=1);

$root        = dirname( __DIR__ );
$destination = $root . '/examples/hello-dolly-ai/.packages/automattic-ai-evals';
$installed   = $root . '/examples/hello-dolly-ai/vendor/automattic/ai-evals';
$files       = array( 'bootstrap.php', 'composer.json', 'LICENSE.md' );

/**
 * Remove a generated directory tree.
 */
function remove_directory( string $directory ): void {
	if ( ! is_dir( $directory ) ) {
		return;
	}

	$iterator = new FilesystemIterator( $directory, FilesystemIterator::SKIP_DOTS );

	foreach ( $iterator as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			remove_directory( $item->getPathname() );
		} elseif ( ! unlink( $item->getPathname() ) ) {
			throw new RuntimeException( 'Could not remove ' . $item->getPathname() );
		}
	}

	if ( ! rmdir( $directory ) ) {
		throw new RuntimeException( 'Could not remove ' . $directory );
	}
}

/**
 * Copy a source directory recursively.
 */
function copy_directory( string $source, string $destination ): void {
	if ( ! is_dir( $destination ) && ! mkdir( $destination, 0777, true ) && ! is_dir( $destination ) ) {
		throw new RuntimeException( 'Could not create ' . $destination );
	}

	$iterator = new FilesystemIterator( $source, FilesystemIterator::SKIP_DOTS );

	foreach ( $iterator as $item ) {
		$target = $destination . '/' . $item->getFilename();

		if ( $item->isDir() && ! $item->isLink() ) {
			copy_directory( $item->getPathname(), $target );
		} elseif ( ! copy( $item->getPathname(), $target ) ) {
			throw new RuntimeException( 'Could not copy ' . $item->getPathname() );
		}
	}
}

remove_directory( $destination );
remove_directory( $installed );

if ( ! mkdir( $destination, 0777, true ) && ! is_dir( $destination ) ) {
	throw new RuntimeException( 'Could not create ' . $destination );
}

foreach ( $files as $file ) {
	if ( ! copy( $root . '/' . $file, $destination . '/' . $file ) ) {
		throw new RuntimeException( 'Could not copy ' . $file );
	}
}

copy_directory( $root . '/includes', $destination . '/includes' );
copy_directory( $root . '/build', $destination . '/build' );

fwrite( STDOUT, "Staged automattic/ai-evals for the Hello Dolly demo.\n" );
