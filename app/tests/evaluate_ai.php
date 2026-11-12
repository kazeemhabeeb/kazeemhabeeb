<?php
/*
 * Accuracy evaluation of the AI classification (Objective 4, Chapter 4.5).
 * Sends every article of a labelled test set through the same code the hourly script uses
 * (build_prompt -> call_ai -> parse_ai_reply -> classify_score) and compares the verdict with the label.
 *
 *   php app/tests/evaluate_ai.php                         (uses evaluation/set-v1.json)
 *   php app/tests/evaluate_ai.php evaluation/set-v1.json v1
 *
 * Uses one real AI call per article (about 15 credits each). A failed call is retried once after
 * 5 seconds; an article that still fails is reported as "no verdict" and left out of the metrics,
 * as the live site would leave it Pending (NFR-2.1).
 * Results are saved to evaluation/results-<name>-<date>.json so a later version can be compared.
 */
if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/sanitize.php';
require __DIR__ . '/../includes/ai.php';

$setFile = __DIR__ . '/' . (isset($argv[1]) ? $argv[1] : 'evaluation/set-v1.json');
$name    = isset($argv[2]) ? $argv[2] : 'v1';
$set     = json_decode(file_get_contents($setFile), true);

$results = [];
foreach ($set as $n => $article) {
    if ($n > 0) {
        sleep(AI_CALL_DELAY_SECONDS);
    }
    $start = microtime(true);
    $analysis = null;
    $error = '';
    for ($try = 1; $try <= 2; $try++) {
        $reply = call_ai(build_prompt($article));
        $analysis = $reply['ok'] ? parse_ai_reply($reply['text']) : null;
        if (is_array($analysis)) {
            break;
        }
        $error = $reply['ok'] ? 'unusable reply: ' . $analysis : $reply['error'];
        if ($try === 1) {
            sleep(5);
        }
    }
    $row = [
        'id' => $article['id'], 'label' => $article['label'], 'group' => $article['group'],
        'source' => $article['source'], 'title' => $article['title'],
        'seconds' => round(microtime(true) - $start, 1),
    ];
    if (is_array($analysis)) {
        $row += ['score' => $analysis['score'], 'verdict' => classify_score($analysis['score']),
                 'clickbait' => $analysis['clickbait'], 'emotional' => $analysis['emotional'],
                 'bias' => $analysis['bias'], 'satire' => $analysis['satire'], 'reason' => $analysis['reason']];
        $row['correct'] = $row['verdict'] === $article['label'];
    } else {
        $row += ['verdict' => 'No verdict', 'error' => $error, 'correct' => null];
    }
    $results[] = $row;
    printf("%2d %-14s %-16s %3s %-14s %s  %s\n", $row['id'], $row['label'], mb_strimwidth($row['source'], 0, 16, '', 'UTF-8'),
           isset($row['score']) ? $row['score'] : '-', $row['verdict'],
           $row['correct'] === null ? '?' : ($row['correct'] ? 'OK ' : 'XX '), mb_strimwidth($row['title'], 0, 60, '…', 'UTF-8'));
}

// Metrics. "Positive" = Misinformation, the class the system is meant to detect.
$tp = $fp = $tn = $fn = 0;
$noVerdict = 0;
$groups = [];
foreach ($results as $r) {
    if ($r['correct'] === null) {
        $noVerdict++;
        continue;
    }
    $fake = $r['label'] === 'Misinformation';
    $saidFake = $r['verdict'] === 'Misinformation';
    if ($fake && $saidFake) $tp++;
    elseif (!$fake && $saidFake) $fp++;
    elseif (!$fake && !$saidFake) $tn++;
    else $fn++;
    $g = $r['group'];
    if (!isset($groups[$g])) $groups[$g] = [0, 0];
    $groups[$g][0]++;
    $groups[$g][1] += $r['correct'] ? 1 : 0;
}
$answered  = $tp + $fp + $tn + $fn;
$accuracy  = $answered ? ($tp + $tn) / $answered : 0;
$precision = ($tp + $fp) ? $tp / ($tp + $fp) : 0;
$recall    = ($tp + $fn) ? $tp / ($tp + $fn) : 0;
$f1        = ($precision + $recall) ? 2 * $precision * $recall / ($precision + $recall) : 0;
$times     = array_column($results, 'seconds');

$summary = [
    'name' => $name, 'date' => date('Y-m-d H:i'), 'articles' => count($results), 'answered' => $answered,
    'no_verdict' => $noVerdict, 'true_positive' => $tp, 'false_positive' => $fp, 'true_negative' => $tn,
    'false_negative' => $fn, 'accuracy' => round($accuracy, 3), 'precision' => round($precision, 3),
    'recall' => round($recall, 3), 'f1' => round($f1, 3),
    'specificity' => ($tn + $fp) ? round($tn / ($tn + $fp), 3) : 0,
    'average_seconds' => round(array_sum($times) / count($times), 1),
    'groups' => array_map(function ($g) { return ['articles' => $g[0], 'correct' => $g[1]]; }, $groups),
];

echo "\n", json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
$out = __DIR__ . "/evaluation/results-$name-" . date('Ymd-Hi') . '.json';
file_put_contents($out, json_encode(['summary' => $summary, 'results' => $results],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
echo "Saved: $out\n";
