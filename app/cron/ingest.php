<?php
/*
 * Hourly ingestion script (FR-1.1; flowchart Figure 3.2, p. 36; §3.7 module 1).
 * cPanel runs it every hour:  php /home/USER/app/cron/ingest.php
 *
 *   1. Trigger      cron starts this script
 *   2. Extraction   read every Active source (RSS feed or news API)
 *   3. Validation   skip articles whose link is already saved (NFR-2.2)
 *   4. Sanitization clean every field (sanitize.php), then tier 1 (tier1.php, §2.5.1)
 *   5-6. AI dispatch and classification: added in Sprint 2
 *   7. Persistence  save new articles as Pending Verification, then log the run (FR-3.5)
 */

// §3.8: this script may only be started by cron or from the command line, never from a browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/feed.php';
require __DIR__ . '/../includes/sanitize.php';
require __DIR__ . '/../includes/tier1.php';

// Stop two runs overlapping if one ever takes longer than an hour.
$lock = fopen(sys_get_temp_dir() . '/trustfeed-ingest.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Another ingestion run is still in progress.\n");
}

try {
    $db = db_connect();
} catch (mysqli_sql_exception $ex) {
    error_log('TrustFeed ingestion: cannot connect to the database: ' . $ex->getMessage());
    exit(1);
}

$runStart = microtime(true);
$count = ['sources' => 0, 'failed' => 0, 'new' => 0, 'flagged' => 0, 'duplicates' => 0, 'spam' => 0];
$slowest = ['name' => '', 'process' => 0.0, 'download' => 0.0];

// Prepared statements, reused for every article (NFR-1.1, §3.8).
$findLink = mysqli_prepare($db, 'SELECT 1 FROM articles WHERE original_link = ? LIMIT 1');
$insert = mysqli_prepare($db,
    'INSERT INTO articles (source_id, title, content_summary, original_link, publication_date,
                           category, image_url, is_clickbait)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$findSpamLog = mysqli_prepare($db,
    "SELECT 1 FROM logs WHERE log_type = 'Spam filtered' AND message LIKE ? LIMIT 1");

// Step 2: every Active source.
$sources = mysqli_query($db, "SELECT source_id, website_name, rss_url FROM rss_sources WHERE status = 'Active' ORDER BY source_id");

while ($source = mysqli_fetch_assoc($sources)) {
    $count['sources']++;
    $name = $source['website_name'];

    $downloadStart = microtime(true);
    $download = fetch_url(prepare_source_url($source['rss_url']));
    $downloadTime = microtime(true) - $downloadStart;

    if (!$download['ok']) {
        $count['failed']++;
        write_log($db, 'RSS feed failed', "Could not fetch $name ({$source['rss_url']}): {$download['error']}. Source skipped.");
        continue;
    }

    $processStart = microtime(true);
    $items = parse_source($download['body']);
    if ($items === null) {
        $count['failed']++;
        write_log($db, 'RSS feed failed', "$name ({$source['rss_url']}) did not return a readable RSS feed or news API reply. Source skipped.");
        continue;
    }

    foreach ($items as $raw) {
        $item = sanitize_item($raw);      // step 4: sanitization
        $check = tier1_check($item);      // tier 1 (§2.5.1)

        if ($check['spam'] !== '') {
            // Spam is not saved, so it reappears every hour while it is in the feed: log it only once.
            $alreadyLogged = false;
            if ($item['link'] !== '') {
                $pattern = '%' . $item['link'] . '%';
                mysqli_stmt_bind_param($findSpamLog, 's', $pattern);
                mysqli_stmt_execute($findSpamLog);
                $alreadyLogged = mysqli_stmt_get_result($findSpamLog)->num_rows > 0;
            }
            if (!$alreadyLogged) {
                $count['spam']++;
                $title = $item['title'] === '' ? '(no title)' : '"' . $item['title'] . '"';
                $where = $item['link'] === '' ? '' : " <{$item['link']}>";
                write_log($db, 'Spam filtered', "Tier 1: item $title from $name not saved ({$check['spam']})$where.");
            }
            continue;
        }

        // Step 3: validation, skip links that are already saved (NFR-2.2).
        mysqli_stmt_bind_param($findLink, 's', $item['link']);
        mysqli_stmt_execute($findLink);
        if (mysqli_stmt_get_result($findLink)->num_rows > 0) {
            $count['duplicates']++;
            continue;
        }

        // Step 7: persistence. Status defaults to Pending Verification and the score stays empty (NFR-2.1).
        $summary   = $item['summary'] === '' ? null : $item['summary'];
        $date      = $item['date'] === '' ? null : $item['date'];
        $category  = $item['category'] === '' ? null : $item['category'];
        $image     = $item['image'] === '' ? null : $item['image'];
        $clickbait = $check['clickbait'] ? 1 : null;
        mysqli_stmt_bind_param($insert, 'issssssi', $source['source_id'], $item['title'], $summary,
                               $item['link'], $date, $category, $image, $clickbait);
        try {
            mysqli_stmt_execute($insert);
            $count['new']++;
            if ($check['clickbait']) {
                $count['flagged']++;
            }
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {
                $count['duplicates']++;   // the same link appeared twice in one feed
            } else {
                throw $ex;
            }
        }
    }

    // NFR-4.1 evidence: time to process, sanitize and save this source (download time shown separately).
    $processTime = microtime(true) - $processStart;
    if ($processTime >= $slowest['process']) {
        $slowest = ['name' => $name, 'process' => $processTime, 'download' => $downloadTime];
    }
}

// Steps 5-6 (AI dispatch and classification) are added here in Sprint 2.

$summary = sprintf(
    'Hourly run finished in %.1f s: %d active sources read (%d failed), %d new article(s) saved (%d flagged as clickbait by tier 1), %d duplicate link(s) skipped, %d spam filtered.',
    microtime(true) - $runStart, $count['sources'], $count['failed'], $count['new'],
    $count['flagged'], $count['duplicates'], $count['spam']
);
if ($slowest['name'] !== '') {
    $summary .= sprintf(' Slowest source: %s, %.3f s to process and save (+%.2f s download).',
                        $slowest['name'], $slowest['process'], $slowest['download']);
}
write_log($db, 'Execution', $summary);
echo $summary, "\n";
