<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/coop.php';
require_once __DIR__ . '/includes/code_game.php';

header('Content-Type: application/json; charset=utf-8');

function pastePenaltyResponse(int $status, array $response): void {
    http_response_code($status);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pastePenaltyResponse(405, ['error' => 'Submit a paste penalty using POST.']);
}
if (!isLoggedIn()) {
    pastePenaltyResponse(401, ['error' => 'Please sign in before submitting a game answer.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    pastePenaltyResponse(400, ['error' => 'Invalid paste penalty request.']);
}
if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
    pastePenaltyResponse(400, ['error' => 'Your session expired. Reload the game and try again.']);
}

$source = $input['source'] ?? null;
$contextId = $input['context_id'] ?? null;
if (!is_string($source) || !is_string($contextId) || strlen($contextId) > 64) {
    pastePenaltyResponse(400, ['error' => 'Invalid game context.']);
}

$userId = (int)$_SESSION['user_id'];
if ($source === 'challenge') {
    $challengeId = filter_var($contextId, FILTER_VALIDATE_INT);
    if (!$challengeId || $challengeId < 1) {
        pastePenaltyResponse(400, ['error' => 'Invalid challenge context.']);
    }
    $stmt = $pdo->prepare('SELECT * FROM challenges WHERE id = ?');
    $stmt->execute([$challengeId]);
    $challenge = $stmt->fetch();
    if (!$challenge || !isChallengeUnlocked($pdo, $userId, $challenge)) {
        pastePenaltyResponse(403, ['error' => 'This challenge is not available to your account.']);
    }
} elseif ($source === 'coop') {
    $room = findCoopRoom($pdo, strtoupper($contextId), $userId);
    if (!$room || $room['status'] !== 'open') {
        pastePenaltyResponse(403, ['error' => 'This co-op answer is no longer available.']);
    }
} elseif ($source === 'code_lab') {
    $problemIds = array_keys(codeGameProblems());
    $problemIndex = array_search($contextId, $problemIds, true);
    if ($problemIndex === false) {
        pastePenaltyResponse(400, ['error' => 'Invalid coding mission.']);
    }
    if ($problemIndex > 0) {
        $previousIds = array_slice($problemIds, 0, $problemIndex);
        $placeholders = implode(',', array_fill(0, count($previousIds), '?'));
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM code_game_progress
            WHERE user_id = ? AND solved = 1 AND problem_id IN ($placeholders)
        ");
        $stmt->execute(array_merge([$userId], $previousIds));
        if ((int)$stmt->fetchColumn() !== count($previousIds)) {
            pastePenaltyResponse(403, ['error' => 'This coding mission is still locked.']);
        }
    }
} else {
    pastePenaltyResponse(400, ['error' => 'Unsupported game context.']);
}

$penalty = 10;
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT xp, level FROM users WHERE id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    $player = $stmt->fetch();
    if (!$player) {
        throw new RuntimeException('Player account was not found.');
    }

    $xpDeducted = min($penalty, max(0, (int)$player['xp']));
    $newXp = (int)$player['xp'] - $xpDeducted;
    $newLevel = levelFromXp($newXp);
    $pdo->prepare('UPDATE users SET xp = ?, level = ? WHERE id = ?')
        ->execute([$newXp, $newLevel, $userId]);
    $pdo->prepare("
        INSERT INTO paste_penalties (user_id, game_type, game_id, xp_deducted)
        VALUES (?, ?, ?, ?)
    ")->execute([$userId, $source, $contextId, $xpDeducted]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('CppQuest paste penalty failed: ' . $exception->getMessage());
    pastePenaltyResponse(500, ['error' => 'The paste was blocked, but the XP penalty could not be recorded.']);
}

refreshSession($pdo, $userId);
pastePenaltyResponse(200, [
    'penalty' => $penalty,
    'xp_deducted' => $xpDeducted,
    'new_xp' => $newXp,
    'new_level' => $newLevel,
]);
