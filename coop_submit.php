<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/coop.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

function coopSubmitResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function addCoopXp(PDO $pdo, int $userId, int $amount): array {
    $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $totalXp = (int)$user['xp'] + $amount;
    $newLevel = levelFromXp($totalXp);
    $stmt = $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
    $stmt->execute([$totalXp, $newLevel, $userId]);
    return ['xp' => $totalXp, 'level' => $newLevel, 'level_up' => $newLevel > (int)$user['level']];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    coopSubmitResponse(['error' => 'Submit an answer using POST.'], 405);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    coopSubmitResponse(['error' => 'Invalid request data.'], 400);
}
if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
    coopSubmitResponse(['error' => 'Your session expired. Reload the room and try again.'], 400);
}

$code = strtoupper(trim(is_string($input['code'] ?? null) ? $input['code'] : ''));
$answer = $input['answer'] ?? '';
if (!is_string($answer) || strlen($answer) > 2000) {
    coopSubmitResponse(['error' => 'Invalid answer.'], 400);
}

$userId = (int)$_SESSION['user_id'];
$room = findCoopRoom($pdo, $code, $userId);
if (!$room) {
    coopSubmitResponse(['error' => 'Room not found.'], 404);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        SELECT r.*, c.expected_output, c.xp_reward
        FROM coop_rooms r
        JOIN challenges c ON c.id = r.challenge_id
        WHERE r.id = ?
        FOR UPDATE
    ");
    $stmt->execute([$room['id']]);
    $lockedRoom = $stmt->fetch();
    if (!$lockedRoom || $lockedRoom['status'] !== 'open') {
        $pdo->commit();
        $winnerName = null;
        if ($lockedRoom && $lockedRoom['winner_id']) {
            $stmt = $pdo->prepare("SELECT COALESCE(p.display_name, u.username) FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ?");
            $stmt->execute([$lockedRoom['winner_id']]);
            $winnerName = $stmt->fetchColumn() ?: null;
        }
        $normalizedSubmittedAnswer = trim(str_replace(["\r\n", "\r"], "\n", $answer));
        $wasCorrect = $lockedRoom && trim($lockedRoom['expected_output']) === $normalizedSubmittedAnswer;
        coopSubmitResponse([
            'correct' => $wasCorrect,
            'xp_earned' => 0,
            'room_completed' => true,
            'message' => $lockedRoom && $lockedRoom['mode'] === 'race'
                ? 'The race has ended' . ($winnerName ? ' — ' . $winnerName . ' won.' : '.')
                : 'Your party has already cleared this mission.',
        ]);
    }

    $stmt = $pdo->prepare("SELECT 1 FROM coop_room_members WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$lockedRoom['id'], $userId]);
    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();
        coopSubmitResponse(['error' => 'Join this room before submitting an answer.'], 403);
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO user_progress (user_id, challenge_id, attempts) VALUES (?, ?, 0)");
    $stmt->execute([$userId, $lockedRoom['challenge_id']]);
    $stmt = $pdo->prepare("SELECT solved, attempts FROM user_progress WHERE user_id = ? AND challenge_id = ? FOR UPDATE");
    $stmt->execute([$userId, $lockedRoom['challenge_id']]);
    $progress = $stmt->fetch();
    $stmt = $pdo->prepare("UPDATE user_progress SET attempts = attempts + 1 WHERE user_id = ? AND challenge_id = ?");
    $stmt->execute([$userId, $lockedRoom['challenge_id']]);

    $expected = trim($lockedRoom['expected_output']);
    $normalizedAnswer = trim(str_replace(["\r\n", "\r"], "\n", $answer));
    if ($normalizedAnswer !== $expected) {
        $pdo->commit();
        coopSubmitResponse([
            'correct' => false,
            'attempts' => (int)$progress['attempts'] + 1,
            'message' => 'Not quite — compare each printed character and try again.',
        ]);
    }

    $baseXp = (int)$lockedRoom['xp_reward'];
    $xpEarned = 0;
    $roomCompleted = false;
    $message = 'Correct answer!';
    $alreadySolved = (bool)$progress['solved'];

    if ($lockedRoom['mode'] === 'team_answer') {
        $stmt = $pdo->prepare("SELECT user_id FROM coop_room_members WHERE room_id = ? ORDER BY user_id");
        $stmt->execute([$lockedRoom['id']]);
        $members = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($members as $memberId) {
            $memberId = (int)$memberId;
            $stmt = $pdo->prepare("INSERT IGNORE INTO user_progress (user_id, challenge_id, attempts) VALUES (?, ?, 0)");
            $stmt->execute([$memberId, $lockedRoom['challenge_id']]);
            $stmt = $pdo->prepare("SELECT solved FROM user_progress WHERE user_id = ? AND challenge_id = ? FOR UPDATE");
            $stmt->execute([$memberId, $lockedRoom['challenge_id']]);
            if (!(bool)$stmt->fetchColumn()) {
                $stmt = $pdo->prepare("UPDATE user_progress SET solved = 1, solved_at = NOW() WHERE user_id = ? AND challenge_id = ? AND solved = 0");
                $stmt->execute([$memberId, $lockedRoom['challenge_id']]);
                if ($stmt->rowCount() === 1) {
                    $memberXp = addCoopXp($pdo, $memberId, $baseXp);
                    if ($memberId === $userId) $xpEarned = $baseXp;
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE coop_rooms SET status = 'completed', winner_id = NULL, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$lockedRoom['id']]);
        $roomCompleted = true;
        $message = 'Mission cleared! Teammates who still needed it earned XP.';
    } elseif ($lockedRoom['mode'] === 'race') {
        if ($alreadySolved) {
            $pdo->commit();
            coopSubmitResponse([
                'correct' => true,
                'already_solved' => true,
                'xp_earned' => 0,
                'room_completed' => false,
                'message' => 'You already cleared this mission and cannot claim its race reward.',
            ]);
        }
        $stmt = $pdo->prepare("UPDATE user_progress SET solved = 1, solved_at = NOW() WHERE user_id = ? AND challenge_id = ? AND solved = 0");
        $stmt->execute([$userId, $lockedRoom['challenge_id']]);
        if ($stmt->rowCount() === 1) {
            $xpEarned = $baseXp + 5;
            addCoopXp($pdo, $userId, $xpEarned);
        }
        $stmt = $pdo->prepare("UPDATE coop_rooms SET status = 'completed', winner_id = ?, completed_at = NOW() WHERE id = ?");
        $stmt->execute([$userId, $lockedRoom['id']]);
        $roomCompleted = true;
        $message = "You won the race! +{$xpEarned} XP.";
    } elseif ($alreadySolved) {
        $message = 'You already earned XP for this mission.';
    } else {
        $stmt = $pdo->prepare("UPDATE user_progress SET solved = 1, solved_at = NOW() WHERE user_id = ? AND challenge_id = ? AND solved = 0");
        $stmt->execute([$userId, $lockedRoom['challenge_id']]);
        if ($stmt->rowCount() === 1) {
            $xpEarned = $baseXp + 5;
            addCoopXp($pdo, $userId, $xpEarned);
        }
        $message = "Correct! You earned {$xpEarned} XP.";
    }

    $pdo->commit();
    refreshSession($pdo, $userId);
    coopSubmitResponse([
        'correct' => true,
        'already_solved' => $alreadySolved,
        'xp_earned' => $xpEarned,
        'room_completed' => $roomCompleted,
        'message' => $message,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
