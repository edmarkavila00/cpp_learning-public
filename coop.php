<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();
$error = '';
$modes = [
    'team_answer' => 'Team answer',
    'chat_room' => 'Study room',
    'race' => 'Speed race',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'create') {
        $challengeId = filter_var($_POST['challenge_id'] ?? null, FILTER_VALIDATE_INT);
        $mode = is_string($_POST['mode'] ?? null) ? $_POST['mode'] : '';
        $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
        $stmt->execute([$challengeId ?: 0]);
        $challenge = $stmt->fetch();

        if (!$challenge || !isset($modes[$mode])) {
            $error = 'Choose a valid mission and co-op mode.';
        } elseif (!isChallengeUnlocked($pdo, (int)$user['id'], $challenge)) {
            $error = 'Finish earlier missions before hosting this one.';
        } elseif ($mode === 'race' && hasSolvedChallenge($pdo, (int)$user['id'], (int)$challenge['id'])) {
            $error = 'Choose a mission you have not cleared yet to host a race.';
        } else {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO coop_rooms (invite_code, host_id, challenge_id, mode) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $user['id'], $challenge['id'], $mode]);
                $roomId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO coop_room_members (room_id, user_id) VALUES (?, ?)")
                    ->execute([$roomId, $user['id']]);
                $pdo->commit();
                header('Location: /cpp_learning/room.php?code=' . urlencode($code));
                exit;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($exception->getCode() === '23000') {
                    $error = 'Could not reserve an invite code. Please try again.';
                } else {
                    throw $exception;
                }
            }
        }
    } elseif (($_POST['action'] ?? '') === 'join') {
        $code = strtoupper(trim(is_string($_POST['invite_code'] ?? null) ? $_POST['invite_code'] : ''));
        if (!preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $code)) {
            $error = 'Enter a valid 8-character invite code.';
        } else {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM coop_rooms WHERE invite_code = ? AND status = 'open' FOR UPDATE");
            $stmt->execute([$code]);
            $room = $stmt->fetch();

            if (!$room) {
                $pdo->rollBack();
                $error = 'That room does not exist or is already complete.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
                $stmt->execute([$room['challenge_id']]);
                $challenge = $stmt->fetch();
                if (!$challenge || !isChallengeUnlocked($pdo, (int)$user['id'], $challenge)) {
                    $pdo->rollBack();
                    $error = 'Unlock this mission on your own account before joining its room.';
                } elseif ($room['mode'] === 'race' && hasSolvedChallenge($pdo, (int)$user['id'], (int)$room['challenge_id'])) {
                    $pdo->rollBack();
                    $error = 'You have already cleared this mission, so you cannot enter its race.';
                } else {
                    $room['challenge_unlocked'] = true;
                }
            }

            if (!empty($room['challenge_unlocked'])) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS member_count,
                           COALESCE(SUM(user_id = ?), 0) AS already_joined
                    FROM coop_room_members
                    WHERE room_id = ?
                ");
                $stmt->execute([$user['id'], $room['id']]);
                $memberCounts = $stmt->fetch();
                if ((int)$memberCounts['member_count'] >= 8 && !(int)$memberCounts['already_joined']) {
                    $pdo->rollBack();
                    $error = 'This room is full (maximum 8 players).';
                } else {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO coop_room_members (room_id, user_id) VALUES (?, ?)");
                    $stmt->execute([$room['id'], $user['id']]);
                    $pdo->commit();
                    header('Location: /cpp_learning/room.php?code=' . urlencode($code));
                    exit;
                }
            }
        }
    }
}

$stmt = $pdo->prepare("
    SELECT r.invite_code, r.mode, r.status, r.created_at, c.title,
           COUNT(m.user_id) AS member_count, r.host_id
    FROM coop_room_members mine
    JOIN coop_rooms r ON r.id = mine.room_id
    JOIN challenges c ON c.id = r.challenge_id
    JOIN coop_room_members m ON m.room_id = r.id
    WHERE mine.user_id = ?
    GROUP BY r.id
    ORDER BY r.created_at DESC
    LIMIT 12
");
$stmt->execute([$user['id']]);
$rooms = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.difficulty, c.xp_reward, c.order_num,
           COALESCE(up.solved, 0) AS solved
    FROM challenges c
    LEFT JOIN user_progress up ON up.challenge_id = c.id AND up.user_id = ?
    ORDER BY FIELD(c.difficulty, 'beginner', 'intermediate', 'advanced'), c.order_num, c.id
");
$stmt->execute([$user['id']]);
$challengeOptions = [];
$missionGateOpen = true;
foreach ($stmt->fetchAll() as $challenge) {
    if (!$missionGateOpen) break;
    $challengeOptions[] = $challenge;
    if (!(bool)$challenge['solved']) $missionGateOpen = false;
}

$pageTitle = 'Co-op Rooms';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-5xl mx-auto px-4 py-8">
    <div class="mb-7">
        <div class="text-xs uppercase tracking-[.22em] text-cyan-300 font-bold">Multiplayer hub</div>
        <h1 class="text-3xl font-black mt-1">Co-op rooms</h1>
        <p class="text-slate-400 mt-2">Invite up to 7 other players to work through a mission together.</p>
    </div>

    <?php if ($error): ?>
        <div class="mb-5 rounded-xl border border-red-700 bg-red-900/30 px-4 py-3 text-sm text-red-200"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-5 mb-8">
        <form method="POST" class="game-panel rounded-2xl border border-indigo-500/30 p-5 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="create">
            <h2 class="text-xl font-bold">Create a room</h2>
            <label class="block text-sm text-slate-300">
                Mission
                <select name="challenge_id" required class="mt-2 w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-3 text-white">
                    <?php foreach ($challengeOptions as $challenge): ?>
                        <option value="<?= (int)$challenge['id'] ?>"><?= htmlspecialchars($challenge['title']) ?> · <?= ucfirst($challenge['difficulty']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-sm text-slate-300">
                Co-op mode
                <select name="mode" class="mt-2 w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-3 text-white">
                    <option value="team_answer">Team answer — one solve rewards every teammate</option>
                    <option value="chat_room">Study room — chat and earn XP individually</option>
                    <option value="race">Speed race — first correct answer wins XP</option>
                </select>
            </label>
            <button type="submit" class="game-button w-full rounded-lg bg-indigo-600 px-4 py-3 font-bold hover:bg-indigo-500">Create invite room</button>
        </form>

        <form method="POST" class="game-panel rounded-2xl border border-cyan-500/30 p-5 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <input type="hidden" name="action" value="join">
            <h2 class="text-xl font-bold">Join a room</h2>
            <p class="text-sm text-slate-400">Ask a friend for their 8-character room code. You need to have unlocked its mission.</p>
            <label class="block text-sm text-slate-300">
                Invite code
                <input name="invite_code" maxlength="8" minlength="8" required autocomplete="off" placeholder="AB12CD34"
                       class="mt-2 w-full rounded-lg border border-slate-600 bg-slate-950 px-4 py-3 text-white uppercase tracking-[.25em]">
            </label>
            <button type="submit" class="game-button w-full rounded-lg bg-cyan-700 px-4 py-3 font-bold hover:bg-cyan-600">Join mission</button>
        </form>
    </div>

    <section>
        <h2 class="text-xl font-bold mb-3">Your rooms</h2>
        <?php if (!$rooms): ?>
            <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-6 text-center text-slate-400">Your co-op rooms will show up here.</div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($rooms as $room): ?>
                    <a href="/cpp_learning/room.php?code=<?= urlencode($room['invite_code']) ?>" class="game-panel flex items-center justify-between gap-4 rounded-xl border border-slate-700 p-4 hover:border-indigo-500 transition">
                        <div class="min-w-0">
                            <div class="font-semibold truncate"><?= htmlspecialchars($room['title']) ?></div>
                            <div class="text-xs text-slate-400 mt-1"><?= htmlspecialchars($modes[$room['mode']] ?? 'Co-op') ?> · <?= (int)$room['member_count'] ?>/8 players</div>
                        </div>
                        <span class="text-xs rounded-full px-3 py-1 <?= $room['status'] === 'open' ? 'bg-green-900/50 text-green-300' : 'bg-slate-700 text-slate-300' ?>"><?= $room['status'] === 'open' ? 'Open' : 'Complete' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
