<?php
/*
 * Analytics dashboard (FR-3.4): total posts ingested, fake vs. real article ratio, active feed
 * statuses and view counts per article. Every figure is counted from the database each time the
 * page opens, so it is always current.
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/public.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/admin.php';

send_security_headers();
require_admin();
$db = db_connect();

// Totals by verdict.
$counts = ['Real' => 0, 'Misinformation' => 0, 'Pending Verification' => 0];
$result = mysqli_query($db, 'SELECT verification_status, COUNT(*) AS n FROM articles GROUP BY verification_status');
while ($row = mysqli_fetch_assoc($result)) {
    $counts[$row['verification_status']] = (int) $row['n'];
}
$total    = array_sum($counts);
$real     = $counts['Real'];
$fake     = $counts['Misinformation'];
$pending  = $counts['Pending Verification'];
$verified = $real + $fake;
$realPct  = $verified ? round($real / $verified * 100, 1) : 0;
$fakePct  = $verified ? round(100 - $realPct, 1) : 0;

// The most recent hourly run (FR-3.5 log), used for "last run" below.
$lastRun = mysqli_fetch_assoc(mysqli_query($db,
    "SELECT created_at, message FROM logs WHERE log_type = 'Execution' ORDER BY log_id DESC LIMIT 1"));

// Feed statuses: Active/Inactive, articles collected, newest article, and whether the source
// failed in the last hourly run (its address appears in an "RSS feed failed" log from that run).
$sources = mysqli_fetch_all(mysqli_query($db,
    "SELECT s.website_name, s.status, COUNT(a.article_id) AS articles, MAX(a.publication_date) AS newest,
            (SELECT MAX(l.created_at) FROM logs l
              WHERE l.log_type = 'RSS feed failed' AND LOCATE(CONCAT('(', s.rss_url, ')'), l.message) > 0) AS last_failure
       FROM rss_sources s LEFT JOIN articles a ON a.source_id = s.source_id
      GROUP BY s.source_id ORDER BY s.status, s.website_name"), MYSQLI_ASSOC);
$runStarted = $lastRun ? strtotime($lastRun['created_at']) - 15 * 60 : null;   // a run takes at most a few minutes

// View counts per article: the 10 most read (all views are listed on the Articles page).
$mostViewed = mysqli_fetch_all(mysqli_query($db,
    'SELECT article_id, title, view_count, verification_status, confidence_score
       FROM articles ORDER BY view_count DESC, article_id DESC LIMIT 10'), MYSQLI_ASSOC);

function stat_card($colour, $number, $label, $textClass = 'text-white')
{
    return '<div class="col-xl-3 col-md-6"><div class="card bg-' . $colour . ' ' . $textClass . ' mb-4">'
         . '<div class="card-body"><div class="fs-2 fw-bold">' . number_format($number) . '</div>' . $label . '</div>'
         . '<div class="card-footer d-flex align-items-center justify-content-between">'
         . '<a class="small ' . $textClass . ' stretched-link" href="articles.php">View Details</a>'
         . '<div class="small ' . $textClass . '"><i class="fas fa-angle-right"></i></div></div></div></div>';
}

echo admin_header('Dashboard', 'dashboard');
?>
                        <div class="row">
                            <?= stat_card('primary', $total, 'Total Posts Ingested') ?>
                            <?= stat_card('success', $real, 'Real Articles') ?>
                            <?= stat_card('danger', $fake, 'Fake Articles') ?>
                            <?= stat_card('warning', $pending, 'Pending Verification', 'text-dark') ?>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-chart-bar me-1"></i> Fake vs. Real Ratio <span class="text-muted small">(verified articles only)</span></div>
                            <div class="card-body">
<?php if ($verified): ?>
                                <div class="progress mb-2" id="ratioBar">
                                    <div class="progress-bar bg-success" data-width="<?= $realPct ?>" role="progressbar" aria-label="Real" aria-valuenow="<?= $realPct ?>" aria-valuemin="0" aria-valuemax="100">Real <?= $realPct ?>%</div>
                                    <div class="progress-bar bg-danger" data-width="<?= $fakePct ?>" role="progressbar" aria-label="Fake" aria-valuenow="<?= $fakePct ?>" aria-valuemin="0" aria-valuemax="100">Fake <?= $fakePct ?>%</div>
                                </div>
                                <div>Real: <strong><?= $real ?></strong> &nbsp; Fake: <strong><?= $fake ?></strong> &nbsp; Pending: <strong><?= $pending ?></strong>
<?php if ($fake): ?>
                                    &nbsp; Ratio (fake : real) = <strong>1 : <?= number_format($real / $fake, 1) ?></strong>
<?php endif; ?>
                                </div>
<?php else: ?>
                                <p class="mb-0 text-muted">No articles have been verified yet.</p>
<?php endif; ?>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-clock me-1"></i> Last Hourly Run</div>
                            <div class="card-body">
<?php if ($lastRun): ?>
                                <div class="small text-muted mb-1"><?= e(date('F j, Y \a\t g:i a', strtotime($lastRun['created_at']))) ?></div>
                                <div><?= e($lastRun['message']) ?></div>
                                <div class="mt-2"><a href="logs.php">See all system logs</a></div>
<?php else: ?>
                                <p class="mb-0 text-muted">The hourly script has not run yet.</p>
<?php endif; ?>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-6">
                                <div class="card mb-4">
                                    <div class="card-header"><i class="fas fa-rss me-1"></i> Feed Statuses</div>
                                    <div class="card-body table-responsive">
                                        <table class="table table-bordered table-striped align-middle mb-0">
                                            <thead><tr><th>Source</th><th>Status</th><th>Last run</th><th class="text-end">Articles</th><th>Newest article</th></tr></thead>
                                            <tbody>
<?php foreach ($sources as $s): ?>
<?php
    if ($s['status'] !== 'Active' || !$runStarted) {
        $run = '<span class="text-muted">&mdash;</span>';
    } elseif ($s['last_failure'] && strtotime($s['last_failure']) >= $runStarted) {
        $run = '<span class="badge bg-danger">Failed</span>';
    } else {
        $run = '<span class="badge bg-success">OK</span>';
    }
?>
                                                <tr>
                                                    <td><?= e($s['website_name']) ?></td>
                                                    <td><span class="badge <?= $s['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($s['status']) ?></span></td>
                                                    <td><?= $run ?></td>
                                                    <td class="text-end"><?= (int) $s['articles'] ?></td>
                                                    <td class="text-nowrap"><?= $s['newest'] ? e(nice_date($s['newest'])) : '<span class="text-muted">none yet</span>' ?></td>
                                                </tr>
<?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-6">
                                <div class="card mb-4">
                                    <div class="card-header"><i class="fas fa-eye me-1"></i> View Counts per Article <span class="text-muted small">(10 most read)</span></div>
                                    <div class="card-body table-responsive">
                                        <table class="table table-bordered table-striped align-middle mb-0">
                                            <thead><tr><th>Article</th><th>Verdict</th><th class="text-end">Views</th></tr></thead>
                                            <tbody>
<?php foreach ($mostViewed as $a): ?>
                                                <tr>
                                                    <td><a href="../article.php?id=<?= (int) $a['article_id'] ?>" target="_blank" rel="noopener"><?= e($a['title']) ?></a></td>
                                                    <td class="text-nowrap"><?= badge_html($a['verification_status'], $a['confidence_score'] === null ? null : (int) $a['confidence_score']) ?></td>
                                                    <td class="text-end"><?= number_format((int) $a['view_count']) ?></td>
                                                </tr>
<?php endforeach; ?>
                                            </tbody>
                                        </table>
                                        <div class="small mt-2"><a href="articles.php">See the views for every article</a></div>
                                    </div>
                                </div>
                            </div>
                        </div>
<?= admin_footer(['js/dashboard.js']) ?>
