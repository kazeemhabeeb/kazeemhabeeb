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
    // Second-opinion flow (test_second_opinion.php): the answer depends on a word in the article title.
    case '/flow-gpt4':
        $prompt = json_decode(file_get_contents('php://input'), true)['messages'][0]['content'];
        if (strpos($prompt, 'SATIRE') !== false)     { $r = [5, true, 'Known satire publication.']; }
        elseif (strpos($prompt, 'RECENT') !== false) { $r = [10, false, 'Implausible claim about a living public figure.']; }
        elseif (strpos($prompt, 'HOAX') !== false)   { $r = [5, false, 'Unverified sensational claim.']; }
        else                                         { $r = [85, false, 'Plausible and neutral.']; }
        echo json_encode(['result' => json_encode(['credibility_score' => $r[0], 'clickbait' => true, 'emotional_manipulation' => true,
            'bias' => 'low', 'satire' => $r[1], 'reason' => $r[2]]), 'status' => true]);
        break;
    case '/flow-web':
        $in = json_decode(file_get_contents('php://input'), true);
        $prompt = $in['messages'][0]['content'];
        if ($in['tools'][0]['type'] !== 'web_search' || strpos($prompt, 'Search the web') === false) { http_response_code(400); echo '{}'; break; }
        if (strpos($prompt, 'WEBFAIL') !== false) { http_response_code(502); echo '{"message":"upstream request failed"}'; break; }
        $real = strpos($prompt, 'RECENT') !== false;
        $content = json_encode(['credibility_score' => $real ? 88 : 8, 'clickbait' => false, 'emotional_manipulation' => false,
            'bias' => 'low', 'satire' => false,
            'reason' => $real ? 'Independent reporting supports the events. ([kvia.com](https://kvia.com/a/2026/11/10/story/))'
                              : 'No reliable source reports this; fact-checkers call it a hoax.']);
        echo json_encode(['object' => 'chat.completion', 'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content]]],
            'usage' => ['total_tokens' => 13653]]);
        break;
    case '/good':          echo json_encode(['result' => '{"credibility_score":82,"clickbait":false,"emotional_manipulation":false,"bias":"none","reason":"Plausible and neutrally worded."}', 'status' => true]); break;
    default:               http_response_code(404); echo '{"message":"Endpoint does not exist"}';
}
