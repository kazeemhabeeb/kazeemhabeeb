<?php
/*
 * Tier 1: lightweight, deterministic filtering before the AI service (§2.5.1, §2.3, decision 12).
 * The same input always gives the same answer, and every rule can be explained in one sentence.
 *   - Spam: the item is not saved and a "Spam filtered" log entry is written.
 *   - Clickbait pattern: the item is saved with is_clickbait = 1 and sent to the AI first.
 * Runs on items that sanitize_item() has already cleaned.
 */

// Words that mark paid content. Matched at the start of the title or as the whole category,
// so a news headline such as "Officers promoted to new ranks" is not caught.
$TIER1_SPONSORED_WORDS = ['sponsored', 'advertorial', 'promoted', 'sponsored content', 'paid post'];

// Clickbait phrases, checked in the title and summary.
$TIER1_CLICKBAIT_PHRASES = [
    "you won't believe", 'you will not believe', 'what happened next',
    'will shock you', 'this is why',
];

// Common English words used to recognise English text (§1.5: English articles only).
// Words that are also common in French, such as "a", "an", "on" and "en", are left out.
$TIER1_ENGLISH_WORDS = [
    'the', 'and', 'of', 'to', 'is', 'for', 'that', 'with', 'was', 'has', 'have', 'are',
    'from', 'by', 'this', 'will', 'said', 'says', 'his', 'her', 'their', 'it', 'its', 'be',
    'at', 'as', 'were', 'been', 'not', 'but', 'also', 'they', 'we', 'our', 'can', 'could',
    'would', 'than', 'more', 'after', 'over', 'into', 'about', 'which', 'who', 'what',
    'when', 'how', 'why', 'while', 'new',
];

// Well-known abbreviations that are written in capitals in normal news headlines.
$TIER1_ACRONYMS = [
    'AI', 'APC', 'ASUU', 'AU', 'BBC', 'CAF', 'CBN', 'CEO', 'CNN', 'DSS', 'ECOWAS', 'EFCC',
    'EU', 'FCT', 'FG', 'FIFA', 'GDP', 'IMF', 'INEC', 'LP', 'NBS', 'NFF', 'NLC', 'NNPC',
    'NYSC', 'PDP', 'SSANU', 'UK', 'UN', 'US', 'USA', 'WHO',
];

// Returns ['spam' => reason or '', 'clickbait' => true/false, 'clickbait_reasons' => [...]].
function tier1_check($item)
{
    $result = ['spam' => tier1_spam_reason($item), 'clickbait' => false, 'clickbait_reasons' => []];
    if ($result['spam'] === '') {
        $result['clickbait_reasons'] = tier1_clickbait_reasons($item);
        $result['clickbait'] = count($result['clickbait_reasons']) > 0;
    }
    return $result;
}

// ---------------------------------------------------------------------------
// Spam rules
// ---------------------------------------------------------------------------
function tier1_spam_reason($item)
{
    global $TIER1_SPONSORED_WORDS;

    // Rule 1: not a real article.
    if ($item['title'] === '') {
        return 'no title';
    }
    if ($item['link'] === '') {
        return 'no valid link';
    }

    // Rule 2: not English (§1.5).
    if (!tier1_is_english($item['title'] . ' ' . $item['summary'])) {
        return 'not written in English';
    }

    // Rule 3: paid content.
    $title    = mb_strtolower($item['title'], 'UTF-8');
    $category = mb_strtolower(trim($item['category']), 'UTF-8');
    foreach ($TIER1_SPONSORED_WORDS as $word) {
        if ($category === $word || preg_match('/^[\[\(]?' . preg_quote($word, '/') . '\b/u', $title)) {
            return 'marked ' . ucfirst($word);
        }
    }
    return '';
}

// English if most letters are from the Latin alphabet and, for longer text,
// at least 3 words in 100 are common English words (real English news scored 6.8% or more
// in the 9 November 2026 live test; French and Spanish scored 0%).
function tier1_is_english($text)
{
    global $TIER1_ENGLISH_WORDS;

    $letters = preg_match_all('/\p{L}/u', $text);
    $latin   = preg_match_all('/\p{Latin}/u', $text);
    if ($letters >= 20 && $latin / $letters < 0.7) {
        return false;   // mostly Arabic, Chinese, Cyrillic and so on
    }

    $words = preg_split('/[^\p{L}\']+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) < 15) {
        return true;    // too short to judge fairly, e.g. a headline with no summary
    }
    $common = 0;
    foreach ($words as $word) {
        if (in_array($word, $TIER1_ENGLISH_WORDS, true)) {
            $common++;
        }
    }
    return $common / count($words) >= 0.03;
}

// ---------------------------------------------------------------------------
// Clickbait rules
// ---------------------------------------------------------------------------
function tier1_clickbait_reasons($item)
{
    global $TIER1_CLICKBAIT_PHRASES, $TIER1_ACRONYMS;
    $reasons = [];

    // Rule 4: clickbait phrases (curly apostrophes are treated like straight ones).
    $text = str_replace('’', "'", mb_strtolower($item['title'] . ' ' . $item['summary'], 'UTF-8'));
    foreach ($TIER1_CLICKBAIT_PHRASES as $phrase) {
        if (strpos($text, $phrase) !== false) {
            $reasons[] = 'phrase "' . $phrase . '"';
        }
    }

    // Rule 5: two or more exclamation marks in the headline.
    if (substr_count($item['title'], '!') >= 2) {
        $reasons[] = 'two or more exclamation marks';
    }

    // Rule 6: three or more ALL-CAPS words in the headline (known abbreviations do not count).
    preg_match_all('/\b\p{Lu}{2,}\b/u', $item['title'], $m);
    $capsWords = array_diff($m[0], $TIER1_ACRONYMS);
    if (count($capsWords) >= 3) {
        $reasons[] = 'ALL-CAPS words (' . implode(', ', $capsWords) . ')';
    }

    return $reasons;
}
