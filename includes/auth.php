<?php
if (session_status() === PHP_SESSION_NONE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/cpp_learning',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /cpp_learning/login.php');
        exit;
    }

    if (!empty($_SESSION['onboarding_required'])
        && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'onboarding.php') {
        header('Location: /cpp_learning/onboarding.php');
        exit;
    }
}

function isChallengeUnlocked(PDO $pdo, int $userId, array $challenge): bool {
    $difficultyOrder = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
    $currentDifficulty = $difficultyOrder[$challenge['difficulty']] ?? 0;
    if ($currentDifficulty === 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM challenges c
        LEFT JOIN user_progress up
            ON up.challenge_id = c.id AND up.user_id = ?
        WHERE COALESCE(up.solved, 0) = 0
          AND (
              CASE c.difficulty
                  WHEN 'beginner' THEN 1
                  WHEN 'intermediate' THEN 2
                  WHEN 'advanced' THEN 3
                  ELSE 99
              END < ?
              OR (
                  CASE c.difficulty
                      WHEN 'beginner' THEN 1
                      WHEN 'intermediate' THEN 2
                      WHEN 'advanced' THEN 3
                      ELSE 99
                  END = ?
                  AND (
                      c.order_num < ?
                      OR (c.order_num = ? AND c.id < ?)
                  )
              )
          )
    ");
    $stmt->execute([
        $userId,
        $currentDifficulty,
        $currentDifficulty,
        $challenge['order_num'],
        $challenge['order_num'],
        $challenge['id'],
    ]);

    return (int)$stmt->fetchColumn() === 0;
}

function isAssignedChallenge(PDO $pdo, int $userId, int $challengeId): bool {
    $stmt = $pdo->prepare("
        SELECT 1
        FROM study_group_assignments a
        JOIN study_group_members m ON m.group_id = a.group_id
        WHERE m.user_id = ? AND a.challenge_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId, $challengeId]);
    return (bool)$stmt->fetchColumn();
}

function isAdventurePortalUnlocked(PDO $pdo, int $userId, array $challenge): bool {
    $difficultyOrder = ['beginner' => 1, 'intermediate' => 2, 'advanced' => 3];
    $currentDifficulty = $difficultyOrder[$challenge['difficulty']] ?? 0;
    if ($currentDifficulty === 0) {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM challenges c
        LEFT JOIN adventure_progress ap
            ON ap.challenge_id = c.id AND ap.user_id = ?
        LEFT JOIN user_progress up
            ON up.challenge_id = c.id AND up.user_id = ?
        WHERE GREATEST(COALESCE(ap.cleared, 0), COALESCE(up.solved, 0)) = 0
          AND (
              CASE c.difficulty
                  WHEN 'beginner' THEN 1
                  WHEN 'intermediate' THEN 2
                  WHEN 'advanced' THEN 3
                  ELSE 99
              END < ?
              OR (
                  CASE c.difficulty
                      WHEN 'beginner' THEN 1
                      WHEN 'intermediate' THEN 2
                      WHEN 'advanced' THEN 3
                      ELSE 99
                  END = ?
                  AND (
                      c.order_num < ?
                      OR (c.order_num = ? AND c.id < ?)
                  )
              )
          )
    ");
    $stmt->execute([
        $userId,
        $userId,
        $currentDifficulty,
        $currentDifficulty,
        $challenge['order_num'],
        $challenge['order_num'],
        $challenge['id'],
    ]);

    return (int)$stmt->fetchColumn() === 0;
}

function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'display_name' => $_SESSION['display_name'] ?? $_SESSION['username'],
        'avatar_id' => (int)($_SESSION['avatar_id'] ?? 0),
        'bio' => $_SESSION['bio'] ?? '',
        'cover_path' => $_SESSION['cover_path'] ?? null,
        'xp'       => $_SESSION['xp'],
        'level'    => $_SESSION['level'],
    ];
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function isValidCsrfToken($token): bool {
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function avatarOptions(): array {
    return [
        0 => 'Violet Coder',
        1 => 'Rose Debugger',
        2 => 'Amber Wizard',
        3 => 'Crimson Ranger',
        4 => 'Midnight Builder',
        5 => 'Sunset Coder',
        6 => 'Ocean Explorer',
        7 => 'Mint Engineer',
        8 => 'Bronze Wizard',
        9 => 'Ruby Ranger',
        10 => 'Sky Engineer',
        11 => 'Jade Explorer',
    ];
}

function pixelAvatar(int $seed, int $size = 48, ?int $avatarId = null): string {
    $sprites = [
        [
            '..HHHH..',
            '.HHHHHH.',
            '.HSSSSH.',
            '.HE..EH.',
            '..SSSS..',
            '.CCCCCC.',
            '.CC..CC.',
            'CC....CC',
        ],
        [
            '..AAAA..',
            '.AHHHHA.',
            '.HSSSSH.',
            '.H.EE.H.',
            '..SSSS..',
            '.CCCCCC.',
            '.C.CC.C.',
            'CC....CC',
        ],
        [
            '.HHHHHH.',
            'HHHHHHHH',
            '.HSSSSH.',
            '.HE..EH.',
            '..SSSS..',
            '.AAAAAA.',
            '.CC..CC.',
            '.CC..CC.',
        ],
    ];
    $palettes = [
        ['H' => '#7c3aed', 'S' => '#fbbf24', 'E' => '#111827', 'C' => '#06b6d4', 'A' => '#f472b6'],
        ['H' => '#1e293b', 'S' => '#fda4af', 'E' => '#111827', 'C' => '#22c55e', 'A' => '#facc15'],
        ['H' => '#b45309', 'S' => '#fdba74', 'E' => '#111827', 'C' => '#6366f1', 'A' => '#38bdf8'],
        ['H' => '#be123c', 'S' => '#fde68a', 'E' => '#111827', 'C' => '#a855f7', 'A' => '#34d399'],
    ];
    $avatarId = $avatarId === null ? max(0, $seed * 7) % 12 : max(0, min(11, $avatarId));
    $sprite = $sprites[$avatarId % count($sprites)];
    $palette = $palettes[intdiv($avatarId, count($sprites)) % count($palettes)];
    $pixels = '';

    foreach ($sprite as $y => $row) {
        foreach (str_split($row) as $x => $color) {
            if (isset($palette[$color])) {
                $pixels .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1" fill="' . $palette[$color] . '"/>';
            }
        }
    }

    $size = max(24, min(128, $size));
    return '<svg xmlns="http://www.w3.org/2000/svg" class="pixel-avatar" width="' . $size . '" height="' . $size . '" viewBox="0 0 8 8" role="img" aria-label="Pixel avatar" shape-rendering="crispEdges"><rect width="8" height="8" rx="1" fill="#111827"/>' . $pixels . '</svg>';
}

function refreshSession(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.xp, u.level, u.streak,
               COALESCE(p.display_name, u.username) AS display_name,
               COALESCE(p.avatar_id, MOD(u.id - 1, 12)) AS avatar_id,
               COALESCE(p.bio, '') AS bio,
               p.cover_path
        FROM users u
        LEFT JOIN user_profiles p ON p.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['display_name'] = $user['display_name'];
    $_SESSION['avatar_id'] = (int)$user['avatar_id'];
    $_SESSION['bio'] = $user['bio'];
    $_SESSION['cover_path'] = $user['cover_path'];
    $_SESSION['xp']       = $user['xp'];
    $_SESSION['level']    = $user['level'];
    $_SESSION['streak']   = $user['streak'];
}

function xpForLevel(int $level): int {
    return $level * 100; // level N unlocks at N*100 total XP
}

function levelFromXp(int $xp): int {
    return max(1, (int)floor($xp / 100) + 1);
}

function levelTitle(int $level): string {
    return match(true) {
        $level >= 10 => 'C++ Wizard',
        $level >= 8  => 'STL Master',
        $level >= 7  => 'Algorithm Adept',
        $level >= 6  => 'Memory Mapper',
        $level >= 5  => 'Stack Climber',
        $level >= 4  => 'Pointer Pilgrim',
        $level >= 3  => 'Loop Ranger',
        $level >= 2  => 'Debug Novice',
        default      => 'Syntax Seeker',
    };
}

function playerUid(int $userId): string {
    return 'CQ-' . str_pad((string)$userId, 6, '0', STR_PAD_LEFT);
}
