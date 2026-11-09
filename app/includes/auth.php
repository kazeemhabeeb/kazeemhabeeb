<?php
/*
 * Admin authentication and session security (FR-3.1, NFR-1.2, §3.8).
 *   - Passwords are checked with password_verify() against the bcrypt hash in admins.password_hash.
 *   - A new session ID is issued at login (stops session fixation).
 *   - The session cookie is HttpOnly (JavaScript cannot read it), SameSite=Strict (not sent with
 *     requests from other sites) and Secure when the site runs on HTTPS.
 *   - The admin is logged out after SESSION_TIMEOUT_MINUTES without activity, and after
 *     SESSION_MAX_HOURS in any case.
 *   - After MAX_LOGIN_ATTEMPTS wrong passwords from one IP address in 15 minutes, logins from that
 *     address are refused for 15 minutes (failed logins are recorded in the logs, FR-3.5).
 *   - Every admin form carries a CSRF token (decision 18).
 */

function start_admin_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = is_https() || FORCE_HTTPS;
    session_name('trustfeed_admin');
    session_set_cookie_params([
        'lifetime' => 0,            // ends when the browser closes
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');   // refuse session IDs the server did not create
    ini_set('session.use_only_cookies', '1');  // never accept a session ID in the URL
    session_start();
}

// Check a username and password. Returns the admin row, or null.
function check_login($db, $username, $password)
{
    $stmt = mysqli_prepare($db, 'SELECT admin_id, username, password_hash FROM admins WHERE username = ?');
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Always run password_verify, even for an unknown username, so the reply takes the same time
    // and does not reveal which usernames exist.
    // The placeholder is the bcrypt hash of a random password nobody knows.
    $hash = $admin ? $admin['password_hash'] : '$2y$10$fX.04FYtIzmdp80JG59k/OXydFgQuO/j0j2ay0xup6Hbv6ilxvHcG';
    $valid = password_verify($password, $hash);
    return ($admin && $valid) ? $admin : null;
}

function client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
}

// True if this IP address has had MAX_LOGIN_ATTEMPTS failed logins in the last 15 minutes.
function login_locked($db)
{
    $pattern = '% from ' . client_ip() . '.';
    $stmt = mysqli_prepare($db,
        "SELECT COUNT(*) FROM logs WHERE log_type = 'Login failed' AND message LIKE ?
            AND created_at >= NOW() - INTERVAL 15 MINUTE");
    mysqli_stmt_bind_param($stmt, 's', $pattern);
    mysqli_stmt_execute($stmt);
    return (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] >= MAX_LOGIN_ATTEMPTS;
}

function record_failed_login($db, $username)
{
    write_log($db, 'Login failed', 'Wrong password for username "' . mb_substr($username, 0, 50, 'UTF-8') . '" from ' . client_ip() . '.');
}

function log_in_admin($admin)
{
    session_regenerate_id(true);
    $_SESSION['admin_id']      = (int) $admin['admin_id'];
    $_SESSION['admin_name']    = $admin['username'];
    $_SESSION['last_activity'] = time();
    $_SESSION['logged_in_at']  = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
}

function log_out_admin()
{
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    session_destroy();
}

// Put at the top of every admin page and admin action. Sends the visitor to the login page
// if they are not logged in, or if they have been inactive too long.
function require_admin()
{
    start_admin_session();
    $loggedIn = isset($_SESSION['admin_id']);
    $expired  = $loggedIn && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_MINUTES * 60
                              || time() - $_SESSION['logged_in_at'] > SESSION_MAX_HOURS * 3600);

    if (!$loggedIn || $expired) {
        if ($expired) {
            log_out_admin();
        }
        if (is_ajax_request()) {
            http_response_code(401);
            header('Content-Type: application/json');
            exit(json_encode(['error' => 'Your session has ended. Please log in again.']));
        }
        // Pages in admin/api/ are one folder deeper than login.php.
        $login = basename(dirname($_SERVER['SCRIPT_NAME'])) === 'api' ? '../login.php' : 'login.php';
        header('Location: ' . $login . ($expired ? '?expired=1' : ''));
        exit;
    }
    $_SESSION['last_activity'] = time();
    header('Cache-Control: no-store');   // admin pages are never cached by the browser
}

function is_ajax_request()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'fetch';
}

// CSRF protection: a secret token in every form, checked on every change (§3.8 "data tampering").
function csrf_token()
{
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '" />';
}

function check_csrf()
{
    $sent = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('This request was refused because it did not come from your admin panel. Go back, reload the page and try again.');
    }
}
