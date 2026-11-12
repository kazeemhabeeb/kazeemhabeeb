<?php
/*
 * One-off clean-up: remove paid posts that were saved before the tier-1 paid-content rule caught
 * categories such as "Promoted Stories" (decision 28, 12 November 2026).
 * Uses the same rule as ingestion (tier1_spam_reason), so it removes exactly what tier 1 now rejects.
 *
 *   /opt/alt/php83/usr/bin/php ~/trustfeed_app/tools/remove_paid_posts.php            (list only)
 *   /opt/alt/php83/usr/bin/php ~/trustfeed_app/tools/remove_paid_posts.php --delete   (delete them)
 *
 * Each deleted article is recorded in the logs as "Spam filtered".
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this from the command line.');
}
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/tier1.php';

$delete = in_array('--delete', $argv, true);
$db = db_connect();

// Only articles whose title or category could match are checked.
$rows = mysqli_fetch_all(mysqli_query($db,
    "SELECT a.article_id, a.title, a.category, a.original_link, s.website_name
       FROM articles a JOIN rss_sources s ON s.source_id = a.source_id
      WHERE a.category LIKE '%sponsored%' OR a.category LIKE '%advertorial%' OR a.category LIKE '%promoted%'
         OR a.category LIKE '%paid post%' OR a.title LIKE '%sponsored%' OR a.title LIKE '%advertorial%'
         OR a.title LIKE '%promoted%' OR a.title LIKE '%paid post%'
      ORDER BY a.article_id"), MYSQLI_ASSOC);

$paid = [];
foreach ($rows as $row) {
    $reason = tier1_spam_reason([
        'title'    => $row['title'],
        'summary'  => '',
        'link'     => $row['original_link'],
        'category' => (string) $row['category'],
    ]);
    if (strpos($reason, 'marked ') === 0) {
        $row['reason'] = $reason;
        $paid[] = $row;
    }
}

if (!$paid) {
    exit("No paid posts found.\n");
}
foreach ($paid as $row) {
    printf("#%d  %s  [%s, %s]\n", $row['article_id'], mb_strimwidth($row['title'], 0, 70, '…', 'UTF-8'),
           $row['website_name'], $row['category']);
}
echo count($paid), " paid post(s) found.\n";

if (!$delete) {
    exit("Nothing deleted. Run again with --delete to remove them.\n");
}

$stmt = mysqli_prepare($db, 'DELETE FROM articles WHERE article_id = ?');
foreach ($paid as $row) {
    mysqli_stmt_bind_param($stmt, 'i', $row['article_id']);
    mysqli_stmt_execute($stmt);
    write_log($db, 'Spam filtered', "Clean-up: article #{$row['article_id']} \"" . mb_strimwidth($row['title'], 0, 80, '…', 'UTF-8')
              . "\" from {$row['website_name']} deleted ({$row['reason']}, category \"{$row['category']}\") <{$row['original_link']}>.");
}
echo count($paid), " paid post(s) deleted and logged as Spam filtered.\n";
