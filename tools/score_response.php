<?php
/*
 * Turn a Fact Checker (RapidAPI) response into a 0-100 confidence score.
 *
 * Usage:
 *   php tools/score_response.php tools/samples/microchips.json "COVID-19 vaccines contain microchips"
 *
 * The API does not return a score. It returns published fact-checks of similar
 * claims, each with a free-text rating such as "False" or "Missing context".
 * This script keeps only the fact-checks whose claim matches the headline,
 * converts each rating to a number, and averages them (proposal section 3.7:
 * "maps out the confidence score").
 */

// Share of the headline's keywords a fact-checked claim must contain to count as a match.
define('MIN_MATCH', 0.6);

// Rating phrases, checked in this order so that "mostly false" and "not true"
// are caught before the plain words "false" and "true".
function rating_to_score($rating)
{
    $r = strtolower(trim($rating));
    $rules = array(
        array(array('mostly false'), 25),
        array(array('mostly true'), 75),
        array(array('missing context', 'half true', 'partly false', 'partly true', 'mixture', 'misleading', 'unproven'), 40),
        array(array('not true', 'false', 'fake', 'hoax', 'incorrect', 'pants on fire', 'fabricated', 'bogus', 'debunked'), 10),
        array(array('true', 'correct', 'accurate'), 90),
    );
    foreach ($rules as $rule) {
        foreach ($rule[0] as $phrase) {
            if (preg_match('/\b' . preg_quote($phrase, '/') . '\b/', $r)) {
                return $rule[1];
            }
        }
    }
    return null; // rating could not be read, so it is left out
}

function keywords($text)
{
    $stop = array('the', 'and', 'for', 'that', 'this', 'with', 'from', 'are', 'was', 'were', 'has', 'have',
                  'had', 'will', 'can', 'its', 'his', 'her', 'their', 'into', 'over', 'after', 'says', 'said');
    $words = preg_split('/[^a-z0-9\-]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    $keep = array();
    foreach ($words as $w) {
        if (strlen($w) > 2 && !in_array($w, $stop)) {
            if (strlen($w) > 4 && substr($w, -1) === 's') {
                $w = substr($w, 0, -1); // treat "microchips" and "microchip" as the same word
            }
            $keep[$w] = true;
        }
    }
    return array_keys($keep);
}

function match_share($headline_words, $claim)
{
    if (count($headline_words) === 0) {
        return 0;
    }
    $common = array_intersect($headline_words, keywords($claim));
    return count($common) / count($headline_words);
}

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/score_response.php <response.json> \"<headline>\"\n");
    exit(1);
}

$response = json_decode(file_get_contents($argv[1]), true);
$headline = $argv[2];
$headline_words = keywords($headline);

$scores = array();
echo "Headline: $headline\n";
echo "Keywords: " . implode(', ', $headline_words) . "\n\n";

foreach ($response['data'] as $item) {
    $share = match_share($headline_words, $item['claim_text']);
    foreach ($item['claim_reviews'] as $review) {
        $score = rating_to_score($review['review_text']);
        $used = $share >= MIN_MATCH && $score !== null;
        if ($used) {
            $scores[] = $score;
        }
        printf("%s match %3d%%  rating %-18s score %-4s %s\n",
            $used ? '[used]' : '[skip]',
            round($share * 100),
            '"' . substr($review['review_text'], 0, 16) . '"',
            $score === null ? '-' : $score,
            substr($item['claim_text'], 0, 60));
    }
}

echo "\n";
if (count($scores) === 0) {
    echo "Result: no matching fact-check found -> Pending Verification (yellow)\n";
    exit(0);
}

$confidence = (int) round(array_sum($scores) / count($scores));
$status = $confidence >= 50 ? 'Real (green)' : 'Misinformation (red)';
echo "Matched fact-checks: " . count($scores) . "\n";
echo "Confidence score   : $confidence%\n";
echo "Result             : $status\n";
