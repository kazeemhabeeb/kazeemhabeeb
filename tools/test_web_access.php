<?php
/*
 * Check which PR Labs "ChatGPT 4" endpoints can really search the web.
 *
 * Usage:
 *   php tools/test_web_access.php                       (key added by a network secret)
 *   RAPIDAPI_KEY=xxxx php tools/test_web_access.php     (key from an environment variable)
 *
 * Each endpoint gets the same question, which can only be answered with a live
 * web search. Every request costs 15 credits (4 endpoints = 60 credits).
 * The key comes from a network secret (X-RapidAPI-Key header) or the RAPIDAPI_KEY
 * environment variable, never from this file.
 */

// Confirm this against the X-RapidAPI-Host value in the listing's Code snippet tab.
$host = 'chatgpt-42.p.rapidapi.com';
$endpoints = array('/gpt4', '/gpt4o', '/gpt5', '/conversationgpt4-2');

$question = 'Search the web now. Give the exact headline and full URL of one news article '
          . 'published in the last 2 days on punchng.com. Reply with only the headline and the URL.';

// With a Claude Code network secret the X-RapidAPI-Key header is added on the way out,
// so the key may be absent here; locally, set RAPIDAPI_KEY instead.
$key = getenv('RAPIDAPI_KEY');

$body = json_encode(array(
    'messages'   => array(array('role' => 'user', 'content' => $question)),
    'web_access' => true,
));

foreach ($endpoints as $path) {
    $ch = curl_init('https://' . $host . $path);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $headers = array('Content-Type: application/json', 'X-RapidAPI-Host: ' . $host);
    if ($key !== false && $key !== '') {
        $headers[] = 'X-RapidAPI-Key: ' . $key;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $start = microtime(true);
    $response = curl_exec($ch);
    $seconds = round(microtime(true) - $start, 1);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "=== $path  (HTTP $status, {$seconds}s)\n";
    if ($response === false) {
        echo "cURL error: $error\n\n";
        continue;
    }
    $json = json_decode($response, true);
    echo (is_array($json) && isset($json['result']) ? $json['result'] : $response) . "\n\n";

    sleep(1); // Pro plan allows 1 request per second
}
