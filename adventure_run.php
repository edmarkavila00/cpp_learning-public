<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/adventure_game.php';

header('Content-Type: application/json');

function adventureJsonError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    adventureJsonError(405, 'Submit a portal answer using POST.');
}
if (!isLoggedIn()) {
    adventureJsonError(401, 'Please sign in to play the portal trial.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    adventureJsonError(400, 'Invalid request data.');
}
if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
    adventureJsonError(400, 'Your game session expired. Reload the portal and try again.');
}

$challengeId = filter_var($input['challenge_id'] ?? null, FILTER_VALIDATE_INT);
$roundNumber = filter_var($input['round_number'] ?? null, FILTER_VALIDATE_INT);
$answer = $input['answer'] ?? null;
if (!$challengeId || $roundNumber === false || $roundNumber < 0 || $roundNumber > 2
    || !is_string($answer) || strlen($answer) > 80) {
    adventureJsonError(400, 'Invalid portal answer.');
}

$stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch();
if (!$challenge) {
    adventureJsonError(404, 'Portal not found.');
}

$userId = (int)$_SESSION['user_id'];
if (!isAdventurePortalUnlocked($pdo, $userId, $challenge)) {
    adventureJsonError(403, 'Clear the previous portal trials to unlock this one.');
}

$question = adventureQuestion($challenge, $roundNumber);
$pdo->beginTransaction();

try {
    $pdo->prepare("INSERT IGNORE INTO adventure_progress (user_id, challenge_id) VALUES (?, ?)")
        ->execute([$userId, $challengeId]);
    $stmt = $pdo->prepare("
        SELECT round_number, score, cleared
        FROM adventure_progress
        WHERE user_id = ? AND challenge_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$userId, $challengeId]);
    $progress = $stmt->fetch();

    if ($progress['cleared']) {
        $pdo->commit();
        echo json_encode([
            'correct' => true,
            'already_cleared' => true,
            'finished' => true,
            'xp_earned' => 0,
        ]);
        exit;
    }

    if ((int)$progress['round_number'] !== $roundNumber) {
        $pdo->rollBack();
        adventureJsonError(409, 'Your portal round changed. Reload the portal to continue.');
    }

    if (!hash_equals($question['answer'], $answer)) {
        $pdo->rollBack();
        echo json_encode([
            'correct' => false,
            'feedback' => 'That token does not repair the code yet. Try another one.',
        ]);
        exit;
    }

    $score = (int)$progress['score'] + 1;
    $nextRound = $roundNumber + 1;
    $finished = $nextRound >= 3;
    $pdo->prepare("
        UPDATE adventure_progress
        SET round_number = ?, score = ?, cleared = ?, cleared_at = ?
        WHERE user_id = ? AND challenge_id = ?
    ")->execute([
        $nextRound,
        $score,
        $finished ? 1 : 0,
        $finished ? date('Y-m-d H:i:s') : null,
        $userId,
        $challengeId,
    ]);

    $response = [
        'correct' => true,
        'finished' => $finished,
        'round_number' => $nextRound,
        'score' => $score,
        'feedback' => $question['explanation'],
    ];

    if ($finished) {
        $xpEarned = 35;
        $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('Player account was not found.');
        }

        $newXp = (int)$user['xp'] + $xpEarned;
        $newLevel = levelFromXp($newXp);
        $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?")
            ->execute([$newXp, $newLevel, $userId]);
        $response['xp_earned'] = $xpEarned;
        $response['new_xp'] = $newXp;
        $response['new_level'] = $newLevel;
        $response['level_up'] = $newLevel > (int)$user['level'];
    } else {
        $response['next_question'] = publicAdventureQuestion($challenge, $nextRound);
    }

    $pdo->commit();
    if ($finished) {
        refreshSession($pdo, $userId);
    }
    echo json_encode($response);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
