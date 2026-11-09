<?php
/*
 * System and error logs (FR-3.5): shows the operational execution logs and highlights API timeout
 * events and failed RSS feed connections for administrative review. Read-only.
 *   logs.php?type=API+timeout&page=2
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/public.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/admin.php';

send_security_headers();
require_admin();
$db = db_connect();

define('LOGS_PAGE_SIZE', 25);

// Each log type, its badge colour and the row highlight used for problems.
$types = [
    'Execution'       => ['bg-secondary', ''],
    'API timeout'     => ['bg-warning text-dark', 'table-warning'],
    'API error'       => ['bg-danger', 'table-danger'],
    'RSS feed failed' => ['bg-danger', 'table-danger'],
    'Spam filtered'   => ['bg-info text-dark', ''],
    'Login failed'    => ['bg-warning text-dark', 'table-warning'],
];

$type = (string) filter_input(INPUT_GET, 'type');
if (!isset($types[$type])) {
    $type = '';
}
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);

// How many of each type, all time and in the last 24 hours.
$totals = array_fill_keys(array_keys($types), 0);
$recent = array_fill_keys(array_keys($types), 0);
$result = mysqli_query($db,
    'SELECT log_type, COUNT(*) AS total, SUM(created_at >= NOW() - INTERVAL 1 DAY) AS recent FROM logs GROUP BY log_type');
while ($row = mysqli_fetch_assoc($result)) {
    $totals[$row['log_type']] = (int) $row['total'];
    $recent[$row['log_type']] = (int) $row['recent'];
}
$count = $type === '' ? array_sum($totals) : $totals[$type];
$pages = max(1, (int) ceil($count / LOGS_PAGE_SIZE));
$page  = min($page, $pages);
$offset = ($page - 1) * LOGS_PAGE_SIZE;
$limit  = LOGS_PAGE_SIZE;

if ($type === '') {
    $stmt = mysqli_prepare($db, 'SELECT log_type, message, created_at FROM logs ORDER BY log_id DESC LIMIT ? OFFSET ?');
    mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
} else {
    $stmt = mysqli_prepare($db, 'SELECT log_type, message, created_at FROM logs WHERE log_type = ? ORDER BY log_id DESC LIMIT ? OFFSET ?');
    mysqli_stmt_bind_param($stmt, 'sii', $type, $limit, $offset);
}
mysqli_stmt_execute($stmt);
$logs = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

function logs_url($type, $page)
{
    $query = http_build_query(array_filter(['type' => $type, 'page' => $page > 1 ? $page : null]));
    return 'logs.php' . ($query === '' ? '' : '?' . $query);
}

echo admin_header('System Logs', 'logs');

// FR-3.5 "highlighting API timeout events or failed RSS feed connections".
$problems = $recent['API timeout'] + $recent['API error'] + $recent['RSS feed failed'] + $recent['Login failed'];
?>
<?php if ($problems): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            In the last 24 hours: <strong><?= $recent['API timeout'] ?></strong> API timeout(s),
                            <strong><?= $recent['API error'] ?></strong> API error(s) and
                            <strong><?= $recent['RSS feed failed'] ?></strong> failed feed connection(s) and
                            <strong><?= $recent['Login failed'] ?></strong> failed admin login(s).
                            Articles affected by API problems stay Pending Verification and are retried every hour.
                        </div>
<?php else: ?>
                        <div class="alert alert-success"><i class="fas fa-check me-1"></i> No API timeouts, API errors, failed feeds or failed logins in the last 24 hours.</div>
<?php endif; ?>
                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-list me-1"></i> System Logs <span class="text-muted small">(<?= number_format($count) ?>)</span></div>
                            <div class="card-body">
                                <div class="mb-3 d-flex flex-wrap gap-2" role="group" aria-label="Show log type">
                                    <a class="btn btn-sm <?= $type === '' ? 'btn-dark' : 'btn-outline-dark' ?>" href="logs.php">All <span class="badge bg-light text-dark"><?= array_sum($totals) ?></span></a>
<?php foreach ($types as $name => $style): ?>
                                    <a class="btn btn-sm <?= $type === $name ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= e(logs_url($name, 1)) ?>"><?= e($name) ?> <span class="badge <?= $style[0] ?>"><?= $totals[$name] ?></span></a>
<?php endforeach; ?>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                                        <thead><tr><th>Date / Time</th><th>Event</th><th>Details</th></tr></thead>
                                        <tbody>
<?php foreach ($logs as $log): ?>
<?php $style = isset($types[$log['log_type']]) ? $types[$log['log_type']] : ['bg-secondary', '']; ?>
                                            <tr class="<?= $style[1] ?>">
                                                <td class="text-nowrap"><?= e(date('Y-m-d H:i:s', strtotime($log['created_at']))) ?></td>
                                                <td><span class="badge <?= $style[0] ?>"><?= e($log['log_type']) ?></span></td>
                                                <td class="text-break"><?= e($log['message']) ?></td>
                                            </tr>
<?php endforeach; ?>
<?php if (!$logs): ?>
                                            <tr><td colspan="3" class="text-center text-muted py-4">No log entries<?= $type === '' ? '' : ' of this type' ?> yet.</td></tr>
<?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
<?php if ($pages > 1): ?>
                                <ul class="pagination justify-content-end mt-3 mb-0">
                                    <li class="page-item<?= $page === 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(logs_url($type, $page - 1)) ?>">Newer</a></li>
<?php for ($p = max(1, min($page - 2, $pages - 4)); $p <= min($pages, max(1, min($page - 2, $pages - 4)) + 4); $p++): ?>
                                    <li class="page-item<?= $p === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e(logs_url($type, $p)) ?>"><?= $p ?></a></li>
<?php endfor; ?>
                                    <li class="page-item<?= $page === $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(logs_url($type, $page + 1)) ?>">Older</a></li>
                                </ul>
<?php endif; ?>
                            </div>
                        </div>
<?= admin_footer() ?>
