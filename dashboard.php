<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();
$showWelcomeBack = !empty($_SESSION['show_welcome_back']);
unset($_SESSION['show_welcome_back']);

$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.difficulty, c.xp_reward, c.category,
           COALESCE(up.solved, 0) AS solved
    FROM challenges c
    LEFT JOIN user_progress up ON up.challenge_id = c.id AND up.user_id = ?
    ORDER BY FIELD(c.difficulty, 'beginner', 'intermediate', 'advanced'), c.order_num, c.id
");
$stmt->execute([$user['id']]);
$challenges = $stmt->fetchAll();

$totalSolved = count(array_filter($challenges, fn($challenge) => $challenge['solved']));
$totalCount = count($challenges);
$stmt = $pdo->prepare('SELECT COUNT(*) FROM challenge_reviews WHERE user_id = ? AND due_at <= CURDATE()');
$stmt->execute([(int)$user['id']]);
$dueReviewCount = (int)$stmt->fetchColumn();

$xpNext = xpForLevel((int)$user['level']);
$xpLevelStart = max(0, ((int)$user['level'] - 1) * 100);
$xpCurrent = max(0, (int)$user['xp'] - $xpLevelStart);
$xpPct = min(100, round($xpCurrent / max(1, $xpNext - $xpLevelStart) * 100));
$currentLevel = max(1, (int)$user['level']);
$currentRankTitle = levelTitle($currentLevel);
$rankStartLevel = $currentLevel;
while ($rankStartLevel > 1 && levelTitle($rankStartLevel - 1) === $currentRankTitle) {
    $rankStartLevel--;
}
$nextRankLevel = $currentLevel + 1;
while ($nextRankLevel <= 10 && levelTitle($nextRankLevel) === $currentRankTitle) {
    $nextRankLevel++;
}
$rankXpStart = ($rankStartLevel - 1) * 100;
$rankXpTarget = $nextRankLevel <= 10 ? ($nextRankLevel - 1) * 100 : null;
$rankProgress = $rankXpTarget === null
    ? 100
    : min(100, max(0, (int)round(((int)$user['xp'] - $rankXpStart) / max(1, $rankXpTarget - $rankXpStart) * 100)));
$pageTitle = 'Game Hub';
include __DIR__ . '/includes/header.php';
$earnedBadges = array_values(array_filter($badges, static fn($badge) => $badge['unlocked']));
$visibleBadges = array_slice($earnedBadges, 0, 7);
$remainingBadgeCount = max(0, count($earnedBadges) - count($visibleBadges));
?>

<main class="max-w-5xl mx-auto px-4 py-8">
    <section class="game-panel dashboard-player-card relative mb-6 overflow-hidden rounded-2xl border border-emerald-700 p-5 sm:p-7">
        <?php if (!empty($user['cover_path'])): ?>
            <img class="dashboard-player-cover" src="<?= htmlspecialchars($user['cover_path']) ?>" alt="" aria-hidden="true">
        <?php endif; ?>
        <div class="dashboard-player-cover-overlay" aria-hidden="true"></div>
        <div class="relative z-10 flex flex-col gap-5 sm:flex-row sm:items-center">
            <div class="relative shrink-0">
                <div class="relative rounded-xl border-2 border-emerald-700 bg-slate-950 p-2"><?= pixelAvatar((int)$user['id'], 72, (int)$user['avatar_id']) ?></div>
            </div>
            <div class="min-w-0 flex-1">
                <?php if ($showWelcomeBack): ?>
                    <div id="welcomeBackNotice" class="mb-1 text-xs font-bold uppercase tracking-[.22em] text-indigo-300 transition-opacity duration-500" role="status" aria-live="polite">
                        Welcome back · Level <?= max(1, (int)$user['level']) ?>
                    </div>
                <?php endif; ?>
                <h1 class="text-2xl font-black text-white sm:text-3xl"><?= htmlspecialchars($user['display_name']) ?></h1>
                <p class="mt-1 text-sm text-slate-400"><?= levelTitle((int)$user['level']) ?> · <?= number_format((int)$user['xp']) ?> XP earned</p>
                <p class="mt-1 font-mono text-xs text-slate-500">Player ID · <?= htmlspecialchars(playerUid((int)$user['id'])) ?></p>
                <p class="dashboard-streak-badge mt-2" aria-label="Daily streak: <?= (int)($_SESSION['streak'] ?? 0) ?> days">
                    <i class="fa-solid fa-fire" aria-hidden="true"></i>
                    <?= (int)($_SESSION['streak'] ?? 0) ?> day streak
                </p>
                <?php if ($user['bio'] !== ''): ?><p class="mt-2 text-sm text-slate-300"><?= htmlspecialchars($user['bio']) ?></p><?php endif; ?>
                <div class="dashboard-earned-badges mt-3" aria-label="Earned badges">
                    <span class="dashboard-earned-badges-label">Badges earned</span>
                    <div class="dashboard-earned-badges-list">
                        <?php if ($visibleBadges): ?>
                            <?php foreach ($visibleBadges as $badge): ?>
                                <span class="dashboard-earned-badge" title="<?= htmlspecialchars($badge['title'], ENT_QUOTES) ?>"
                                      aria-label="<?= htmlspecialchars($badge['title'], ENT_QUOTES) ?>">
                                    <i class="fa-solid <?= htmlspecialchars($badge['icon'], ENT_QUOTES) ?>" aria-hidden="true"></i>
                                </span>
                            <?php endforeach; ?>
                            <?php if ($remainingBadgeCount > 0): ?>
                                <button type="button" class="dashboard-more-badges"
                                        aria-haspopup="dialog" aria-controls="playerBadgesDialog" aria-expanded="false"
                                        aria-label="View all badges, including <?= $remainingBadgeCount ?> more earned badges">
                                    +<?= $remainingBadgeCount ?>
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="dashboard-no-badges">No badges earned yet</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <aside class="rank-showcase" aria-label="Player rank">
                <div class="rank-showcase-badge" aria-hidden="true">
                    <i class="fa-solid fa-crown"></i>
                    <span><?= $currentLevel ?></span>
                </div>
                <div class="rank-showcase-details">
                    <span class="rank-showcase-label">Current rank</span>
                    <strong><?= htmlspecialchars($currentRankTitle) ?></strong>
                    <?php if ($rankXpTarget !== null): ?>
                        <div class="rank-showcase-progress" role="progressbar"
                             aria-label="Progress to <?= htmlspecialchars(levelTitle($nextRankLevel)) ?>"
                             aria-valuemin="0" aria-valuemax="<?= $rankXpTarget - $rankXpStart ?>"
                             aria-valuenow="<?= min(max(0, (int)$user['xp'] - $rankXpStart), $rankXpTarget - $rankXpStart) ?>">
                            <span style="width:<?= $rankProgress ?>%"></span>
                        </div>
                        <span class="rank-showcase-next">
                            <?= number_format(max(0, $rankXpTarget - (int)$user['xp'])) ?> XP to <?= htmlspecialchars(levelTitle($nextRankLevel)) ?>
                        </span>
                    <?php else: ?>
                        <span class="rank-showcase-next">Top rank reached</span>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </section>

    <section class="mb-6" aria-labelledby="activity-title">
        <div class="mb-3">
            <h2 id="activity-title" class="text-lg font-bold text-white">Choose your next activity</h2>
            <p class="mt-1 text-sm text-slate-400">Jump into a challenge, explore, or play with others.</p>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
            <a href="/cpp_learning/challenges.php" class="game-panel activity-card activity-challenges rounded-xl border border-amber-500/50 p-4">
                <span class="text-2xl" aria-hidden="true">🧩</span>
                <span class="mt-2 block font-bold text-white">Challenges</span>
                <span class="mt-1 block text-xs text-slate-400"><?= $totalSolved ?> / <?= $totalCount ?> cleared</span>
            </a>
            <a href="/cpp_learning/code_game.php" class="game-panel activity-card activity-code-game rounded-xl border border-emerald-500/50 p-4">
                <span class="text-2xl" aria-hidden="true">⌨️</span>
                <span class="mt-2 block font-bold text-white">Code Lab</span>
                <span class="mt-1 block text-xs text-slate-400">Write, compile, and run C++</span>
            </a>
            <a href="/cpp_learning/adventure.php" class="game-panel activity-card activity-adventure rounded-xl border border-emerald-500/50 p-4">
                <span class="text-2xl" aria-hidden="true">🗺️</span>
                <span class="mt-2 block font-bold text-white">Adventure</span>
                <span class="mt-1 block text-xs text-slate-400">Explore the C++ frontier</span>
            </a>
            <a href="/cpp_learning/coop.php" class="game-panel activity-card activity-coop rounded-xl border border-cyan-500/50 p-4">
                <span class="text-2xl" aria-hidden="true">👥</span>
                <span class="mt-2 block font-bold text-white">Co-op</span>
                <span class="mt-1 block text-xs text-slate-400">Host or join a room</span>
            </a>
            <a href="/cpp_learning/leaderboard.php" class="game-panel activity-card activity-leaderboard rounded-xl border border-yellow-500/50 p-4">
                <span class="text-2xl" aria-hidden="true">🏆</span>
                <span class="mt-2 block font-bold text-white">Leaderboard</span>
                <span class="mt-1 block text-xs text-slate-400">See how you rank</span>
            </a>
            <a href="/cpp_learning/review.php" class="game-panel activity-card rounded-xl border border-emerald-500/50 p-4">
                <span class="text-2xl" aria-hidden="true">🧠</span>
                <span class="mt-2 block font-bold text-white">Review</span>
                <span class="mt-1 block text-xs text-slate-400"><?= $dueReviewCount ?> due to revisit</span>
            </a>
            <a href="/cpp_learning/study_groups.php" class="game-panel activity-card rounded-xl border border-cyan-500/50 p-4">
                <span class="text-2xl" aria-hidden="true">📚</span>
                <span class="mt-2 block font-bold text-white">Study Groups</span>
                <span class="mt-1 block text-xs text-slate-400">Join or assign missions</span>
            </a>
        </div>
    </section>

    <section aria-label="Player progress">
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-700 bg-slate-800 p-4 text-center">
                <div class="text-2xl font-bold text-indigo-400"><?= number_format((int)$user['xp']) ?></div>
                <div class="mt-1 text-xs text-slate-400">Total XP</div>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-800 p-4 text-center">
                <div class="text-2xl font-bold text-purple-400"><?= (int)$user['level'] ?></div>
                <div class="mt-1 text-xs text-slate-400"><?= levelTitle((int)$user['level']) ?></div>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-800 p-4 text-center">
                <div class="text-2xl font-bold text-green-400"><?= $totalSolved ?>/<?= $totalCount ?></div>
                <div class="mt-1 text-xs text-slate-400">Missions cleared</div>
            </div>
        </div>
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
            <div class="mb-2 flex justify-between gap-3 text-sm">
                <span class="text-slate-400">Level <?= (int)$user['level'] ?> — <span class="text-indigo-300"><?= levelTitle((int)$user['level']) ?></span></span>
                <span class="text-right text-indigo-400"><?= $xpCurrent ?> / <?= max(1, $xpNext - $xpLevelStart) ?> XP to next level</span>
            </div>
            <div class="h-3 w-full overflow-hidden rounded-full bg-slate-700">
                <div class="h-3 rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 transition-all duration-500" style="width:<?= $xpPct ?>%"></div>
            </div>
        </div>
    </section>
</main>
<?php if ($showWelcomeBack): ?>
<script>
window.setTimeout(() => {
    const notice = document.getElementById('welcomeBackNotice');
    if (!notice) return;
    notice.classList.add('opacity-0');
    window.setTimeout(() => notice.remove(), 500);
}, 4000);
</script>
<?php endif; ?>
</body>
</html>
