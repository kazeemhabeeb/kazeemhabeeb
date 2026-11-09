<?php
/*
 * Create the administrator account, or change its password (decision 22: one admin, no sign-up page).
 * Run on the server from the command line (cPanel Terminal):
 *     php tools/create_admin.php
 * The password is typed in hidden and stored only as a bcrypt hash (NFR-1.2, §3.8).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../includes/bootstrap.php';

function ask($question, $hidden = false)
{
    echo $question;
    if ($hidden && stream_isatty(STDIN) && DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
    }
    $answer = trim((string) fgets(STDIN));
    if ($hidden && stream_isatty(STDIN) && DIRECTORY_SEPARATOR === '/') {
        system('stty echo');
        echo "\n";
    }
    return $answer;
}

$username = ask('Admin username: ');
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    exit("The username must be 3 to 50 letters, numbers, dots, dashes or underscores.\n");
}
$password = ask('Password (at least 10 characters): ', true);
if (strlen($password) < 10) {
    exit("The password must be at least 10 characters.\n");
}
if (ask('Type the password again: ', true) !== $password) {
    exit("The passwords do not match. Nothing was saved.\n");
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$db = db_connect();
$stmt = mysqli_prepare($db,
    'INSERT INTO admins (username, password_hash) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)');
mysqli_stmt_bind_param($stmt, 'ss', $username, $hash);
mysqli_stmt_execute($stmt);

echo mysqli_stmt_affected_rows($stmt) === 1
    ? "Admin \"$username\" created.\n"
    : "Password for \"$username\" changed.\n";
