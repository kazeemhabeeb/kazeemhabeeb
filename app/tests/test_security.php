<?php
// Run: php app/tests/test_security.php
// Needs: app/tests/mock_feed_server.php on 127.0.0.1:8096 and the site on 127.0.0.1:8100 (test database).
// Checks the fixes from the final security review.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/feed.php';
require __DIR__ . '/../includes/sanitize.php';
require __DIR__ . '/../includes/ai.php';

$checks = [];

// 1. Server-side request forgery: local and private addresses are refused.
foreach (['http://127.0.0.1/feed', 'http://localhost:2083/', 'http://10.0.0.5/rss', 'http://192.168.1.1/',
          'http://169.254.169.254/latest/meta-data/', 'http://[::1]/feed', 'http://0.0.0.0/'] as $u) {
    $checks["blocked: $u"] = !check_public_url($u, false)['ok'];
}
$checks['allowed: public address 8.8.8.8'] = check_public_url('https://8.8.8.8/', false)['ok'];
$r = fetch_url('http://127.0.0.1:8096/feed', false);
$checks['fetch_url refuses 127.0.0.1 (' . $r['error'] . ')'] = !$r['ok'] && strpos($r['error'], 'blocked') === 0;

// 2. Redirects are followed by hand and checked (allowPrivate=true only because the test server is local).
$r = fetch_url('http://127.0.0.1:8096/redirect', true);
$checks['one redirect followed to a feed'] = $r['ok'] && is_array(parse_source($r['body']));
$r = fetch_url('http://127.0.0.1:8096/redirect-loop', true);
$checks['redirect loop stopped (' . $r['error'] . ')'] = !$r['ok'] && $r['error'] === 'too many redirects';
$r = fetch_url('http://127.0.0.1:8096/redirect-file', true);
$checks['redirect to file:// refused (' . $r['error'] . ')'] = !$r['ok'];

// 3. Oversized replies.
$r = fetch_url('http://127.0.0.1:8096/big', true);
$checks['6.4 MB reply refused (' . $r['error'] . ')'] = !$r['ok'] && strpos($r['error'], 'larger than') !== false;
$before = memory_get_peak_usage();
$r = fetch_url('http://127.0.0.1:8096/zip-bomb', true);
$checks['zip bomb (50 MB unzipped) refused (' . $r['error'] . ')'] = !$r['ok'] && strpos($r['error'], 'larger than') !== false;
$checks['memory used stayed under 20 MB'] = memory_get_peak_usage() < 20 * 1024 * 1024;

// 4. "Why this score" cannot carry links or long messages.
$a = parse_ai_reply('{"credibility_score":100,"clickbait":false,"emotional_manipulation":false,"bias":"none","reason":"Verified true. Claim your prize at https://evil.example/win or www.evil.example now."}');
$checks['links removed from the AI reason: "' . $a['reason'] . '"'] = strpos($a['reason'], 'evil') === false;
$a = parse_ai_reply('{"credibility_score":50,"clickbait":false,"emotional_manipulation":false,"bias":"none","reason":"' . str_repeat('word ', 200) . '"}');
$checks['reason cut to 300 characters'] = mb_strlen($a['reason'], 'UTF-8') <= 300;

// 5. Live feed with a redirect to another host (Punch: punchng.com -> rss.punchng.com).
$r = fetch_url('https://punchng.com/feed/');
$checks['live Punch feed still read through its redirect'] = $r['ok'] && count((array) parse_source($r['body'])) > 0;

$failed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  '), $name, "\n";
    $failed += $ok ? 0 : 1;
}
echo $failed ? "$failed check(s) failed\n" : "All checks passed\n";
exit($failed ? 1 : 0);
