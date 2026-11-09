<?php
/*
 * Test a RapidAPI fact-checking endpoint from the command line.
 *
 * Usage:
 *   RAPIDAPI_KEY=xxxx php tools/test_rapidapi.php "text or claim to check"
 *
 * Fill in the three settings below from the "Endpoints" tab of the RapidAPI
 * listing you subscribed to. The key is read from the RAPIDAPI_KEY environment
 * variable so it is never saved in this file or committed to Git.
 */

// ---- settings from the RapidAPI listing ----
$host   = 'REPLACE-WITH-HOST.p.rapidapi.com';  // the X-RapidAPI-Host value
$path   = '/REPLACE/WITH/PATH';                 // the endpoint path
$method = 'GET';                                // GET or POST, as the listing says
$field  = 'query';                              // name of the parameter that carries the text

$key = getenv('RAPIDAPI_KEY');
if ($key === false || $key === '') {
    fwrite(STDERR, "Set the RAPIDAPI_KEY environment variable first.\n");
    exit(1);
}
if (strpos($host, 'REPLACE') !== false) {
    fwrite(STDERR, "Edit \$host, \$path, \$method and \$field at the top of this file first.\n");
    exit(1);
}

$text = isset($argv[1]) ? $argv[1] : 'COVID-19 vaccines contain microchips';

$url = 'https://' . $host . $path;
$ch = curl_init();
$headers = array(
    'X-RapidAPI-Key: ' . $key,
    'X-RapidAPI-Host: ' . $host,
);

if ($method === 'POST') {
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array($field => $text)));
} else {
    $url .= '?' . http_build_query(array($field => $text));
}

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$start = microtime(true);
$body = curl_exec($ch);
$seconds = round(microtime(true) - $start, 2);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Request : $method $url\n";
echo "Status  : $status\n";
echo "Time    : {$seconds}s\n";

if ($body === false) {
    echo "cURL error: $error\n";
    exit(1);
}

$json = json_decode($body, true);
echo "Response:\n";
echo $json === null ? $body : json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo "\n";
