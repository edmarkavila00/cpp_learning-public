<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');

function reportError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    reportError(405, 'Send challenge reports using POST.');
}
if (!isLoggedIn()) {
    reportError(401, 'Please sign in to report a challenge.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    reportError(400, 'Invalid report data.');
}
if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
    reportError(400, 'Your session expired. Reload the challenge and try again.');
}

$challengeId = filter_var($input['challenge_id'] ?? null, FILTER_VALIDATE_INT);
$issueType = $input['issue_type'] ?? null;
$details = $input['details'] ?? '';
$allowedTypes = ['incorrect_answer', 'unclear_wording', 'broken_code', 'other'];
$detailsLength = is_string($details)
    ? (function_exists('mb_strlen') ? mb_strlen($details) : strlen($details))
    : PHP_INT_MAX;
if ($challengeId === false || $challengeId < 1
    || !is_string($issueType) || !in_array($issueType, $allowedTypes, true)
    || !is_string($details) || $detailsLength > 1000) {
    reportError(400, 'Choose a valid report type and provide no more than 1,000 characters.');
}

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM challenges WHERE id = ?');
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch();
if (!$challenge) {
    reportError(404, 'Challenge not found.');
}
if (!isChallengeUnlocked($pdo, $userId, $challenge)
    && !isAssignedChallenge($pdo, $userId, (int)$challengeId)) {
    reportError(403, 'This challenge is not available to your account.');
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM challenge_reports
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute([$userId]);
if ((int)$stmt->fetchColumn() >= 5) {
    reportError(429, 'You have sent several reports recently. Please try again later.');
}

$stmt = $pdo->prepare("
    INSERT INTO challenge_reports (user_id, challenge_id, issue_type, details)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([$userId, $challengeId, $issueType, trim($details)]);
echo json_encode(['ok' => true]);
