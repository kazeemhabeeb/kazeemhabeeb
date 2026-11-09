<?php
/*
 * Shared helper functions.
 */

// Escape text before printing it in HTML (NFR-1.1, §3.8).
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

// True when the page was requested over HTTPS (§3.8).
function is_https()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
}

// Save one entry to the logs table (FR-3.5).
// $type is one of: Execution, API timeout, API error, RSS feed failed, Spam filtered, Login failed.
function write_log($db, $type, $message)
{
    $stmt = mysqli_prepare($db, 'INSERT INTO logs (log_type, message) VALUES (?, ?)');
    mysqli_stmt_bind_param($stmt, 'ss', $type, $message);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
