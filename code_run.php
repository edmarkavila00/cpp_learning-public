<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/code_game.php';
require_once __DIR__ . '/includes/code_runner_client.php';

header('Content-Type: application/json; charset=utf-8');

function codeRunError(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    codeRunError(405, 'Submit your C++ program using POST.');
}
if (!isLoggedIn()) {
    codeRunError(401, 'Please sign in to play the Code Lab.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    codeRunError(400, 'Invalid request data.');
}
if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
    codeRunError(400, 'Your game session expired. Reload the Code Lab and try again.');
}

$problemId = $input['problem_id'] ?? null;
$source = $input['source'] ?? null;
$problems = codeGameProblems();
if (!is_string($problemId) || !isset($problems[$problemId])
    || !is_string($source) || trim($source) === '' || strlen($source) > 16000) {
    codeRunError(400, 'Choose a mission and submit a C++ program under 16,000 bytes.');
}

$problemIds = array_keys($problems);
$problemIndex = array_search($problemId, $problemIds, true);
$userId = (int)$_SESSION['user_id'];
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
        codeRunError(403, 'Clear the previous coding mission to unlock this one.');
    }
}

$now = microtime(true);
if (isset($_SESSION['code_run_at']) && $now - (float)$_SESSION['code_run_at'] < 1.5) {
    codeRunError(429, 'Please wait a moment before running another program.');
}
$_SESSION['code_run_at'] = $now;

try {
    $result = runCodeGameTests($source, $problems[$problemId]['tests']);
} catch (RuntimeException $exception) {
    codeRunError(503, $exception->getMessage());
} catch (Throwable $exception) {
    error_log('CppQuest code runner failure: ' . $exception->getMessage());
    codeRunError(503, 'The isolated C++ runner could not process this program.');
}

if (($result['kind'] ?? '') !== 'results') {
    try {
        $pdo->prepare("
            INSERT INTO code_game_progress (user_id, problem_id, attempts)
            VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE attempts = attempts + 1
        ")->execute([$userId, $problemId]);
    } catch (Throwable $exception) {
        error_log('CppQuest code progress update failed: ' . $exception->getMessage());
        codeRunError(500, 'The run finished, but its attempt could not be saved.');
    }
    echo json_encode($result);
    exit;
}

$newlySolved = false;
$xpEarned = 0;
$newXp = (int)$_SESSION['xp'];
$newLevel = (int)$_SESSION['level'];
$previousLevel = (int)$_SESSION['level'];
$pdo->beginTransaction();
try {
    $pdo->prepare("INSERT IGNORE INTO code_game_progress (user_id, problem_id) VALUES (?, ?)")
        ->execute([$userId, $problemId]);
    $stmt = $pdo->prepare("
        SELECT solved
        FROM code_game_progress
        WHERE user_id = ? AND problem_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$userId, $problemId]);
    $progress = $stmt->fetch();
    if (!$progress) {
        throw new RuntimeException('Coding mission progress could not be loaded.');
    }

    $newlySolved = !empty($result['passed']) && !(bool)$progress['solved'];
    $pdo->prepare("
        UPDATE code_game_progress
        SET attempts = attempts + 1,
            solved = CASE WHEN ? = 1 THEN 1 ELSE solved END,
            solved_at = CASE WHEN ? = 1 THEN NOW() ELSE solved_at END
        WHERE user_id = ? AND problem_id = ?
    ")->execute([$newlySolved ? 1 : 0, $newlySolved ? 1 : 0, $userId, $problemId]);

    if ($newlySolved) {
        $xpEarned = 20;
        $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $userRecord = $stmt->fetch();
        if (!$userRecord) {
            throw new RuntimeException('Player account was not found.');
        }
        $newXp = (int)$userRecord['xp'] + $xpEarned;
        $newLevel = levelFromXp($newXp);
        $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?")
            ->execute([$newXp, $newLevel, $userId]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('CppQuest code progress transaction failed: ' . $exception->getMessage());
    codeRunError(500, 'The run finished, but its progress could not be saved.');
}

if ($newlySolved) {
    refreshSession($pdo, $userId);
}
$result['xp_earned'] = $xpEarned;
$result['new_xp'] = $newXp;
$result['new_level'] = $newLevel;
$result['level_up'] = $newlySolved && $newLevel > $previousLevel;
$result['next_problem_id'] = null;
if ($newlySolved && isset($problemIds[$problemIndex + 1])) {
    $result['next_problem_id'] = $problemIds[$problemIndex + 1];
    $result['next_problem_title'] = $problems[$result['next_problem_id']]['title'];
}
echo json_encode($result);
