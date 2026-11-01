<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
refreshSession($pdo, $_SESSION['user_id']);
$user = currentUser();

$players = $pdo->query("
    SELECT
        u.id,
        u.username,
        u.xp,
        u.level,
        u.streak,
        COALESCE(MAX(p.display_name), u.username) AS display_name,
        COALESCE(MAX(p.avatar_id), MOD(u.id - 1, 12)) AS avatar_id,
        COALESCE(MAX(p.bio), '') AS bio,
        COUNT(up.id) AS solved
    FROM users u
    LEFT JOIN user_profiles p ON p.user_id = u.id
    LEFT JOIN user_progress up ON up.user_id = u.id AND up.solved = 1
    GROUP BY u.id
    ORDER BY u.xp DESC, solved DESC, u.id ASC
")->fetchAll();

$totalChallenges = $pdo->query("SELECT COUNT(*) FROM challenges")->fetchColumn();
$myRank = 0;
foreach ($players as $i => $p) {
    if ($p['id'] == $user['id']) { $myRank = $i + 1; break; }
}

$pageTitle = 'Leaderboard';
include __DIR__ . '/includes/header.php';
?>

<main class="max-w-3xl mx-auto px-4 py-8">

    <div class="mb-8">
        <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
                <span class="text-yellow-400">🏆</span> Leaderboard
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                <?= count($players) ?> player<?= count($players) !== 1 ? 's' : '' ?> ranked by XP
                <?php if ($myRank): ?>
                    · <span class="text-indigo-300">You are #<?= $myRank ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php if (count($players) > 0): ?>

    <!-- Podium -->
    <?php
        $p1 = $players[0] ?? null;
        $p2 = $players[1] ?? null;
        $p3 = $players[2] ?? null;
    ?>
    <div class="mb-10">
        <div class="flex items-end justify-center gap-3">

            <!-- 2nd place -->
            <div class="flex-1 flex flex-col items-center max-w-[180px]">
                <?php if ($p2): ?>
                <div class="text-3xl mb-1">🥈</div>
                <div class="mb-1"><?= pixelAvatar((int)$p2['id'], 48, (int)$p2['avatar_id']) ?></div>
                <div class="text-sm font-semibold text-center truncate w-full px-1"><?= htmlspecialchars($p2['display_name']) ?></div>
                <div class="text-xs text-indigo-300 mb-2">⚡ <?= number_format($p2['xp']) ?></div>
                <?php else: ?>
                <div class="text-3xl mb-1 opacity-30">🥈</div>
                <div class="w-12 h-12 rounded-full bg-slate-700 border-2 border-dashed border-slate-600 mb-5"></div>
                <?php endif; ?>
                <div class="w-full h-24 bg-slate-700 rounded-t-xl flex items-center justify-center text-2xl font-black text-slate-400">2</div>
            </div>

            <!-- 1st place -->
            <div class="flex-1 flex flex-col items-center max-w-[180px]">
                <?php if ($p1): ?>
                <div class="text-4xl mb-1">👑</div>
                <div class="mb-1"><?= pixelAvatar((int)$p1['id'], 56, (int)$p1['avatar_id']) ?></div>
                <div class="text-sm font-bold text-center truncate w-full px-1 text-yellow-200"><?= htmlspecialchars($p1['display_name']) ?></div>
                <div class="text-xs text-yellow-300 mb-2">⚡ <?= number_format($p1['xp']) ?></div>
                <?php else: ?>
                <div class="text-4xl mb-1 opacity-30">👑</div>
                <div class="w-14 h-14 rounded-full bg-slate-700 border-2 border-dashed border-slate-600 mb-5"></div>
                <?php endif; ?>
                <div class="w-full h-36 bg-gradient-to-b from-yellow-700 to-yellow-900 rounded-t-xl flex items-center justify-center text-3xl font-black text-yellow-300">1</div>
            </div>

            <!-- 3rd place -->
            <div class="flex-1 flex flex-col items-center max-w-[180px]">
                <?php if ($p3): ?>
                <div class="text-3xl mb-1">🥉</div>
                <div class="mb-1"><?= pixelAvatar((int)$p3['id'], 48, (int)$p3['avatar_id']) ?></div>
                <div class="text-sm font-semibold text-center truncate w-full px-1"><?= htmlspecialchars($p3['display_name']) ?></div>
                <div class="text-xs text-indigo-300 mb-2">⚡ <?= number_format($p3['xp']) ?></div>
                <?php else: ?>
                <div class="text-3xl mb-1 opacity-30">🥉</div>
                <div class="w-12 h-12 rounded-full bg-slate-700 border-2 border-dashed border-slate-600 mb-5"></div>
                <?php endif; ?>
                <div class="w-full h-16 bg-slate-600 rounded-t-xl flex items-center justify-center text-2xl font-black text-slate-400">3</div>
            </div>

        </div>
    </div>

    <?php endif; ?>

    <!-- Full ranking table -->
    <div class="bg-slate-800 rounded-xl border border-slate-700 overflow-hidden">
        <div class="grid grid-cols-12 text-xs text-slate-400 uppercase tracking-wider px-5 py-3 border-b border-slate-700 bg-slate-900">
            <div class="col-span-1">#</div>
            <div class="col-span-5">Player</div>
            <div class="col-span-3 text-center">Rank</div>
            <div class="col-span-3 text-right">XP / Solved</div>
        </div>

        <?php foreach ($players as $i => $p):
            $rank    = $i + 1;
            $isMe    = $p['id'] == $user['id'];
            $rankIcon = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
            $solvedPct = $totalChallenges > 0 ? round($p['solved'] / $totalChallenges * 100) : 0;
            $title   = levelTitle($p['level']);
        ?>
        <div class="grid grid-cols-12 items-center px-5 py-3 border-b border-slate-700/40 last:border-0
            <?= $isMe ? 'bg-indigo-900/25' : 'hover:bg-slate-700/20' ?> transition relative">

            <?php if ($isMe): ?>
            <div class="absolute left-0 top-0 bottom-0 w-0.5 bg-indigo-500 rounded-r"></div>
            <?php endif; ?>

            <div class="col-span-1 font-bold text-sm <?= $rank <= 3 ? 'text-xl leading-none' : 'text-slate-400' ?>">
                <?= $rankIcon ?>
            </div>

            <div class="col-span-5 flex items-center gap-2 min-w-0">
                <?= pixelAvatar((int)$p['id'], 32, (int)$p['avatar_id']) ?>
                <div class="min-w-0">
                    <div class="font-semibold text-sm truncate <?= $isMe ? 'text-indigo-300' : 'text-white' ?>">
                        <?= htmlspecialchars($p['display_name']) ?>
                        <?= $isMe ? '<span class="text-xs text-indigo-500 ml-1">(you)</span>' : '' ?>
                    </div>
                    <?php if ($p['bio'] !== ''): ?><div class="text-xs text-slate-500 truncate"><?= htmlspecialchars($p['bio']) ?></div><?php endif; ?>
                    <?php if ($p['streak'] > 0): ?>
                    <div class="text-xs text-orange-400">🔥 <?= $p['streak'] ?>d</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-span-3 text-center">
                <div class="inline-block">
                    <div class="text-xs text-purple-300 font-semibold">Lv <?= $p['level'] ?></div>
                    <div class="text-xs text-slate-500 truncate max-w-24"><?= $title ?></div>
                </div>
            </div>

            <div class="col-span-3 text-right">
                <div class="font-bold text-indigo-300 text-sm">⚡ <?= number_format($p['xp']) ?></div>
                <div class="text-xs text-slate-500"><?= $p['solved'] ?>/<?= $totalChallenges ?> solved</div>
            </div>

        </div>
        <?php endforeach; ?>

        <?php if (empty($players)): ?>
        <div class="text-center text-slate-400 py-12">No players yet. Be the first!</div>
        <?php endif; ?>
    </div>

</main>
</body>
</html>
