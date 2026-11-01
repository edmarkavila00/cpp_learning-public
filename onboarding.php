<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$userId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT 1 FROM user_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
if ($stmt->fetchColumn()) {
    unset($_SESSION['onboarding_required']);
    header('Location: /cpp_learning/dashboard.php');
    exit;
}

$avatars = avatarOptions();
$displayNameInput = $_POST['display_name'] ?? $_SESSION['username'];
$displayName = trim(is_string($displayNameInput) ? $displayNameInput : '');
$selectedAvatar = filter_var($_POST['avatar_id'] ?? $_SESSION['avatar_id'], FILTER_VALIDATE_INT);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameLength = function_exists('mb_strlen') ? mb_strlen($displayName) : strlen($displayName);

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your form expired. Reload this page and try again.';
    } elseif ($nameLength < 2 || $nameLength > 32) {
        $error = 'Your in-game name must be between 2 and 32 characters.';
    } elseif ($selectedAvatar === false || !array_key_exists($selectedAvatar, $avatars)) {
        $error = 'Choose one of the available avatars.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, display_name, avatar_id, bio)
            VALUES (?, ?, ?, '')
        ");
        $stmt->execute([$userId, $displayName, $selectedAvatar]);
        unset($_SESSION['onboarding_required']);
        refreshSession($pdo, $userId);
        header('Location: /cpp_learning/dashboard.php');
        exit;
    }
}

$selectedAvatar = $selectedAvatar === false ? (int)$_SESSION['avatar_id'] : $selectedAvatar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to CppQuest</title>
    <link rel="stylesheet" href="/cpp_learning/assets/tailwind.css">
    <style>
        body { background: radial-gradient(ellipse at 50% 0%, rgba(79,70,229,.24), transparent 55%), #080b1b; }
        .pixel-avatar { image-rendering: pixelated; }
    </style>
    <link rel="stylesheet" href="/cpp_learning/assets/theme.css?v=20261101-contrast">
</head>
<body class="min-h-screen text-white">
<main class="mx-auto flex min-h-screen max-w-3xl items-center px-4 py-10">
    <section class="w-full rounded-3xl border border-indigo-500/30 bg-slate-900/95 p-6 shadow-2xl sm:p-10">
        <div class="mb-7 flex items-center gap-4 rounded-2xl border border-cyan-500/20 bg-cyan-950/20 p-4">
            <div id="welcomeAvatar"><?= pixelAvatar($userId, 72, $selectedAvatar) ?></div>
            <div>
                <p class="text-xs font-bold uppercase tracking-[.2em] text-cyan-300">A new adventure begins</p>
                <h1 class="mt-1 text-2xl font-black sm:text-3xl">Welcome to CppQuest!</h1>
                <p class="mt-1 text-sm text-slate-300">Choose your character and the name other players will see.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div role="alert" class="mb-5 rounded-xl border border-red-700 bg-red-950/50 px-4 py-3 text-sm text-red-200">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-7">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
            <label class="block">
                <span class="mb-2 block text-sm font-bold text-slate-200">Your in-game username</span>
                <input name="display_name" required minlength="2" maxlength="32" autocomplete="nickname"
                       value="<?= htmlspecialchars($displayName) ?>"
                       class="w-full rounded-xl border border-slate-600 bg-slate-950 px-4 py-3 text-white focus:border-indigo-400 focus:outline-none">
                <span class="mt-2 block text-xs text-slate-500">This is your player name. Your sign-in username stays <?= htmlspecialchars($_SESSION['username']) ?>.</span>
            </label>

            <fieldset>
                <legend class="mb-3 text-sm font-bold text-slate-200">Choose your avatar</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <?php foreach ($avatars as $avatarId => $avatarName): ?>
                        <label class="cursor-pointer">
                            <input class="peer sr-only" type="radio" name="avatar_id" value="<?= $avatarId ?>"
                                   <?= $selectedAvatar === $avatarId ? 'checked' : '' ?>>
                            <span class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-950/70 p-3 transition hover:border-slate-500 peer-checked:border-indigo-400 peer-checked:bg-indigo-950/60 peer-focus-visible:ring-2 peer-focus-visible:ring-indigo-400">
                                <?= pixelAvatar($userId, 44, $avatarId) ?>
                                <span class="text-xs font-semibold text-slate-200"><?= htmlspecialchars($avatarName) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="flex flex-col gap-3 rounded-xl border border-indigo-500/20 bg-indigo-950/30 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-indigo-300">Your unique player ID</p>
                    <p class="mt-1 font-mono text-xl font-black text-white"><?= htmlspecialchars(playerUid($userId)) ?></p>
                </div>
                <p class="max-w-sm text-xs leading-relaxed text-slate-400">This permanent ID is assigned to your account automatically and is different for every player.</p>
            </div>

            <button type="submit" class="w-full rounded-xl bg-indigo-600 px-6 py-3 font-bold transition hover:bg-indigo-500">
                Enter the lobby →
            </button>
        </form>
    </section>
</main>
<script>
document.querySelectorAll('input[name="avatar_id"]').forEach(input => {
    input.addEventListener('change', () => {
        const selectedAvatar = input.closest('label')?.querySelector('.pixel-avatar');
        const preview = document.getElementById('welcomeAvatar');
        if (!selectedAvatar || !preview) return;

        const avatar = selectedAvatar.cloneNode(true);
        avatar.setAttribute('width', '72');
        avatar.setAttribute('height', '72');
        preview.replaceChildren(avatar);
    });
});
</script>
</body>
</html>
