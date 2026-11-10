<?php
/*
 * Live check of the web-search second opinion (decision 32). Run on the server over SSH:
 *   /opt/alt/php83/usr/bin/php ~/trustfeed_app/tools/test_web_check.php
 * Sends 4 sample articles (2 real, 2 invented) to Search GPT and prints the scores.
 * Nothing is saved to the database. Uses about 50,000-120,000 Search GPT tokens.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/sanitize.php';
require __DIR__ . '/../includes/ai.php';

if (!second_opinion_enabled()) {
    exit("The second opinion is off: add SEARCH_API_HOST and SEARCH_API_URL to config/config.php first.\n");
}

$cases = [
    ['REAL', 'Erika Kirk condemns jokes on Charlie’s assassination',
     "Erika Kirk, widow of conservative activist Charlie Kirk, has strongly condemned making light of political violence, following a controversial routine by comedian Dave Chappelle about her husband's assassination.",
     'https://pmnewsnigeria.com/2026/11/10/erika-kirk-condemns-jokes-on-charlies-assassination/', '2026-11-10 12:00:00'],
    ['REAL', 'I sold arms to anyone with money — Kalu',
     'Senator Orji Kalu has disclosed that he previously traded in heavy weapons internationally, selling to any buyer with money before his parents made him stop.',
     'https://punchng.com/i-sold-arms-to-anyone-with-money-kalu/', '2026-11-10 07:45:24'],
    ['FAKE', 'Former President Olusegun Obasanjo dies at 89',
     'Former President Olusegun Obasanjo has died at the age of 89, a family source told our correspondent on Tuesday.',
     'https://naijagistnow.blogspot.com/2026/11/obasanjo-dies.html', '2026-11-10 10:00:00'],
    ['FAKE', 'I will hand over to Peter Obi next week — Tinubu',
     'President Bola Tinubu has told aides he will resign next week and hand power to Peter Obi, saying he is tired of governing Nigeria. Share this before it is deleted!',
     'https://naijagistnow.blogspot.com/2026/11/tinubu-hand-over-obi.html', '2026-11-10 09:00:00'],
];

$tokens = 0;
foreach ($cases as $i => $c) {
    if ($i > 0) {
        sleep(AI_CALL_DELAY_SECONDS);
    }
    $start = microtime(true);
    $reply = call_web_ai(build_prompt(['title' => $c[1], 'content_summary' => $c[2], 'original_link' => $c[3], 'publication_date' => $c[4]], true));
    $analysis = $reply['ok'] ? parse_ai_reply($reply['text']) : null;
    printf("[%s] %s\n", $c[0], $c[1]);
    if (!is_array($analysis)) {
        echo '   FAILED: ', $reply['ok'] ? 'unusable reply (' . $analysis . ')' : $reply['error'], "\n\n";
        continue;
    }
    $tokens += $reply['tokens'];
    printf("   %d -> %s   (%.1f s, %d tokens)\n   %s\n\n", $analysis['score'], classify_score($analysis['score']),
           microtime(true) - $start, $reply['tokens'], $analysis['reason']);
}
echo "Total tokens: $tokens\n";
