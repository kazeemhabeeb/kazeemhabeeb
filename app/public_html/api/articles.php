<?php
/*
 * Feed data for the public site, loaded by js/feed.js with AJAX/Fetch (FR-2.3).
 *   GET api/articles.php?page=1&category=Business
 * Returns one page of articles (newest first), the page count and the category lists.
 */

require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/sanitize.php';
require __DIR__ . '/../../includes/public.php';

send_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$page     = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
$category = trim((string) filter_input(INPUT_GET, 'category'));
$category = mb_substr($category, 0, 100, 'UTF-8');

try {
    $db = db_connect();

    // How many articles match, so the page links can be drawn.
    if ($category === '') {
        $total = (int) mysqli_fetch_row(mysqli_query($db, 'SELECT COUNT(*) FROM articles'))[0];
    } else {
        $stmt = mysqli_prepare($db, 'SELECT COUNT(*) FROM articles WHERE category = ?');
        mysqli_stmt_bind_param($stmt, 's', $category);
        mysqli_stmt_execute($stmt);
        $total = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    }
    $pages = max(1, (int) ceil($total / FEED_PAGE_SIZE));
    $page  = min($page, $pages);
    $offset = ($page - 1) * FEED_PAGE_SIZE;
    $limit  = FEED_PAGE_SIZE;

    // One page of articles, newest first (uses the publication_date index, NFR-4.2).
    $sql = 'SELECT a.article_id, a.title, a.content_summary, a.publication_date, a.category, a.image_url,
                   a.confidence_score, a.verification_status, a.is_clickbait, a.emotional_manipulation,
                   a.bias_level, s.website_name
              FROM articles a JOIN rss_sources s ON s.source_id = a.source_id'
         . ($category === '' ? '' : ' WHERE a.category = ?')
         . ' ORDER BY a.publication_date DESC, a.article_id DESC LIMIT ? OFFSET ?';
    $stmt = mysqli_prepare($db, $sql);
    if ($category === '') {
        mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    } else {
        mysqli_stmt_bind_param($stmt, 'sii', $category, $limit, $offset);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

    $articles = [];
    foreach ($rows as $r) {
        // §3.1.1 alert indicators, shown on the card only when a flag is set (decision 19).
        $alerts = [];
        if ((int) $r['is_clickbait'] === 1) {
            $alerts[] = 'Clickbait';
        }
        if ((int) $r['emotional_manipulation'] === 1) {
            $alerts[] = 'Emotional manipulation';
        }
        if ($r['bias_level'] === 'high') {
            $alerts[] = 'High bias';
        }
        $articles[] = [
            'id'       => (int) $r['article_id'],
            'title'    => $r['title'],
            'summary'  => shorten((string) $r['content_summary'], 220),
            'date'     => nice_date($r['publication_date']),
            'source'   => $r['website_name'],
            'category' => (string) $r['category'],
            'image'    => (string) $r['image_url'],
            'status'   => $r['verification_status'],
            'score'    => $r['confidence_score'] === null ? null : (int) $r['confidence_score'],
            'alerts'   => $alerts,
        ];
    }

    echo json_encode([
        'articles'   => $articles,
        'page'       => $page,
        'pages'      => $pages,
        'category'   => $category,
        'nav'        => top_categories($db, NAV_CATEGORIES),
        'categories' => top_categories($db, SIDEBAR_CATEGORIES),
        'threshold'  => VERDICT_THRESHOLD,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (mysqli_sql_exception $ex) {
    error_log('TrustFeed api/articles.php: ' . $ex->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'News could not be loaded.']);
}
