<?php
/*
 * Database connection (MySQLi, §3.5 and §3.7 module 3).
 * Every query in the app uses prepared statements with this connection (NFR-1.1, §3.8).
 */

function db_connect()
{
    // Turn MySQLi errors into exceptions so a failed query cannot pass silently.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $db = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    mysqli_set_charset($db, 'utf8mb4');

    // Store and compare times in the site's time zone (Lagos is UTC+1, no daylight saving).
    mysqli_query($db, "SET time_zone = '+01:00'");

    return $db;
}
