<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Submit an answer using POST.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please sign in to submit an answer.']);
    exit;
}

$input       = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data.']);
    exit;
}

if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
    http_response_code(400);
    echo json_encode(['error' => 'Your game session expired. Reload the challenge and try again.']);
    exit;
}

$challengeId = (int)($input['challenge_id'] ?? 0);
$userAnswer  = $input['answer'] ?? '';

if (!$challengeId || !is_string($userAnswer) || strlen($userAnswer) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid challenge or answer.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch();
if (!$challenge) {
    echo json_encode(['error' => 'Challenge not found']); exit;
}

$userId = $_SESSION['user_id'];
if (!isChallengeUnlocked($pdo, (int)$userId, $challenge)
    && !isAssignedChallenge($pdo, (int)$userId, $challengeId)) {
    http_response_code(403);
    echo json_encode(['error' => 'Complete the earlier challenges to unlock this one.']);
    exit;
}

$pdo->beginTransaction();

// Get or create progress record
$stmt = $pdo->prepare("SELECT * FROM user_progress WHERE user_id = ? AND challenge_id = ? FOR UPDATE");
$stmt->execute([$userId, $challengeId]);
$progress = $stmt->fetch();

if (!$progress) {
    $pdo->prepare("INSERT INTO user_progress (user_id, challenge_id, attempts) VALUES (?, ?, 0)")
        ->execute([$userId, $challengeId]);
    $stmt->execute([$userId, $challengeId]);
    $progress = $stmt->fetch();
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM user_xp_purchases
    WHERE user_id = ? AND item_type = 'challenge_hint' AND context_id = ?
");
$stmt->execute([$userId, $challengeId]);
$hintsUsed = min(2, (int)$stmt->fetchColumn());
$hintUsed = $hintsUsed > 0;

// Compare answer
$expected    = trim($challenge['expected_output']);
$userTrimmed = trim(str_replace(["\r\n", "\r"], "\n", $userAnswer));
$correct     = ($userTrimmed === $expected);
$outputFeedback = null;
if (!$correct) {
    $expectedChars = function_exists('mb_str_split') ? mb_str_split($expected) : str_split($expected);
    $answerChars = function_exists('mb_str_split') ? mb_str_split($userTrimmed) : str_split($userTrimmed);
    $position = 0;
    $sharedLength = min(count($expectedChars), count($answerChars));
    while ($position < $sharedLength && $expectedChars[$position] === $answerChars[$position]) {
        $position++;
    }

    $describeCharacter = static function (array $characters, int $index): string {
        if (!isset($characters[$index])) {
            return 'the output ended';
        }
        return match ($characters[$index]) {
            ' ' => 'a space',
            "\n" => 'a line break',
            "\t" => 'a tab',
            default => 'a different character',
        };
    };
    $outputFeedback = [
        'position' => $position + 1,
        'expected' => $describeCharacter($expectedChars, $position),
        'submitted' => $describeCharacter($answerChars, $position),
        'expected_length' => count($expectedChars),
        'submitted_length' => count($answerChars),
    ];
}

// Update attempts
$pdo->prepare("UPDATE user_progress SET attempts = attempts + 1 WHERE user_id = ? AND challenge_id = ?")
    ->execute([$userId, $challengeId]);

// ── Combo logic ──────────────────────────────────────────────────────────────
if (!isset($_SESSION['combo'])) $_SESSION['combo'] = 0;

$comboBefore = $_SESSION['combo'];
$comboBroken = false;

function comboMultiplier(int $combo): float {
    if ($combo >= 5) return 2.0;
    if ($combo >= 3) return 1.5;
    return 1.0;
}

if ($correct && !$progress['solved']) {
    if ($hintUsed) {
        $_SESSION['combo'] = 0;
        $comboBroken = $comboBefore > 0;
    } else {
        $_SESSION['combo']++;
    }
} elseif (!$correct) {
    $_SESSION['combo'] = 0;
    $comboBroken = $comboBefore > 0;
}

$comboNow   = $_SESSION['combo'];
$multiplier = comboMultiplier($correct && !$hintUsed ? $comboBefore : 0);
// ─────────────────────────────────────────────────────────────────────────────

$response = [
    'correct'      => $correct,
    'attempts'     => ($progress['attempts'] ?? 0) + 1,
    'xp_earned'    => 0,
    'level_up'     => false,
    'new_level'    => $_SESSION['level'],
    'new_xp'       => $_SESSION['xp'],
    'combo'        => $comboNow,
    'combo_broken' => $comboBroken,
    'multiplier'   => $multiplier,
];

if ($correct && !$progress['solved']) {
    $baseXp    = $challenge['xp_reward'];
    $hintBonus = match($hintsUsed) { 0 => 5, 1 => 3, default => 0 };
    $xpReward  = (int)floor($baseXp * $multiplier) + $hintBonus;

    $pdo->prepare("UPDATE user_progress SET solved = 1, solved_at = NOW() WHERE user_id = ? AND challenge_id = ?")
        ->execute([$userId, $challengeId]);

    $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();

    $totalXp  = $userData['xp'] + $xpReward;
    $newLevel = max(1, floor($totalXp / 100) + 1);
    $levelUp  = $newLevel > $userData['level'];

    $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?")
        ->execute([$totalXp, $newLevel, $userId]);

    refreshSession($pdo, $userId);

    $response['xp_earned'] = $xpReward;
    $response['base_xp']   = $baseXp;
    $response['level_up']  = $levelUp;
    $response['new_level'] = $_SESSION['level'];
    $response['new_xp']    = $_SESSION['xp'];

    $total  = $pdo->query("SELECT COUNT(*) FROM challenges")->fetchColumn();
    $solved = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND solved = 1");
    $solved->execute([$userId]);
    $response['all_complete'] = ($solved->fetchColumn() >= $total);

    // Check if this difficulty level is now fully complete
    $stmt = $pdo->prepare("
        SELECT COUNT(c.id) AS total,
               SUM(CASE WHEN up.solved = 1 THEN 1 ELSE 0 END) AS solved
        FROM challenges c
        LEFT JOIN user_progress up ON up.challenge_id = c.id AND up.user_id = ?
        WHERE c.difficulty = ?
    ");
    $stmt->execute([$userId, $challenge['difficulty']]);
    $diffRow  = $stmt->fetch();
    $diffDone = $diffRow && (int)$diffRow['solved'] >= (int)$diffRow['total'] && (int)$diffRow['total'] > 0;
    $response['difficulty_complete']  = $diffDone && !$response['all_complete'];
    $response['completed_difficulty'] = $diffDone ? $challenge['difficulty'] : null;
}

if ($correct) {
    $pdo->prepare("
        INSERT INTO challenge_reviews (user_id, challenge_id, interval_days, due_at)
        VALUES (?, ?, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY))
        ON DUPLICATE KEY UPDATE
            due_at = DATE_ADD(CURDATE(), INTERVAL LEAST(interval_days * 2, 30) DAY),
            interval_days = LEAST(interval_days * 2, 30),
            last_reviewed_at = CURRENT_TIMESTAMP
    ")->execute([$userId, $challengeId]);
} else {
    $pdo->prepare("
        UPDATE challenge_reviews
        SET interval_days = 1,
            due_at = DATE_ADD(CURDATE(), INTERVAL 1 DAY),
            last_reviewed_at = CURRENT_TIMESTAMP
        WHERE user_id = ? AND challenge_id = ?
    ")->execute([$userId, $challengeId]);
    $response['output_feedback'] = $outputFeedback;
}

$pdo->commit();
echo json_encode($response);
