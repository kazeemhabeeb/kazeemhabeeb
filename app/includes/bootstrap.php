<?php
/*
 * Loaded first by every page and by the cron script.
 * Reads the settings, sets the time zone and loads the shared functions.
 */

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    error_log('TrustFeed: config/config.php is missing. Copy config.example.php and fill it in.');
    http_response_code(500);
    exit('The site is not configured yet.');
}
require $configFile;

date_default_timezone_set(TIMEZONE);

// Never show PHP errors to visitors; they go to the server's error log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
