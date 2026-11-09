<?php
/*
 * Re-flag or delete one article (FR-3.3), called with AJAX from the Articles page.
 *   POST action=reflag&id=12&status=Real&csrf_token=...
 *   POST action=delete&id=12&csrf_token=...
 */

require __DIR__ . '/../../../includes/bootstrap.php';
require __DIR__ . '/../../../includes/public.php';
require __DIR__ . '/../../../includes/auth.php';

send_security_headers();
require_admin();
header('Content-Type: application/json; charset=utf-8');
check_csrf();

function reply($code, $data)
{
    http_response_code($code);
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

$action = (string) filter_input(INPUT_POST, 'action');
$id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    reply(400, ['error' => 'No article was chosen.']);
}
$db = db_connect();

if ($action === 'reflag') {
    $status = (string) filter_input(INPUT_POST, 'status');
    if (!in_array($status, ['Real', 'Misinformation', 'Pending Verification'], true)) {
        reply(400, ['error' => 'Choose Real, Misinformation or Pending Verification.']);
    }
    // The admin's label replaces the AI's verdict, so the AI's score, reason and flags no longer
    // apply (decision 16). A Pending article is picked up again by the next hourly AI run.
    $stmt = mysqli_prepare($db,
        'UPDATE articles SET verification_status = ?, confidence_score = NULL, verification_reason = NULL,
                is_clickbait = NULL, emotional_manipulation = NULL, bias_level = NULL
          WHERE article_id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) === 0 && !article_exists($db, $id)) {
        reply(404, ['error' => "Article #$id no longer exists."]);
    }
    reply(200, ['message' => "Article #$id re-flagged as $status."]);
}

if ($action === 'delete') {
    $stmt = mysqli_prepare($db, 'DELETE FROM articles WHERE article_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_affected_rows($stmt) === 0) {
        reply(404, ['error' => "Article #$id no longer exists."]);
    }
    reply(200, ['message' => "Article #$id deleted."]);
}

reply(400, ['error' => 'Unknown action.']);

function article_exists($db, $id)
{
    $stmt = mysqli_prepare($db, 'SELECT 1 FROM articles WHERE article_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt)->num_rows > 0;
}
