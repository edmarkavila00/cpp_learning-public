<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/adventure_game.php';

header('Content-Type: application/json; charset=utf-8');

function xpPurchaseResponse(int $status, array $response): void {
    http_response_code($status);
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    xpPurchaseResponse(405, ['error' => 'Submit an XP purchase using POST.']);
}
if (!isLoggedIn()) {
    xpPurchaseResponse(401, ['error' => 'Please sign in before spending XP.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    xpPurchaseResponse(400, ['error' => 'Invalid XP purchase request.']);
}
if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
    xpPurchaseResponse(400, ['error' => 'Your session expired. Reload the game and try again.']);
}

$game = $input['game'] ?? null;
$itemType = $input['item'] ?? null;
$challengeId = filter_var($input['challenge_id'] ?? null, FILTER_VALIDATE_INT);
$userId = (int)$_SESSION['user_id'];
if (!in_array($game, ['challenge', 'adventure'], true)
    || !in_array($itemType, ['hint', 'answer'], true)
    || !$challengeId || $challengeId < 1) {
    xpPurchaseResponse(400, ['error' => 'Invalid XP purchase details.']);
}

$stmt = $pdo->prepare('SELECT * FROM challenges WHERE id = ?');
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch();
if (!$challenge) {
    xpPurchaseResponse(404, ['error' => 'The selected challenge was not found.']);
}

$itemKey = '';
$itemTypeKey = $game . '_' . $itemType;
$content = '';
$cost = match ([$game, $itemType]) {
    ['challenge', 'hint'] => 10,
    ['adventure', 'hint'] => 20,
    default => 50,
};
$roundNumber = null;

if ($game === 'challenge') {
    if (!isChallengeUnlocked($pdo, $userId, $challenge)) {
        xpPurchaseResponse(403, ['error' => 'Complete earlier challenges to unlock this one.']);
    }

    if ($itemType === 'answer') {
        $itemKey = 'answer';
        $content = (string)$challenge['expected_output'];
    } else {
        $hintIndex = filter_var($input['hint_index'] ?? null, FILTER_VALIDATE_INT);
        $rawHints = $challenge['hint'] ?? '';
        $decodedHints = $rawHints !== '' ? json_decode($rawHints, true) : null;
        $hints = is_array($decodedHints) ? $decodedHints : ($rawHints !== '' ? [$rawHints] : []);
        if ($hintIndex === false || $hintIndex < 0 || !isset($hints[$hintIndex])) {
            xpPurchaseResponse(400, ['error' => 'That challenge hint is unavailable.']);
        }
        $itemKey = 'hint-' . $hintIndex;
        $content = (string)$hints[$hintIndex];
    }
} else {
    $roundNumber = filter_var($input['round_number'] ?? null, FILTER_VALIDATE_INT);
    if ($roundNumber === false || $roundNumber < 0 || $roundNumber > 2
        || !isAdventurePortalUnlocked($pdo, $userId, $challenge)) {
        xpPurchaseResponse(403, ['error' => 'This Adventure round is not available.']);
    }

    try {
        $question = adventureQuestion($challenge, $roundNumber);
    } catch (InvalidArgumentException $exception) {
        xpPurchaseResponse(400, ['error' => 'This Adventure question is unavailable.']);
    }
    $itemKey = 'round-' . $roundNumber;
    $content = $itemType === 'answer'
        ? (string)$question['answer']
        : (string)$question['hint'];
}

$pdo->beginTransaction();
try {
    if ($game === 'adventure') {
        $stmt = $pdo->prepare("
            SELECT round_number, cleared
            FROM adventure_progress
            WHERE user_id = ? AND challenge_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$userId, $challengeId]);
        $progress = $stmt->fetch();
        $currentRound = $progress ? (int)$progress['round_number'] : 0;
        if (($progress && (bool)$progress['cleared']) || $currentRound !== $roundNumber) {
            $pdo->rollBack();
            xpPurchaseResponse(409, ['error' => 'That Adventure round has changed. Reload the portal and try again.']);
        }
    }

    $stmt = $pdo->prepare('SELECT xp, level FROM users WHERE id = ? FOR UPDATE');
    $stmt->execute([$userId]);
    $player = $stmt->fetch();
    if (!$player) {
        throw new RuntimeException('Player account was not found.');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM user_xp_purchases
        WHERE user_id = ? AND item_type = ? AND context_id = ? AND item_key = ?
    ");
    $stmt->execute([$userId, $itemTypeKey, $challengeId, $itemKey]);
    $alreadyOwned = (bool)$stmt->fetchColumn();
    $xpDeducted = 0;
    $newXp = (int)$player['xp'];
    $newLevel = (int)$player['level'];

    if (!$alreadyOwned) {
        if ($newXp < $cost) {
            $pdo->rollBack();
            xpPurchaseResponse(409, [
                'error' => "You need {$cost} XP for this unlock, but you have {$newXp} XP.",
                'new_xp' => $newXp,
                'new_level' => $newLevel,
            ]);
        }

        $newXp -= $cost;
        $newLevel = levelFromXp($newXp);
        $pdo->prepare('UPDATE users SET xp = ?, level = ? WHERE id = ?')
            ->execute([$newXp, $newLevel, $userId]);
        $pdo->prepare("
            INSERT INTO user_xp_purchases (user_id, item_type, context_id, item_key, xp_spent)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$userId, $itemTypeKey, $challengeId, $itemKey, $cost]);
        $xpDeducted = $cost;
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('CppQuest XP purchase failed: ' . $exception->getMessage());
    xpPurchaseResponse(500, ['error' => 'The XP purchase could not be completed.']);
}

refreshSession($pdo, $userId);
$ownedHintCount = 0;
if ($itemTypeKey === 'challenge_hint') {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM user_xp_purchases
        WHERE user_id = ? AND item_type = 'challenge_hint' AND context_id = ?
    ");
    $stmt->execute([$userId, $challengeId]);
    $ownedHintCount = (int)$stmt->fetchColumn();
}
xpPurchaseResponse(200, [
    'content' => $content,
    'already_owned' => $alreadyOwned,
    'owned_hint_count' => $ownedHintCount,
    'xp_deducted' => $xpDeducted,
    'new_xp' => $newXp,
    'new_level' => $newLevel,
]);
