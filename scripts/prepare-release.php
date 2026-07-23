<?php
/**
 * Versions the checkout and stages the Composer-ready release package.
 */

declare(strict_types=1);

use Automattic\AiEvals\Build\ReleasePackager;

require_once __DIR__ . '/release-tools.php';

if ( ! isset( $argv[1], $argv[2] ) ) {
	fwrite( STDERR, "Usage: php scripts/prepare-release.php <version> <output-directory>\n" );
	exit( 1 );
}

try {
	$package_directory = ReleasePackager::prepare( dirname( __DIR__ ), $argv[2], $argv[1] );
	fwrite( STDOUT, 'Prepared release package at ' . $package_directory . "\n" );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
