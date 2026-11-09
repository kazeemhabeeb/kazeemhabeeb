<?php
/*
 * Admin dashboard (FR-3.4). Placeholder until the dashboard step of Sprint 4.
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/public.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/admin.php';

send_security_headers();
require_admin();

echo admin_header('Dashboard', 'dashboard');
?>
                        <div class="alert alert-info">You are logged in. The dashboard figures are added in the next step.</div>
<?= admin_footer() ?>
