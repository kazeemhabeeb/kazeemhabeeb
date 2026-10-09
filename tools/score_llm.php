<?php
/*
 * Read an LLM API response and apply the proposal's 50% rule (Fig 3.2).
 *
 * Usage:
 *   php tools/score_llm.php tools/samples/llm-diabetes-fruit.json
 *
 * The API wraps the model's answer as a JSON string inside its own JSON
 * ("result"), so it is decoded twice. Anything missing or out of range is
 * treated as a failed check, which leaves the article as Pending Verification
 * (NFR-2.1).
 */

function parse_llm_response($body)
{
    $outer = json_decode($body, true);
    if (!is_array($outer) || empty($outer['status']) || !isset($outer['result'])) {
        return null;
    }

    // Some models wrap JSON in ```json fences; keep only the {...} part.
    $text = $outer['result'];
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false) {
        return null;
    }
    $inner = json_decode(substr($text, $start, $end - $start + 1), true);

    if (!is_array($inner) || !isset($inner['credibility_score']) || !is_numeric($inner['credibility_score'])) {
        return null;
    }
    $score = (int) round($inner['credibility_score']);
    if ($score < 0 || $score > 100) {
        return null;
    }
    $inner['credibility_score'] = $score;
    return $inner;
}

function map_verdict($analysis)
{
    if ($analysis === null) {
        return array(null, 'Pending Verification');
    }
    $score = $analysis['credibility_score'];
    return array($score, $score >= 50 ? 'Real' : 'Misinformation');
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/score_llm.php <response.json>\n");
    exit(1);
}

$analysis = parse_llm_response(file_get_contents($argv[1]));
list($confidence, $status) = map_verdict($analysis);

if ($analysis !== null) {
    echo "Clickbait             : " . (!empty($analysis['clickbait']) ? 'yes' : 'no') . "\n";
    echo "Emotional manipulation: " . (!empty($analysis['emotional_manipulation']) ? 'yes' : 'no') . "\n";
    echo "Bias                  : " . (isset($analysis['bias']) ? $analysis['bias'] : '-') . "\n";
    echo "Reason                : " . (isset($analysis['reason']) ? $analysis['reason'] : '-') . "\n";
}
echo "confidence_score      : " . ($confidence === null ? 'NULL' : $confidence . '%') . "\n";
echo "verification_status   : $status\n";
