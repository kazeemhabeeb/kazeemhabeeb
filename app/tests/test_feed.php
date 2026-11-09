<?php
// Run: php app/tests/test_feed.php
// Reads the saved fixtures with the same functions the cron script uses.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/feed.php';

foreach (['rss-sample.xml', 'wordpress-sample.json'] as $file) {
    $body = file_get_contents(__DIR__ . '/fixtures/' . $file);
    echo "=== $file (format: " . detect_format($body) . ") ===\n";
    var_export(parse_source($body));
    echo "\n\n";
}

echo "=== Not a feed ===\n";
var_export(parse_source('Service unavailable'));
echo "\n\n=== API URL without _embed ===\n";
echo prepare_source_url('https://businessday.ng/wp-json/wp/v2/posts'), "\n";
echo prepare_source_url('https://businessday.ng/wp-json/wp/v2/posts?per_page=20'), "\n";
