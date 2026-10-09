<?php
/*
 * Check which PR Labs "ChatGPT 4" endpoints can really search the web.
 *
 * Usage:
 *   RAPIDAPI_KEY=xxxx php tools/test_web_access.php
 *
 * Each endpoint gets the same question, which can only be answered with a live
 * web search. Every request costs 15 credits (4 endpoints = 60 credits).
 * The key is read from the RAPIDAPI_KEY environment variable, never from this file.
 */

// Confirm this against the X-RapidAPI-Host value in the listing's Code snippet tab.
$host = 'chatgpt-42.p.rapidapi.com';
$endpoints = array('/gpt4', '/gpt4o', '/gpt5', '/conversationgpt4-2');

$question = 'Search the web now. Give the exact headline and full URL of one news article '
          . 'published in the last 2 days on punchng.com. Reply with only the headline and the URL.';

$key = getenv('RAPIDAPI_KEY');
if ($key === false || $key === '') {
    fwrite(STDERR, "Set the RAPIDAPI_KEY environment variable first.\n");
    exit(1);
}

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
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-RapidAPI-Key: ' . $key,
        'X-RapidAPI-Host: ' . $host,
    ));

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
