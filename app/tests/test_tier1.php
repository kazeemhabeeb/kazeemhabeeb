<?php
// Run: php app/tests/test_tier1.php
// Runs tier 1 on the fixture items, then on extra cases chosen to test each rule's edges.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/feed.php';
require __DIR__ . '/../includes/sanitize.php';
require __DIR__ . '/../includes/tier1.php';

function item($title, $summary = '', $category = '', $link = 'https://example.com/a')
{
    return sanitize_item(['title' => $title, 'link' => $link, 'summary' => $summary,
                          'date' => '', 'category' => $category, 'image' => '']);
}

function show($label, $item)
{
    $r = tier1_check($item);
    if ($r['spam'] !== '') {
        $verdict = 'SPAM (' . $r['spam'] . ')';
    } elseif ($r['clickbait']) {
        $verdict = 'FLAGGED (' . implode('; ', $r['clickbait_reasons']) . ')';
    } else {
        $verdict = 'OK';
    }
    printf("%-58s %s\n", mb_strimwidth($label, 0, 56, '…', 'UTF-8'), $verdict);
    return $verdict;
}

echo "=== Fixture items ===\n";
$items = array_merge(
    parse_source(file_get_contents(__DIR__ . '/fixtures/rss-sample.xml')),
    parse_source(file_get_contents(__DIR__ . '/fixtures/wordpress-sample.json'))
);
foreach ($items as $raw) {
    $clean = sanitize_item($raw);
    show($clean['title'] === '' ? '(no title)' : $clean['title'], $clean);
}

echo "\n=== Edge cases (expected result in brackets) ===\n";
$cases = [
    ['[OK] news headline using the word "promoted"',
        item('Police officers promoted to new ranks in Lagos')],
    ['[SPAM] "Promoted:" at the start',
        item('Promoted: Best loan app in Nigeria')],
    ['[SPAM] category is Advertorial',
        item('Ten reasons to visit our new mall', '', 'Advertorial')],
    ['[OK] headline full of abbreviations',
        item('NNPC, CBN and EFCC meet over fuel subsidy')],
    ['[FLAGGED] three shouting words',
        item('BREAKING NEWS: GOVERNOR ARRESTED at airport')],
    ['[FLAGGED] curly apostrophe phrase',
        item('You won’t believe what this pastor said')],
    ['[OK] one exclamation mark',
        item('Super Eagles qualify for the World Cup!')],
    ['[SPAM] French summary',
        item('Le président annonce une réforme',
             'Le président de la République a annoncé mardi une réforme importante des retraites, selon un communiqué publié par le gouvernement.')],
    ['[SPAM] Arabic text',
        item('الرئيس يعلن عن إصلاح جديد في البلاد', 'أعلن الرئيس اليوم عن خطة جديدة للإصلاح الاقتصادي في جميع أنحاء البلاد')],
    ['[OK] Pidgin English summary',
        item('Fuel price go up again for Lagos',
             'Motorists for Lagos dey complain as fuel price go up again, and the government say the price will come down soon for the people.')],
    ['[OK] real BusinessDay article with few common words (9 Nov 2026)',
        item('Kaduna’s dairy revolution targets Nigeria’s $1.5bn milk import market',
             'Kaduna State’s emerging dairy industry could help Nigeria reduce its annual expenditure of more than $1.5 billion on imported milk')],
    ['[SPAM] Spanish summary',
        item('El presidente anuncia una reforma',
             'El presidente del gobierno anunció el martes una importante reforma de las pensiones, según un comunicado oficial publicado por el ministerio.')],
    ['[OK] headline only, no summary (too short to judge language)',
        item('Starbucks Announces New Teen-Free Hours For Nervous Adults')],
];
$wrong = 0;
foreach ($cases as $case) {
    $verdict = show($case[0], $case[1]);
    preg_match('/^\[(\w+)\]/', $case[0], $want);
    if (strpos($verdict, $want[1]) !== 0) {
        $wrong++;
        echo "  ^ expected {$want[1]}\n";
    }
}
echo $wrong ? "\n$wrong case(s) gave an unexpected result\n" : "\nAll edge cases gave the expected result\n";
exit($wrong ? 1 : 0);
