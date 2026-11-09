<?php
// Run: php app/tests/test_sanitize.php
// Shows each fixture item before and after sanitizing, then checks the attack cases.
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/feed.php';
require __DIR__ . '/../includes/sanitize.php';

$items = array_merge(
    parse_source(file_get_contents(__DIR__ . '/fixtures/rss-sample.xml')),
    parse_source(file_get_contents(__DIR__ . '/fixtures/wordpress-sample.json'))
);

foreach ($items as $n => $raw) {
    $clean = sanitize_item($raw);
    echo '=== Item ', $n + 1, " ===\n";
    foreach (['title', 'summary', 'image', 'link'] as $field) {
        if ($raw[$field] !== $clean[$field]) {
            echo "  $field before: ", $raw[$field], "\n";
            echo "  $field after:  ", $clean[$field] === '' ? '(empty)' : $clean[$field], "\n";
        } else {
            echo "  $field unchanged\n";
        }
    }
}

// Pass/fail checks
$checks = [
    'script tag in title removed'         => clean_text("Governor <script>alert('xss')</script> opens new road", 300) === 'Governor opens new road',
    'double-encoded script removed'       => strpos(clean_text('&amp;lt;script&amp;gt;alert(1)&amp;lt;/script&amp;gt;Hi', 300), '<') === false,
    'onclick attribute removed'           => clean_text('<p onclick="alert(1)">Text</p>', 300) === 'Text',
    'style block removed'                 => clean_text('<style>body{display:none}</style>Text', 300) === 'Text',
    'javascript: image rejected'          => clean_url('javascript:alert(1)') === '',
    'data: image rejected'                => clean_url('data:image/svg+xml;base64,PHN2Zz4=') === '',
    'file: link rejected'                 => clean_url('file:///etc/passwd') === '',
    'https image kept'                    => clean_url('https://example.com/a.jpg') === 'https://example.com/a.jpg',
    'entities decoded'                    => clean_text('central bank&#8217;s rate', 300) === 'central bank’s rate',
    'WordPress footer removed'            => clean_text('<p>Story.</p><p>The post <a href="#">X</a> appeared first on <a href="#">Site</a>.</p>', 300) === 'Story.',
    'WordPress [...] ending removed'      => clean_text('<p>Story text [&hellip;]</p>', 300) === 'Story text',
    'long summary shortened'              => mb_strlen(clean_text(str_repeat('word ', 1000), 2000), 'UTF-8') <= 2000,
    '₦ and accents survive'               => clean_text('Adds ₦209bn in Ọ̀yọ́', 300) === 'Adds ₦209bn in Ọ̀yọ́',
];
echo "\n=== Checks ===\n";
$failed = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS  ' : 'FAIL  '), $name, "\n";
    $failed += $ok ? 0 : 1;
}
echo $failed ? "$failed check(s) failed\n" : "All checks passed\n";
exit($failed ? 1 : 0);
