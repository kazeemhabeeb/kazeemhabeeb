<?php
// Fake feed server for the security tests. Start: php -S 127.0.0.1:8096 app/tests/mock_feed_server.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rss = '<?xml version="1.0"?><rss version="2.0"><channel><title>T</title><item><title>Hello</title><link>https://example.com/a</link></item></channel></rss>';
switch ($path) {
    case '/feed':            header('Content-Type: application/rss+xml'); echo $rss; break;
    case '/redirect':        header('Location: /feed', true, 302); break;
    case '/redirect-loop':   header('Location: /redirect-loop', true, 302); break;
    case '/redirect-file':   header('Location: file:///etc/passwd', true, 302); break;
    case '/big':             header('Content-Type: application/rss+xml'); echo str_repeat('<!-- padding -->', 400000); break; // 6.4 MB
    case '/zip-bomb':        header('Content-Encoding: gzip'); echo gzencode(str_repeat("\0", 50 * 1024 * 1024), 9); break; // ~50 KB that unzips to 50 MB
    default:                 http_response_code(404);
}
