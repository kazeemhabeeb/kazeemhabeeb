<?php
/*
 * Shared layout for the admin panel: Start Bootstrap "SB Admin" (MIT licence, admin/css/LICENSE-sb-admin.txt).
 * Icons: Font Awesome Free 6.3.0, served from admin/vendor/fontawesome.
 */

// A one-time message shown after a form is saved (the page then redirects, so a refresh
// cannot submit the form twice).
function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function show_flash()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alert alert-' . e($flash['type']) . '" role="alert">' . e($flash['message']) . '</div>';
}

function admin_header($title, $active)
{
    $links = [
        'dashboard' => ['dashboard.php', 'fa-tachometer-alt', 'Dashboard', 'Core'],
        'sources'   => ['sources.php', 'fa-rss', 'RSS Sources', 'Management'],
        'articles'  => ['articles.php', 'fa-newspaper', 'Articles', ''],
        'logs'      => ['logs.php', 'fa-list', 'System Logs', ''],
    ];
    $nav = '';
    foreach ($links as $key => $link) {
        if ($link[3] !== '') {
            $nav .= '<div class="sb-sidenav-menu-heading">' . $link[3] . '</div>';
        }
        $nav .= '<a class="nav-link' . ($key === $active ? ' active' : '') . '" href="' . $link[0] . '">'
              . '<div class="sb-nav-link-icon"><i class="fas ' . $link[1] . '"></i></div>' . $link[2] . '</a>';
    }
    $name = e($_SESSION['admin_name']);
    $site = e(SITE_NAME);
    $title = e($title);
    $csrf = e(csrf_token());          // read by admin JavaScript for AJAX actions
    $csrfField = csrf_field();

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="robots" content="noindex" />
        <meta name="csrf-token" content="{$csrf}" />
        <title>{$title} - {$site} Admin</title>
        <link href="css/styles.css" rel="stylesheet" />
        <link href="vendor/fontawesome/css/all.min.css" rel="stylesheet" />
    </head>
    <body class="sb-nav-fixed">
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
            <a class="navbar-brand ps-3" href="dashboard.php">{$site} Admin</a>
            <button class="btn btn-link btn-sm order-1 order-lg-0 me-4 me-lg-0" id="sidebarToggle" type="button" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></button>
            <ul class="navbar-nav ms-auto me-3 me-lg-4">
                <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank" rel="noopener"><i class="fas fa-external-link-alt fa-fw"></i> View site</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user fa-fw"></i> {$name}</a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><form method="post" action="logout.php">{$csrfField}<button class="dropdown-item" type="submit">Logout</button></form></li>
                    </ul>
                </li>
            </ul>
        </nav>
        <div id="layoutSidenav">
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu"><div class="nav">{$nav}</div></div>
                    <div class="sb-sidenav-footer"><div class="small">Logged in as:</div>{$name}</div>
                </nav>
            </div>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">{$title}</h1>
                        <ol class="breadcrumb mb-4"><li class="breadcrumb-item active">{$title}</li></ol>
HTML;
}

function admin_footer($scripts = [])
{
    $site = e(SITE_NAME);
    $year = date('Y');
    $extra = '';
    foreach ($scripts as $src) {
        $extra .= '<script src="' . e($src) . '"></script>';
    }
    return <<<HTML
                    </div>
                </main>
                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <div class="text-muted">Copyright &copy; {$site} {$year}</div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        <script src="js/bootstrap.bundle.min.js"></script>
        <script src="js/scripts.js"></script>
        {$extra}
    </body>
</html>
HTML;
}
