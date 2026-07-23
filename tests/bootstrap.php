<?php
/**
 * PHPUnit bootstrap for the library's unit tests.
 *
 * The Composer autoloader is loaded first so that the package's own
 * bootstrap.php (registered via the "files" autoloader) sees no WordPress
 * functions and stays inert. The WordPress test doubles are loaded afterward.
 */

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/wordpress-stubs.php';
