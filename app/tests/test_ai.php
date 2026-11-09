<?php
// Run: php app/tests/test_ai.php   (start app/tests/mock_ai_server.php on port 8095 first)
// Tests the AI module without spending credits: reply checking, the 50% rule and failure handling.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/sanitize.php';
require __DIR__ . '/../includes/ai.php';

$checks = [];

// 1. Real replies saved from the RapidAPI tests (tools/samples/final).
foreach (glob(__DIR__ . '/../../tools/samples/final/*.json') as $file) {
    $outer = json_decode(file_get_contents($file), true);
    $a = parse_ai_reply($outer['result']);
    $checks['real reply ' . basename($file) . ' -> ' . (is_array($a) ? $a['score'] . ' ' . classify_score($a['score']) : 'REJECTED')] = is_array($a);
}

// 2. The 50% rule at its edge (§3.4.2B step 6).
$checks['score 50 is Real']         = classify_score(50) === 'Real';
$checks['score 49 is Misinformation'] = classify_score(49) === 'Misinformation';
$checks['score 0 is Misinformation']  = classify_score(0) === 'Misinformation';
$checks['score 100 is Real']        = classify_score(100) === 'Real';

// 3. Replies GPT might send ("nonsense"): accepted only when the data itself is valid.
$good = '{"credibility_score":85,"clickbait":false,"emotional_manipulation":false,"bias":"low","reason":"Plausible."}';
$cases = [
    'extra words around the JSON accepted'  => ['Here is my analysis: ' . $good . ' Hope this helps!', true],
    '```json fences accepted'               => ["```json\n$good\n```", true],
    'score sent as text "85" accepted'      => [str_replace('85', '"85"', $good), true],
    'bias "Low" (capital) accepted'         => [str_replace('"low"', '"Low"', $good), true],
    'score 150 rejected'                    => [str_replace('85', '150', $good), false],
    'score -5 rejected'                     => [str_replace('85', '-5', $good), false],
    'score "85%" rejected'                  => [str_replace('85', '"85%"', $good), false],
    'score 72.5 rejected'                   => [str_replace('85', '72.5', $good), false],
    'missing score rejected'                => ['{"clickbait":false,"emotional_manipulation":false,"bias":"low","reason":"x"}', false],
    'bias "extreme" rejected'               => [str_replace('"low"', '"extreme"', $good), false],
    'clickbait "maybe" rejected'            => [str_replace('"clickbait":false', '"clickbait":"maybe"', $good), false],
    'missing reason rejected'               => [str_replace(',"reason":"Plausible."', '', $good), false],
    'reply cut off half-way rejected'       => [substr($good, 0, 40), false],
    'plain text refusal rejected'           => ['I cannot help with that.', false],
];
foreach ($cases as $name => $case) {
    $checks[$name] = is_array(parse_ai_reply($case[0])) === $case[1];
}
$a = parse_ai_reply(str_replace('Plausible.', '<script>alert(1)</script>Plausible <b>claim</b>.', $good));
$checks['HTML in the reason is removed'] = is_array($a) && $a['reason'] === 'Plausible claim.';

// 4. Failures from the service itself (mock server), each must come back as a loggable failure.
$base = 'http://127.0.0.1:8095';
$r = call_ai('test', "$base/slow", 1);
$checks['slow reply -> API timeout']      = !$r['ok'] && $r['type'] === 'API timeout';
foreach (['rate-limit', 'no-credits', 'bad-key', 'server-down', 'new-format'] as $p) {
    $r = call_ai('test', "$base/$p", 5);
    $checks["$p -> API error (" . ($r['ok'] ? 'ok' : $r['error']) . ')'] = !$r['ok'] && $r['type'] === 'API error';
}
$r = call_ai('test', 'http://127.0.0.1:8099/nothing-here', 5);
$checks['service unreachable -> API error'] = !$r['ok'] && $r['type'] === 'API error';
$r = call_ai('test', "$base/good", 5);
$checks['good reply -> ok']                = $r['ok'] && is_array(parse_ai_reply($r['text']));

// 5. The prompt carries exactly the FR-1.2 fields.
$prompt = build_prompt(['title' => 'T', 'content_summary' => null, 'original_link' => 'https://x.ng/a', 'publication_date' => '2026-11-09 10:00:00']);
$checks['prompt has title, body, source_url, publication_date'] = strpos($prompt, '"title":"T","body":"(headline only)","source_url":"https://x.ng/a","publication_date":"2026-11-09 10:00:00"') !== false;

$failed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  '), $name, "\n";
    $failed += $ok ? 0 : 1;
}
echo $failed ? "$failed check(s) failed\n" : "All checks passed\n";
exit($failed ? 1 : 0);
