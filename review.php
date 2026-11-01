<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT c.id, c.title, c.difficulty, c.category, r.interval_days, r.due_at,
           COALESCE(up.attempts, 0) AS attempts
    FROM challenge_reviews r
    JOIN challenges c ON c.id = r.challenge_id
    LEFT JOIN user_progress up ON up.user_id = r.user_id AND up.challenge_id = c.id
    WHERE r.user_id = ? AND r.due_at <= CURDATE()
    ORDER BY r.due_at, c.difficulty, c.order_num, c.id
");
$stmt->execute([$userId]);
$dueReviews = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT c.category, COUNT(*) AS total,
           SUM(CASE WHEN COALESCE(up.solved, 0) = 1 THEN 1 ELSE 0 END) AS solved,
           SUM(COALESCE(up.attempts, 0)) AS attempts
    FROM challenges c
    LEFT JOIN user_progress up ON up.user_id = ? AND up.challenge_id = c.id
    GROUP BY c.category
    ORDER BY c.category
");
$stmt->execute([$userId]);
$topicProgress = $stmt->fetchAll();

$pageTitle = 'Review & Progress';
include __DIR__ . '/includes/header.php';
?>

<main class="mx-auto max-w-5xl px-4 py-8">
    <header class="settings-hero mb-7 rounded-2xl border p-5 sm:p-7">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-emerald-300">Practice that sticks</p>
        <h1 class="mt-1 text-3xl font-black text-white">Review &amp; progress</h1>
        <p class="mt-2 text-sm text-slate-300">Correctly answer a review to schedule it farther out. A missed review returns tomorrow.</p>
    </header>

    <section class="settings-section rounded-2xl border p-5 sm:p-6" aria-labelledby="due-review-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="due-review-title" class="text-xl font-bold text-white">Due for review</h2>
                <p class="mt-1 text-sm text-slate-400"><?= count($dueReviews) ?> <?= count($dueReviews) === 1 ? 'challenge' : 'challenges' ?> ready to revisit</p>
            </div>
            <a href="/cpp_learning/challenges.php" class="game-button rounded-lg px-4 py-2 text-sm font-bold">Browse challenges</a>
        </div>
        <?php if (!$dueReviews): ?>
            <p class="mt-5 rounded-xl border border-slate-700 p-4 text-sm text-slate-300">Nothing is due right now. Solve challenges and they will appear here for later review.</p>
        <?php else: ?>
            <ul class="mt-4 divide-y divide-slate-700">
                <?php foreach ($dueReviews as $review): ?>
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <a class="font-bold text-emerald-300 underline" href="/cpp_learning/challenge.php?id=<?= (int)$review['id'] ?>"><?= htmlspecialchars($review['title']) ?></a>
                            <p class="mt-1 text-xs text-slate-400"><?= htmlspecialchars(ucfirst($review['category'])) ?> · <?= htmlspecialchars(ucfirst($review['difficulty'])) ?> · <?= (int)$review['attempts'] ?> attempts</p>
                        </div>
                        <span class="text-xs text-slate-400">Last interval: <?= (int)$review['interval_days'] ?> <?= (int)$review['interval_days'] === 1 ? 'day' : 'days' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="mt-6" aria-labelledby="topic-progress-title">
        <h2 id="topic-progress-title" class="mb-3 text-xl font-bold text-white">Progress by topic</h2>
        <?php if (!$topicProgress): ?>
            <p class="settings-section rounded-xl border p-4 text-sm text-slate-300">No challenge topics are available yet.</p>
        <?php else: ?>
            <div class="grid gap-3 sm:grid-cols-2">
                <?php foreach ($topicProgress as $topic): ?>
                    <?php $percent = (int)round((int)$topic['solved'] / max(1, (int)$topic['total']) * 100); ?>
                    <article class="settings-section rounded-xl border p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-bold text-white">
                                <a class="text-emerald-300 underline" href="/cpp_learning/challenges.php?category=<?= rawurlencode($topic['category']) ?>"><?= htmlspecialchars(ucfirst($topic['category'])) ?></a>
                            </h3>
                            <span class="text-sm text-slate-300"><?= (int)$topic['solved'] ?>/<?= (int)$topic['total'] ?> solved</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden bg-slate-700" role="progressbar"
                             aria-label="<?= htmlspecialchars($topic['category']) ?> topic completion"
                             aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>">
                            <div class="h-full bg-emerald-400" style="width:<?= $percent ?>%"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-400"><?= (int)$topic['attempts'] ?> total attempts</p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
