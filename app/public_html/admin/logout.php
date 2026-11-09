<?php
/*
 * Logout. Only accepts the POST form in the admin menu, with its CSRF token, so another site
 * cannot log the admin out with a hidden link.
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/auth.php';

require_admin();
check_csrf();
log_out_admin();
header('Location: login.php?logged_out=1');
