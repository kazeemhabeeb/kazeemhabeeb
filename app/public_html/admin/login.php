<?php
/*
 * Admin login (FR-3.1, use case "Log In" on p. 35). SB Admin login template.
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/public.php';
require __DIR__ . '/../../includes/auth.php';

send_security_headers();
header('Cache-Control: no-store');
start_admin_session();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) filter_input(INPUT_POST, 'username'));
    $password = (string) filter_input(INPUT_POST, 'password');
    try {
        $admin = ($username !== '' && $password !== '') ? check_login(db_connect(), $username, $password) : null;
    } catch (mysqli_sql_exception $ex) {
        error_log('TrustFeed login: ' . $ex->getMessage());
        $admin = null;
        $error = 'The login service is unavailable right now. Please try again later.';
    }
    if ($admin) {
        log_in_admin($admin);
        header('Location: dashboard.php');
        exit;
    }
    if ($error === '') {
        sleep(1);   // slows down anyone trying many passwords
        $error = 'Wrong username or password. Check both and try again.';
    }
}
$notice = isset($_GET['expired']) ? 'You were logged out after ' . SESSION_TIMEOUT_MINUTES . ' minutes without activity. Please log in again.'
        : (isset($_GET['logged_out']) ? 'You have logged out.' : '');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="robots" content="noindex" />
        <title>Login - <?= e(SITE_NAME) ?> Admin</title>
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body class="bg-primary">
        <div id="layoutAuthentication">
            <div id="layoutAuthentication_content">
                <main>
                    <div class="container">
                        <div class="row justify-content-center">
                            <div class="col-lg-5">
                                <div class="card shadow-lg border-0 rounded-lg mt-5">
                                    <div class="card-header"><h3 class="text-center font-weight-light my-4"><?= e(SITE_NAME) ?> Admin Login</h3></div>
                                    <div class="card-body">
<?php if ($error !== ''): ?>
                                        <div class="alert alert-danger"><?= e($error) ?></div>
<?php elseif ($notice !== ''): ?>
                                        <div class="alert alert-info"><?= e($notice) ?></div>
<?php endif; ?>
                                        <form method="post" action="login.php">
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputUsername" name="username" type="text" placeholder="Username" autocomplete="username" maxlength="50" required value="<?= e($username) ?>" />
                                                <label for="inputUsername">Username</label>
                                            </div>
                                            <div class="form-floating mb-3">
                                                <input class="form-control" id="inputPassword" name="password" type="password" placeholder="Password" autocomplete="current-password" required />
                                                <label for="inputPassword">Password</label>
                                            </div>
                                            <div class="d-flex align-items-center justify-content-end mt-4 mb-0">
                                                <button class="btn btn-primary" type="submit">Login</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
            <div id="layoutAuthentication_footer">
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; <?= e(SITE_NAME) ?> <?= date('Y') ?></div>
                            <a href="../index.php">View site</a>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>
