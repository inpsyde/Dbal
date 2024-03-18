<?php

declare(strict_types=1);

$testsDir = str_replace('\\', '/', __DIR__);
$libDir = dirname($testsDir);
$vendorDir = "{$libDir}/vendor";
$autoload = "{$vendorDir}/autoload.php";

if (!is_file($autoload)) {
    die('Please install via Composer before running tests.');
}

putenv('VENDOR_DIR=' . $vendorDir);
putenv('TESTS_DIR=' . $testsDir);
putenv('LIB_DIR=' . $libDir);

error_reporting(E_ALL);

if (!defined('ABSPATH')) {
    define('ABSPATH', "{$vendorDir}/roots/wordpress-no-content/");
}

if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    define('PHPUNIT_COMPOSER_INSTALL', $autoload);
    require_once $autoload;
}

unset($libDir, $testsDir, $vendorDir, $autoload);