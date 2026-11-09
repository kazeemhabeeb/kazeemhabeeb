<?php
/*
 * Article list for the admin Articles page (FR-3.3 search), loaded with AJAX.
 *   GET api/articles.php?q=naira&page=1
 * Searches the title and the source name; newest first; 10 per page.
 */

require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/public.php';
require __DIR__ . '/../../../includes/auth.php';

send_security_headers();
require_admin();
header('Content-Type: application/json; charset=utf-8');

define('ADMIN_PAGE_SIZE', 10);

$q    = mb_substr(trim((string) filter_input(INPUT_GET, 'q')), 0, 100, 'UTF-8');
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);

// "%" and "_" typed by the admin are searched for literally, not used as wildcards.
$like = '%' . addcslashes($q, '%_\\') . '%';

$db = db_connect();
$where = 'FROM articles a JOIN rss_sources s ON s.source_id = a.source_id WHERE a.title LIKE ? OR s.website_name LIKE ?';

$stmt = mysqli_prepare($db, "SELECT COUNT(*) $where");
mysqli_stmt_bind_param($stmt, 'ss', $like, $like);
mysqli_stmt_execute($stmt);
$total = (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];

$pages  = max(1, (int) ceil($total / ADMIN_PAGE_SIZE));
$page   = min($page, $pages);
$offset = ($page - 1) * ADMIN_PAGE_SIZE;
$limit  = ADMIN_PAGE_SIZE;

$stmt = mysqli_prepare($db,
    "SELECT a.article_id, a.title, a.publication_date, a.confidence_score, a.verification_status, a.view_count,
            s.website_name
     $where ORDER BY a.publication_date DESC, a.article_id DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, 'ssii', $like, $like, $limit, $offset);
mysqli_stmt_execute($stmt);

$articles = [];
foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $r) {
    $articles[] = [
        'id'     => (int) $r['article_id'],
        'title'  => $r['title'],
        'source' => $r['website_name'],
        'date'   => nice_date($r['publication_date']),
        'status' => $r['verification_status'],
        'score'  => $r['confidence_score'] === null ? null : (int) $r['confidence_score'],
        'views'  => (int) $r['view_count'],
    ];
}

echo json_encode(['articles' => $articles, 'total' => $total, 'page' => $page, 'pages' => $pages],
                 JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
