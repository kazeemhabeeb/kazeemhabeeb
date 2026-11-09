<?php
/*
 * Public news feed (FR-2.1: no login needed). Blog Home template.
 * The page itself is a shell: js/feed.js loads the articles and categories from api/articles.php
 * with AJAX/Fetch and refreshes them every 5 minutes without reloading the page (FR-2.3).
 * It does not touch the database, so the page still opens even if the database is down.
 */

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/public.php';

send_security_headers();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="<?= e(SITE_NAME) ?>: aggregated news with AI credibility scores" />
        <title>News Feed - <?= e(SITE_NAME) ?></title>
        <link rel="icon" href="data:," />
        <link href="css/styles.css" rel="stylesheet" />
    </head>
    <body>
        <?= render_nav([], '') ?>
        <!-- Page header -->
        <header class="py-5 bg-light border-bottom mb-4">
            <div class="container">
                <div class="text-center my-5">
                    <h1 class="fw-bolder"><?= e(SITE_NAME) ?></h1>
                    <p class="lead mb-0">Secure AI-Assisted Content Aggregation and Misinformation Detection System</p>
                </div>
            </div>
        </header>
        <div class="container">
            <div class="row">
                <!-- News feed -->
                <div class="col-lg-8">
                    <h2 class="h4 mb-3" id="feedHeading">Latest News</h2>
                    <div id="feed" aria-live="polite">
                        <noscript><div class="alert alert-info">Please turn on JavaScript to load the news feed.</div></noscript>
                    </div>
                </div>
                <!-- Side widgets: categories are filled in by feed.js -->
                <div class="col-lg-4">
                    <?= render_sidebar([], '') ?>
                </div>
            </div>
        </div>
        <?= render_footer() ?>
        <script src="js/feed.js"></script>
    </body>
</html>
