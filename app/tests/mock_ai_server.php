<?php
// A fake AI service for testing failures without using credits.
// Start: php -S 127.0.0.1:8095 app/tests/mock_ai_server.php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
switch ($path) {
    case '/slow':          sleep(3); echo '{"result":"{}","status":true}'; break;
    case '/rate-limit':    http_response_code(429); echo '{"message":"Too many requests"}'; break;
    case '/no-credits':    http_response_code(429); echo '{"message":"You have exceeded the MONTHLY quota for Requests on your current plan, BASIC."}'; break;
    case '/bad-key':       http_response_code(403); echo '{"message":"You are not subscribed to this API."}'; break;
    case '/server-down':   http_response_code(503); echo 'Service Unavailable'; break;
    case '/new-format':    echo '{"choices":[{"message":{"content":"{\"credibility_score\":80}"}}]}'; break;
    case '/good':          echo json_encode(['result' => '{"credibility_score":82,"clickbait":false,"emotional_manipulation":false,"bias":"none","reason":"Plausible and neutrally worded."}', 'status' => true]); break;
    default:               http_response_code(404); echo '{"message":"Endpoint does not exist"}';
}
