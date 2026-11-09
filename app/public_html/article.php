<?php
/*
 * Article details page: use cases "Read Article Summary" and "View Credibility Score" (p. 35).
 * Each visit adds 1 to view_count (FR-2.4). Shows the verdict, "Why this score" with the AI's
 * reason and flags (§3.1.1), the summary, and a link to the full story (original_link).
 */

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public.php';

send_security_headers();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$article = null;
$categories = [];

try {
    $db = db_connect();
    if ($id) {
        // FR-2.4: record the view, then read the article.
        $stmt = mysqli_prepare($db, 'UPDATE articles SET view_count = view_count + 1 WHERE article_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($db,
            'SELECT a.*, s.website_name FROM articles a JOIN rss_sources s ON s.source_id = a.source_id
              WHERE a.article_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $article = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    }
    $navCategories = top_categories($db, NAV_CATEGORIES);
    $categories = top_categories($db, SIDEBAR_CATEGORIES);
} catch (mysqli_sql_exception $ex) {
    error_log('TrustFeed article.php: ' . $ex->getMessage());
    http_response_code(503);
    exit('The site is temporarily unavailable. Please try again in a few minutes.');
}

if (!$article) {
    http_response_code(404);
}
$active = $article ? (string) $article['category'] : '';
$source = $article ? $article['website_name'] : '';
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <title><?= $article ? e($article['title']) : 'Article not found' ?> - <?= e(SITE_NAME) ?></title>
        <link rel="icon" href="data:," />
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body>
        <?= render_nav($navCategories, $active) ?>
        <div class="container mt-5">
            <div class="row">
                <div class="col-lg-8">
<?php if (!$article): ?>
                    <h1 class="fw-bolder mb-3">Article not found</h1>
                    <p>This article does not exist or has been removed.</p>
<?php else: ?>
                    <article>
                        <header class="mb-4">
                            <h1 class="fw-bolder mb-1"><?= e($article['title']) ?></h1>
                            <div class="text-muted fst-italic mb-2">
                                Posted<?= $article['publication_date'] ? ' on ' . e(nice_date($article['publication_date'])) : '' ?> by <?= e($source) ?>
                            </div>
<?php if ($article['category'] !== null): ?>
                            <a class="badge bg-secondary text-decoration-none link-light" href="<?= e(category_url($article['category'])) ?>"><?= e($article['category']) ?></a>
<?php endif; ?>
                        </header>
                        <figure class="mb-4">
                            <img class="img-fluid rounded" src="<?= e($article['image_url'] ?: 'assets/placeholder-850x350.svg') ?>" alt="" referrerpolicy="no-referrer" />
                        </figure>

<?php // Verdict box (FR-2.2, NFR-3.2) ?>
<?php if ($article['verification_status'] === 'Real' || $article['verification_status'] === 'Misinformation'): ?>
                        <div class="alert <?= $article['verification_status'] === 'Real' ? 'alert-success' : 'alert-danger' ?>">
                            <strong><?= e($article['verification_status']) ?><?= $article['confidence_score'] !== null ? ' &ndash; ' . (int) $article['confidence_score'] . '% Confidence' : '' ?></strong>
                        </div>

<?php // "Why this score" (§3.1.1 explainable verification flags) ?>
                        <div class="card mb-4">
                            <div class="card-header fw-bold">Why this score</div>
                            <div class="card-body">
<?php if ($article['verification_reason'] !== null): ?>
                                <p class="mb-2"><?= e($article['verification_reason']) ?></p>
                                <ul class="list-unstyled mb-0">
                                    <li class="mb-1">Clickbait: <?= (int) $article['is_clickbait'] === 1 ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></li>
                                    <li class="mb-1">Emotional manipulation: <?= (int) $article['emotional_manipulation'] === 1 ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></li>
<?php $bias = (string) $article['bias_level']; $biasClass = $bias === 'high' ? 'bg-danger' : ($bias === 'low' ? 'bg-warning text-dark' : 'bg-secondary'); ?>
                                    <li>Bias: <span class="badge <?= $biasClass ?>"><?= e(ucfirst($bias)) ?></span></li>
                                </ul>
<?php else: ?>
                                <p class="mb-0">This label was set by an administrator.</p>
<?php endif; ?>
                            </div>
                        </div>
<?php else: ?>
                        <div class="alert alert-warning"><strong>Pending Verification.</strong> This article has not been verified yet.</div>
<?php endif; ?>

                        <section class="mb-5">
                            <p class="fs-5 mb-4"><?= $article['content_summary'] !== null ? e($article['content_summary']) : '<span class="text-muted">No summary was provided in the feed.</span>' ?></p>
                            <a class="btn btn-primary" href="<?= e($article['original_link']) ?>" target="_blank" rel="noopener noreferrer">Read the full story on <?= e($source) ?></a>
                        </section>
                    </article>
<?php endif; ?>
                    <p><a href="index.php">&larr; Back to all news</a></p>
                </div>
                <div class="col-lg-4">
                    <?= render_sidebar($categories, $active) ?>
                </div>
            </div>
        </div>
        <?= render_footer() ?>
        <script src="js/article.js"></script>
    </body>
</html>
