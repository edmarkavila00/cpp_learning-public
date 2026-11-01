<?php
function findCoopRoom(PDO $pdo, string $code, int $userId): ?array {
    if (!preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $code)) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT r.*, c.title, c.code AS challenge_code, c.expected_output,
               c.difficulty, c.category, c.xp_reward,
               h.display_name AS host_name
        FROM coop_rooms r
        JOIN challenges c ON c.id = r.challenge_id
        JOIN user_profiles h ON h.user_id = r.host_id
        JOIN coop_room_members m ON m.room_id = r.id AND m.user_id = ?
        WHERE r.invite_code = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $code]);
    $room = $stmt->fetch();

    return $room ?: null;
}

function coopModeLabel(string $mode): string {
    return match ($mode) {
        'team_answer' => 'Team answer',
        'chat_room' => 'Study room',
        'race' => 'Speed race',
        default => 'Co-op room',
    };
}

function hasSolvedChallenge(PDO $pdo, int $userId, int $challengeId): bool {
    $stmt = $pdo->prepare("SELECT solved FROM user_progress WHERE user_id = ? AND challenge_id = ?");
    $stmt->execute([$userId, $challengeId]);
    return (bool)$stmt->fetchColumn();
}
