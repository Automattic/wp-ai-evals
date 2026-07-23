<?php
/**
 * Prints the stable semantic version declared by a package.json file.
 */

declare(strict_types=1);

use Automattic\AiEvals\Build\ReleaseVersion;

require_once __DIR__ . '/release-tools.php';

$source = $argv[1] ?? '';
if ( '' === $source ) {
	fwrite( STDERR, "Usage: php scripts/read-package-version.php <package.json|->\n" );
	exit( 1 );
}

$json = '-' === $source ? file_get_contents( 'php://stdin' ) : file_get_contents( $source );
if ( false === $json ) {
	fwrite( STDERR, sprintf( "Could not read package manifest \"%s\".\n", $source ) );
	exit( 1 );
}

try {
	fwrite( STDOUT, ReleaseVersion::from_json( $json ) . "\n" );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
