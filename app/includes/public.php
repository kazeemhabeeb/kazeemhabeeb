<?php
/*
 * Shared pieces of the public site (Presentation & UI module, §3.7 module 4).
 * Layout from Start Bootstrap "Blog Home" (MIT licence, css/LICENSE-blog-home.txt).
 */

define('FEED_PAGE_SIZE', 5);        // articles per feed page (NFR-4.2: small pages load fast)
define('SIDEBAR_CATEGORIES', 10);   // categories listed in the sidebar
define('NAV_CATEGORIES', 4);        // categories listed in the top bar

// Extra browser protection on every public response (§3.8, NFR-1.1).
// The Content-Security-Policy only lets the page run script files from this site (no inline scripts),
// so even if a script tag ever got past sanitizing, the browser would refuse to run it.
function send_security_headers()
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
         . "style-src 'self'; img-src 'self' https: http: data:; connect-src 'self'; "
         . "frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// The most used categories, most articles first (FR-2.3).
function top_categories($db, $limit)
{
    $stmt = mysqli_prepare($db,
        'SELECT category, COUNT(*) AS total FROM articles
          WHERE category IS NOT NULL GROUP BY category ORDER BY total DESC, category LIMIT ?');
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    return array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'category');
}

// Credibility badge: label and percentage (FR-2.2) in green, red or yellow (NFR-3.2).
// A label set by the admin has no AI score, so no percentage is shown (decision 16).
function badge_html($status, $score)
{
    if ($status === 'Real') {
        $class = 'bg-success';
    } elseif ($status === 'Misinformation') {
        $class = 'bg-danger';
    } else {
        return '<span class="badge bg-warning text-dark">Pending Verification</span>';
    }
    $percent = $score === null ? '' : ' &ndash; ' . (int) $score . '% Confidence';
    return '<span class="badge ' . $class . '">' . e($status) . $percent . '</span>';
}

// "November 9, 2026", or '' when the feed gave no date.
function nice_date($datetime)
{
    return $datetime ? date('F j, Y', strtotime($datetime)) : '';
}

function category_url($category)
{
    return 'index.php' . ($category === '' ? '' : '?category=' . rawurlencode($category));
}

// Top navigation bar. On the feed page the category links are filled in by feed.js.
function render_nav($categories, $active)
{
    $html = '<nav class="navbar navbar-expand-lg navbar-dark bg-dark"><div class="container">'
          . '<a class="navbar-brand" href="index.php">' . e(SITE_NAME) . '</a>'
          . '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" '
          . 'aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>'
          . '<div class="collapse navbar-collapse" id="navbarSupportedContent">'
          . '<ul class="navbar-nav ms-auto mb-2 mb-lg-0" id="navCategories">'
          . '<li class="nav-item"><a class="nav-link' . ($active === '' ? ' active' : '') . '" href="index.php">Home</a></li>';
    foreach ($categories as $c) {
        $html .= '<li class="nav-item"><a class="nav-link' . ($active === $c ? ' active' : '') . '" href="'
               . e(category_url($c)) . '">' . e($c) . '</a></li>';
    }
    return $html . '</ul></div></div></nav>';
}

// Sidebar: Categories widget (FR-2.3) and the colour key (NFR-3.2).
function render_sidebar($categories, $active)
{
    $list = array_merge([''], $categories);
    $half = (int) ceil(count($list) / 2);
    $columns = '';
    foreach ([array_slice($list, 0, $half), array_slice($list, $half)] as $column) {
        $columns .= '<div class="col-sm-6"><ul class="list-unstyled mb-0">';
        foreach ($column as $c) {
            $columns .= '<li><a href="' . e(category_url($c)) . '"' . ($active === $c ? ' class="fw-bold text-dark"' : '')
                      . '>' . ($c === '' ? 'All' : e($c)) . '</a></li>';
        }
        $columns .= '</ul></div>';
    }
    return '<div class="card mb-4"><div class="card-header">Categories</div>'
         . '<div class="card-body"><div class="row" id="categoryWidget">' . $columns . '</div></div></div>'
         . '<div class="card mb-4"><div class="card-header">Credibility Labels</div><div class="card-body">'
         . '<ul class="list-unstyled mb-0">'
         . '<li class="mb-2"><span class="badge bg-success">Real</span> Confidence score of ' . VERDICT_THRESHOLD . '% or higher</li>'
         . '<li class="mb-2"><span class="badge bg-danger">Misinformation</span> Confidence score below ' . VERDICT_THRESHOLD . '%</li>'
         . '<li><span class="badge bg-warning text-dark">Pending Verification</span> Not yet verified</li>'
         . '</ul></div></div>';
}

function render_footer()
{
    return '<footer class="py-5 bg-dark"><div class="container"><p class="m-0 text-center text-white">Copyright &copy; '
         . e(SITE_NAME) . ' ' . date('Y') . '</p></div></footer>'
         . '<script src="js/bootstrap.bundle.min.js"></script>';   // Bootstrap 5.2.3, served from this site
}
