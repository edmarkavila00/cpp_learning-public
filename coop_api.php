<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/coop.php';
requireLogin();
header('Content-Type: application/json; charset=utf-8');

$userId = (int)$_SESSION['user_id'];
$code = strtoupper(trim(is_string($_GET['code'] ?? null) ? $_GET['code'] : ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request data.']);
        exit;
    }

    $code = strtoupper(trim(is_string($input['code'] ?? null) ? $input['code'] : ''));
    if (!isValidCsrfToken($input['csrf_token'] ?? null)) {
        http_response_code(400);
        echo json_encode(['error' => 'Your session expired. Reload the room and try again.']);
        exit;
    }
    if (($input['action'] ?? '') !== 'message') {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown room action.']);
        exit;
    }

    $message = trim(is_string($input['message'] ?? null) ? $input['message'] : '');
    $messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
    if ($messageLength < 1 || $messageLength > 500) {
        http_response_code(400);
        echo json_encode(['error' => 'Chat messages must be 1 to 500 characters.']);
        exit;
    }

    $room = findCoopRoom($pdo, $code, $userId);
    if (!$room) {
        http_response_code(404);
        echo json_encode(['error' => 'Room not found.']);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO coop_room_messages (room_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$room['id'], $userId, $message]);
    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Use GET to refresh the room or POST to send a message.']);
    exit;
}

$room = findCoopRoom($pdo, $code, $userId);
if (!$room) {
    http_response_code(404);
    echo json_encode(['error' => 'Room not found.']);
    exit;
}
$afterId = max(0, (int)($_GET['after'] ?? 0));
$stmt = $pdo->prepare("
    SELECT m.id, m.user_id, m.message, m.created_at,
           COALESCE(p.display_name, u.username) AS display_name
    FROM coop_room_messages m
    JOIN users u ON u.id = m.user_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    WHERE m.room_id = ? AND m.id > ?
    ORDER BY m.id
    LIMIT 50
");
$stmt->execute([$room['id'], $afterId]);
$messages = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT m.user_id, r.host_id,
           COALESCE(p.display_name, u.username) AS display_name,
           COALESCE(p.avatar_id, MOD(u.id - 1, 12)) AS avatar_id,
           COALESCE(up.solved, 0) AS solved
    FROM coop_room_members m
    JOIN coop_rooms r ON r.id = m.room_id
    JOIN users u ON u.id = m.user_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    LEFT JOIN user_progress up ON up.user_id = u.id AND up.challenge_id = r.challenge_id
    WHERE m.room_id = ?
    ORDER BY m.joined_at, m.user_id
");
$stmt->execute([$room['id']]);
$members = $stmt->fetchAll();

$winnerName = null;
if ($room['winner_id']) {
    $stmt = $pdo->prepare("SELECT COALESCE(p.display_name, u.username) FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$room['winner_id']]);
    $winnerName = $stmt->fetchColumn() ?: null;
}

echo json_encode([
    'room' => [
        'mode' => $room['mode'],
        'status' => $room['status'],
        'winner_name' => $winnerName,
    ],
    'members' => array_map(static function (array $member): array {
        $member['avatar_html'] = pixelAvatar((int)$member['user_id'], 36, (int)$member['avatar_id']);
        $member['is_host'] = (int)$member['user_id'] === (int)$member['host_id'];
        unset($member['host_id']);
        return $member;
    }, $members),
    'messages' => $messages,
]);
