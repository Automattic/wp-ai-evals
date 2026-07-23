<?php
/**
 * Prints the next stable release version from semantic version tags on STDIN.
 */

declare(strict_types=1);

use Automattic\AiEvals\Build\ReleaseVersion;

require_once __DIR__ . '/release-tools.php';

$root         = dirname( __DIR__ );
$package      = json_decode( (string) file_get_contents( $root . '/package.json' ), true );
$bump         = $argv[1] ?? 'patch';
$initial      = is_array( $package ) && isset( $package['version'] ) ? (string) $package['version'] : '';
$standard_in  = file( 'php://stdin', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
$release_tags = is_array( $standard_in ) ? array_values( $standard_in ) : array();

try {
	fwrite( STDOUT, ReleaseVersion::next_version( $release_tags, $initial, $bump ) . "\n" );
} catch ( Throwable $error ) {
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
}
