<?php // phpcs:disable
if (defined('ABSPATH')) {
    return;
}

define('ABSPATH', realpath('./vendor/wordpress/wordpress') . DIRECTORY_SEPARATOR);
define('WPINC', 'wp-includes');

require_once ABSPATH . WPINC . '/load.php';
require_once ABSPATH . WPINC . '/wp-db.php';
require_once ABSPATH . WPINC . '/functions.php';
require_once ABSPATH . WPINC . '/formatting.php';
require_once ABSPATH . WPINC . '/cache.php';

function dbDelta(string $query) : void {
}
