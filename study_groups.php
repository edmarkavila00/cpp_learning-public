<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
$error = '';
$notice = '';
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your form expired. Reload the page and try again.';
    } else {
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        if ($action === 'create_group') {
            $nameInput = $_POST['group_name'] ?? '';
            $name = trim(is_string($nameInput) ? $nameInput : '');
            $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
            if ($nameLength < 3 || $nameLength > 60) {
                $error = 'Group names must be between 3 and 60 characters.';
            } else {
                $created = false;
                for ($attempt = 0; $attempt < 3 && !$created; $attempt++) {
                    $inviteCode = '';
                    for ($index = 0; $index < 8; $index++) {
                        $inviteCode .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                    }

                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare('INSERT INTO study_groups (owner_id, name, invite_code) VALUES (?, ?, ?)');
                        $stmt->execute([$userId, $name, $inviteCode]);
                        $groupId = (int)$pdo->lastInsertId();
                        $pdo->prepare('INSERT INTO study_group_members (group_id, user_id) VALUES (?, ?)')
                            ->execute([$groupId, $userId]);
                        $pdo->commit();
                        $created = true;
                        $notice = 'Study group created. Share the invite code with your learners.';
                    } catch (PDOException $exception) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        if ($exception->getCode() !== '23000') {
                            throw $exception;
                        }
                    }
                }
                if (!$created) {
                    $error = 'Could not reserve a group invite code. Please try again.';
                }
            }
        } elseif ($action === 'join_group') {
            $codeInput = $_POST['invite_code'] ?? '';
            $inviteCode = strtoupper(trim(is_string($codeInput) ? $codeInput : ''));
            if (!preg_match('/^[A-HJ-NP-Z2-9]{8}$/', $inviteCode)) {
                $error = 'Enter a valid 8-character study group code.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM study_groups WHERE invite_code = ?');
                $stmt->execute([$inviteCode]);
                $groupId = $stmt->fetchColumn();
                if (!$groupId) {
                    $error = 'That study group could not be found.';
                } else {
                    $pdo->prepare('INSERT IGNORE INTO study_group_members (group_id, user_id) VALUES (?, ?)')
                        ->execute([(int)$groupId, $userId]);
                    $notice = 'You joined the study group.';
                }
            }
        } elseif ($action === 'assign_challenge') {
            $groupId = filter_var($_POST['group_id'] ?? null, FILTER_VALIDATE_INT);
            $challengeId = filter_var($_POST['challenge_id'] ?? null, FILTER_VALIDATE_INT);
            $dueInput = $_POST['due_at'] ?? '';
            $dueAt = is_string($dueInput) && $dueInput !== '' ? $dueInput : null;
            $dateIsValid = $dueAt === null;
            if ($dueAt !== null) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dueAt);
                $dateIsValid = $date && $date->format('Y-m-d') === $dueAt
                    && $dueAt >= date('Y-m-d');
            }

            if ($groupId === false || $challengeId === false || !$dateIsValid) {
                $error = 'Choose a valid challenge and an optional due date that is today or later.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT c.id
                    FROM study_groups g
                    JOIN challenges c ON c.id = ?
                    WHERE g.id = ? AND g.owner_id = ?
                ");
                $stmt->execute([$challengeId, $groupId, $userId]);
                if (!$stmt->fetchColumn()) {
                    http_response_code(403);
                    $error = 'Only the group creator can assign challenges.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO study_group_assignments (group_id, challenge_id, due_at)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE due_at = VALUES(due_at)
                    ");
                    $stmt->execute([$groupId, $challengeId, $dueAt]);
                    $notice = 'Challenge assignment saved.';
                }
            }
        } else {
            http_response_code(400);
            $error = 'Choose a valid study group action.';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT g.id, g.name, g.invite_code, g.owner_id,
           COALESCE(p.display_name, u.username) AS owner_name,
           (SELECT COUNT(*) FROM study_group_members gm WHERE gm.group_id = g.id) AS member_count
    FROM study_groups g
    JOIN study_group_members mine ON mine.group_id = g.id AND mine.user_id = ?
    JOIN users u ON u.id = g.owner_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    ORDER BY g.created_at DESC
");
$stmt->execute([$userId]);
$groups = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT id, title, difficulty
    FROM challenges
    ORDER BY FIELD(difficulty, 'beginner', 'intermediate', 'advanced'), order_num, id
");
$challenges = $stmt->fetchAll();

$groupAssignments = [];
$groupProgress = [];
foreach ($groups as $group) {
    $stmt = $pdo->prepare("
        SELECT a.id, c.id AS challenge_id, c.title, c.difficulty, a.due_at,
               COALESCE(up.solved, 0) AS solved
        FROM study_group_assignments a
        JOIN challenges c ON c.id = a.challenge_id
        LEFT JOIN user_progress up ON up.challenge_id = c.id AND up.user_id = ?
        WHERE a.group_id = ?
        ORDER BY a.due_at IS NULL, a.due_at, a.created_at DESC
    ");
    $stmt->execute([$userId, $group['id']]);
    $groupAssignments[$group['id']] = $stmt->fetchAll();

    if ((int)$group['owner_id'] === $userId) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(p.display_name, u.username) AS display_name,
                   c.title AS challenge_title,
                   COALESCE(up.solved, 0) AS solved
            FROM study_group_members gm
            JOIN users u ON u.id = gm.user_id
            LEFT JOIN user_profiles p ON p.user_id = u.id
            JOIN study_group_assignments a ON a.group_id = gm.group_id
            JOIN challenges c ON c.id = a.challenge_id
            LEFT JOIN user_progress up ON up.user_id = gm.user_id AND up.challenge_id = c.id
            WHERE gm.group_id = ? AND gm.user_id <> ?
            ORDER BY u.username, c.order_num, c.id
        ");
        $stmt->execute([$group['id'], $userId]);
        $groupProgress[$group['id']] = $stmt->fetchAll();
    }
}

$pageTitle = 'Study Groups';
include __DIR__ . '/includes/header.php';
?>

<main class="mx-auto max-w-5xl px-4 py-8">
    <header class="settings-hero mb-6 rounded-2xl border p-5 sm:p-7">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Learn together</p>
        <h1 class="mt-1 text-3xl font-black text-white">Study groups &amp; assignments</h1>
        <p class="mt-2 text-sm text-slate-300">Create a private group, share its invite code, and set challenge goals. Group creators can see enrolled members' completion, not their answers.</p>
    </header>

    <?php if ($error): ?>
        <div role="alert" class="mb-5 rounded-xl border border-red-700 bg-red-950/60 px-4 py-3 text-sm text-red-200"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($notice): ?>
        <div role="status" class="mb-5 rounded-xl border border-emerald-700 bg-emerald-950/60 px-4 py-3 text-sm text-emerald-200"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <div class="grid gap-5 md:grid-cols-2">
        <form method="POST" class="settings-section space-y-3 rounded-2xl border p-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="create_group">
            <h2 class="text-xl font-bold text-white">Create a group</h2>
            <label class="block text-sm text-slate-300" for="groupName">Group name</label>
            <input id="groupName" name="group_name" required minlength="3" maxlength="60" class="w-full rounded-lg border px-3 py-2">
            <button type="submit" class="game-button rounded-lg px-4 py-2 font-bold">Create private group</button>
        </form>

        <form method="POST" class="settings-section space-y-3 rounded-2xl border p-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
            <input type="hidden" name="action" value="join_group">
            <h2 class="text-xl font-bold text-white">Join a group</h2>
            <label class="block text-sm text-slate-300" for="inviteCode">8-character invite code</label>
            <input id="inviteCode" name="invite_code" required minlength="8" maxlength="8" pattern="[A-HJ-NP-Z2-9]{8}" class="w-full rounded-lg border px-3 py-2">
            <button type="submit" class="game-button rounded-lg px-4 py-2 font-bold">Join group</button>
        </form>
    </div>

    <section class="mt-8 space-y-5" aria-labelledby="your-groups-title">
        <h2 id="your-groups-title" class="text-2xl font-black text-white">Your groups</h2>
        <?php if (!$groups): ?>
            <p class="settings-section rounded-xl border p-5 text-sm text-slate-300">You haven't joined a study group yet.</p>
        <?php endif; ?>
        <?php foreach ($groups as $group): ?>
            <article class="settings-section rounded-2xl border p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-xl font-bold text-white"><?= htmlspecialchars($group['name']) ?></h3>
                        <p class="mt-1 text-sm text-slate-400">
                            Created by <?= htmlspecialchars($group['owner_name']) ?> · <?= (int)$group['member_count'] ?> members
                            <?php if ((int)$group['owner_id'] === $userId): ?> · Invite code: <strong><?= htmlspecialchars($group['invite_code']) ?></strong><?php endif; ?>
                        </p>
                    </div>
                    <?php if ((int)$group['owner_id'] === $userId && $challenges): ?>
                        <form method="POST" class="flex flex-wrap items-end gap-2">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
                            <input type="hidden" name="action" value="assign_challenge">
                            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                            <label class="text-xs text-slate-300">
                                Challenge
                                <select name="challenge_id" required class="mt-1 block rounded border px-2 py-2">
                                    <?php foreach ($challenges as $challenge): ?>
                                        <option value="<?= (int)$challenge['id'] ?>"><?= htmlspecialchars($challenge['title']) ?> · <?= htmlspecialchars(ucfirst($challenge['difficulty'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="text-xs text-slate-300">
                                Due date
                                <input type="date" name="due_at" min="<?= date('Y-m-d') ?>" class="mt-1 block rounded border px-2 py-2">
                            </label>
                            <button type="submit" class="game-button rounded px-3 py-2 text-sm font-bold">Assign</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="mt-4">
                    <h4 class="font-bold text-white">Assignments</h4>
                    <?php $assignments = $groupAssignments[$group['id']] ?? []; ?>
                    <?php if (!$assignments): ?>
                        <p class="mt-2 text-sm text-slate-400">No challenges assigned yet.</p>
                    <?php else: ?>
                        <ul class="mt-2 divide-y divide-slate-700">
                            <?php foreach ($assignments as $assignment): ?>
                                <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                                    <span>
                                        <a class="font-bold text-emerald-300 underline" href="/cpp_learning/challenge.php?id=<?= (int)$assignment['challenge_id'] ?>"><?= htmlspecialchars($assignment['title']) ?></a>
                                        <span class="ml-2 text-xs text-slate-400"><?= $assignment['due_at'] ? 'Due ' . htmlspecialchars($assignment['due_at']) : 'No due date' ?></span>
                                    </span>
                                    <span class="text-sm <?= $assignment['solved'] ? 'text-emerald-300' : 'text-slate-400' ?>"><?= $assignment['solved'] ? 'Completed' : 'Not completed' ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <?php if (isset($groupProgress[$group['id']])): ?>
                    <details class="mt-4">
                        <summary class="cursor-pointer font-bold text-white">Member assignment progress</summary>
                        <?php if (!$groupProgress[$group['id']]): ?>
                            <p class="mt-2 text-sm text-slate-400">No learners have joined yet.</p>
                        <?php else: ?>
                            <div class="mt-2 overflow-x-auto">
                                <table class="w-full text-left text-sm">
                                    <thead><tr><th class="py-2 pr-3">Member</th><th class="py-2 pr-3">Challenge</th><th class="py-2">Status</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($groupProgress[$group['id']] as $progress): ?>
                                        <tr>
                                            <td class="py-2 pr-3"><?= htmlspecialchars($progress['display_name']) ?></td>
                                            <td class="py-2 pr-3"><?= htmlspecialchars($progress['challenge_title']) ?></td>
                                            <td class="py-2"><?= $progress['solved'] ? 'Completed' : 'Not completed' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
