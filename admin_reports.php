<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$configuredAdminIds = array_filter(
    array_map('trim', explode(',', (string)getenv('CPPQUEST_ADMIN_IDS'))),
    static fn(string $id): bool => ctype_digit($id) && (int)$id > 0
);
if (!in_array((string)(int)$_SESSION['user_id'], $configuredAdminIds, true)) {
    http_response_code(403);
    exit('You are not allowed to review challenge reports.');
}

$error = '';
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
    $status = is_string($_POST['status'] ?? null) ? $_POST['status'] : '';
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your form expired. Reload the page and try again.';
    } elseif ($reportId === false || $reportId < 1 || !in_array($status, ['open', 'reviewed', 'resolved'], true)) {
        http_response_code(400);
        $error = 'Choose a valid report status.';
    } else {
        $stmt = $pdo->prepare('UPDATE challenge_reports SET status = ? WHERE id = ?');
        $stmt->execute([$status, $reportId]);
        $notice = 'Report status updated.';
    }
}

$stmt = $pdo->query("
    SELECT r.id, r.issue_type, r.details, r.status, r.created_at,
           c.id AS challenge_id, c.title,
           COALESCE(p.display_name, u.username) AS reporter_name
    FROM challenge_reports r
    JOIN challenges c ON c.id = r.challenge_id
    JOIN users u ON u.id = r.user_id
    LEFT JOIN user_profiles p ON p.user_id = u.id
    ORDER BY FIELD(r.status, 'open', 'reviewed', 'resolved'), r.created_at DESC
    LIMIT 100
");
$reports = $stmt->fetchAll();

$pageTitle = 'Challenge Reports';
include __DIR__ . '/includes/header.php';
?>

<main class="mx-auto max-w-5xl px-4 py-8">
    <h1 class="mb-5 text-3xl font-black text-white">Challenge reports</h1>
    <?php if ($error): ?><p role="alert" class="mb-4 text-sm text-red-300"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($notice): ?><p role="status" class="mb-4 text-sm text-emerald-300"><?= htmlspecialchars($notice) ?></p><?php endif; ?>
    <?php if (!$reports): ?>
        <p class="settings-section rounded-xl border p-5 text-slate-300">No reports have been submitted.</p>
    <?php endif; ?>
    <div class="space-y-4">
        <?php foreach ($reports as $report): ?>
            <article class="settings-section rounded-xl border p-5">
                <div class="flex flex-wrap justify-between gap-2">
                    <h2 class="font-bold text-white">
                        <a class="text-emerald-300 underline" href="/cpp_learning/challenge.php?id=<?= (int)$report['challenge_id'] ?>"><?= htmlspecialchars($report['title']) ?></a>
                        · <?= htmlspecialchars(str_replace('_', ' ', $report['issue_type'])) ?>
                    </h2>
                    <span class="text-sm text-slate-400"><?= htmlspecialchars($report['created_at']) ?></span>
                </div>
                <p class="mt-2 text-sm text-slate-300">Reported by <?= htmlspecialchars($report['reporter_name']) ?></p>
                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-200"><?= htmlspecialchars($report['details'] !== '' ? $report['details'] : 'No additional details provided.') ?></p>
                <form method="POST" class="mt-4 flex flex-wrap items-center gap-2">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
                    <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                    <label class="text-sm text-slate-300">
                        Status
                        <select name="status" class="ml-1 rounded border px-2 py-1">
                            <?php foreach (['open', 'reviewed', 'resolved'] as $status): ?>
                                <option value="<?= $status ?>" <?= $report['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="game-button rounded px-3 py-1.5 text-sm font-bold">Save status</button>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
