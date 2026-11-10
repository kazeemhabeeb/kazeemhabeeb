<?php
// Run: php app/tests/test_second_opinion.php   (start app/tests/mock_ai_server.php on port 8095 first)
// Tests the web-search second opinion (decision 32) end to end on the test database, without spending credits.
// Both AI services are replaced by the mock server; the article titles tell the mock how to answer.
error_reporting(E_ALL & ~E_WARNING);            // config.php defines RAPIDAPI_URL again: the mock address below wins
define('RAPIDAPI_URL', 'http://127.0.0.1:8095/flow-gpt4');
define('SEARCH_API_HOST', 'search-gpt.test');
define('SEARCH_API_URL', 'http://127.0.0.1:8095/flow-web');
define('SECOND_OPINION_MAX_PER_RUN', 2);
define('SECOND_OPINION_MAX_WAITING', 3);
require __DIR__ . '/../includes/bootstrap.php';
error_reporting(E_ALL);
require __DIR__ . '/../includes/sanitize.php';
require __DIR__ . '/../includes/tier1.php';
require __DIR__ . '/../includes/ai.php';

$db = db_connect();
$checks = [];
mysqli_query($db, "INSERT INTO rss_sources (website_name, rss_url) VALUES ('Second opinion test', 'http://127.0.0.1:8096/second-opinion-test')");
$source = mysqli_insert_id($db);
mysqli_query($db, "UPDATE articles SET verification_status = 'Real', confidence_score = 90 WHERE verification_status = 'Pending Verification'");

function add($db, $source, $title, $hoursAgo)
{
    $stmt = mysqli_prepare($db, "INSERT INTO articles (source_id, title, content_summary, original_link, publication_date)
                                 VALUES (?, ?, 'Summary.', ?, NOW() - INTERVAL ? HOUR)");
    $link = 'https://example.test/' . rawurlencode($title);
    mysqli_stmt_bind_param($stmt, 'issi', $source, $title, $link, $hoursAgo);
    mysqli_stmt_execute($stmt);
    return mysqli_insert_id($db);
}
function row($db, $id)
{
    return mysqli_fetch_assoc(mysqli_query($db, "SELECT * FROM articles WHERE article_id = " . (int) $id));
}

try {
    $real    = add($db, $source, 'REAL budget passed', 1);
    $satire  = add($db, $source, 'SATIRE man elected moon', 2);
    $recent  = add($db, $source, 'RECENT widow condemns jokes', 3);
    $hoax    = add($db, $source, 'HOAX singer dies', 4);
    $fail    = add($db, $source, 'RECENT WEBFAIL story', 5);

    $count = verify_pending($db);
    $checks['first opinion: 5 sent, 2 final (Real + satire), 3 sent for web check'] = $count['sent'] === 5 && $count['verified'] === 2 && $count['to_web'] === 3;
    $checks['Real article is Real 85 at once']         = row($db, $real)['verification_status'] === 'Real' && (int) row($db, $real)['confidence_score'] === 85;
    $checks['satire is Misinformation at once (no web check)'] = row($db, $satire)['verification_status'] === 'Misinformation';
    $checks['2 web checks this run (limit 2), newest first'] = $count['web_sent'] === 2;
    $r = row($db, $recent);
    $checks['real recent event: first 10 -> web 88 -> Real']  = $r['verification_status'] === 'Real' && (int) $r['confidence_score'] === 88;
    $checks['web reason has no citation link']         = $r['verification_reason'] === 'Independent reporting supports the events.';
    $checks['first AI flags replaced by web flags']    = (int) $r['is_clickbait'] === 0 && (int) $r['emotional_manipulation'] === 0;
    $h = row($db, $hoax);
    $checks['hoax: first 5 -> web 8 -> Misinformation'] = $h['verification_status'] === 'Misinformation' && (int) $h['confidence_score'] === 8;
    $checks['oldest one still waiting (Pending with first score)'] = row($db, $fail)['verification_status'] === 'Pending Verification' && (int) row($db, $fail)['confidence_score'] === 10;
    $checks['tokens counted'] = $count['web_tokens'] === 2 * 13653;

    // Next run: the web check for the waiting one fails -> stays Pending, logged.
    $count = verify_pending($db);
    $checks['web failure leaves the article Pending'] = $count['web_failed'] === 1 && row($db, $fail)['verification_status'] === 'Pending Verification';

    // More than SECOND_OPINION_MAX_WAITING (3) waiting: the oldest get the first verdict, nothing piles up.
    $extra = [];
    foreach ([6, 7, 8] as $h) {
        $extra[] = add($db, $source, "RECENT WEBFAIL older $h", $h);
    }
    $count = verify_pending($db);
    $waiting = (int) mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM articles WHERE source_id = $source AND verification_status = 'Pending Verification'"))[0];
    $checks["over the waiting limit: 1 oldest got the first verdict, 3 still waiting"] = $count['web_fallback'] === 1 && $waiting === 3
        && row($db, end($extra))['verification_status'] === 'Misinformation';

    // Second opinion switched off: everything waiting gets its first verdict.
    $count = second_opinion($db, false);
    $checks['switched off: all 3 waiting get the first verdict'] = $count['web_fallback'] === 3;

    $log = mysqli_fetch_row(mysqli_query($db, "SELECT message FROM logs WHERE log_type = 'Second opinion' ORDER BY log_id DESC LIMIT 1"));
    $checks['"Second opinion" log written: ' . ($log ? $log[0] : '-')] = (bool) $log;

} finally {
    mysqli_query($db, "DELETE FROM rss_sources WHERE source_id = $source");   // its articles go too (ON DELETE CASCADE)
    mysqli_query($db, "DELETE FROM logs WHERE message LIKE '%second opinion%' OR log_type = 'Second opinion' OR message LIKE '%WEBFAIL%'");
}

$failed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  '), $name, "\n";
    $failed += $ok ? 0 : 1;
}
echo $failed ? "$failed check(s) failed\n" : "All checks passed\n";
exit($failed ? 1 : 0);
