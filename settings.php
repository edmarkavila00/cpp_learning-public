<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
refreshSession($pdo, (int)$_SESSION['user_id']);
$user = currentUser();
$passwordError = '';
$passwordChanged = isset($_GET['password_changed']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $passwordError = 'Your form expired. Reload this page and try again.';
    } elseif (strlen($newPassword) < 12) {
        $passwordError = 'Choose a new password with at least 12 characters.';
    } elseif (strlen($newPassword) > 72) {
        $passwordError = 'Choose a new password no longer than 72 bytes.';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'The new passwords do not match.';
    } else {
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([(int)$user['id']]);
        $storedHash = $stmt->fetchColumn();
        if (!is_string($storedHash) || !password_verify($currentPassword, $storedHash)) {
            $passwordError = 'Your current password is incorrect.';
        } elseif ($currentPassword === $newPassword) {
            $passwordError = 'Choose a new password different from your current one.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
            session_regenerate_id(true);
            header('Location: /cpp_learning/settings.php?password_changed=1');
            exit;
        }
    }
}

$pageTitle = 'Game Settings';
include __DIR__ . '/includes/header.php';
?>

<main class="settings-page mx-auto max-w-4xl px-4 py-8">
    <header class="settings-hero mb-7 flex flex-wrap items-center justify-between gap-5 rounded-2xl border p-5 sm:p-7">
        <div class="flex items-center gap-4">
            <div class="settings-hero-icon flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl text-2xl" aria-hidden="true">
                <i class="fa-solid fa-sliders"></i>
            </div>
            <div>
                <p class="text-xs font-black uppercase tracking-[.2em] text-emerald-300">Game preferences</p>
                <h1 class="mt-1 text-3xl font-black text-white sm:text-4xl">Settings</h1>
                <p class="mt-1 text-sm text-slate-300">Tune your CppQuest experience.</p>
            </div>
        </div>
        <a href="/cpp_learning/dashboard.php" class="settings-back rounded-lg border px-4 py-2.5 text-sm font-bold text-white">
            <i class="fa-solid fa-arrow-left mr-2" aria-hidden="true"></i>Back to game
        </a>
    </header>

    <div class="grid gap-5">
        <section class="settings-section rounded-2xl border p-5 sm:p-6" aria-labelledby="appearance-settings">
            <div class="mb-5 flex items-center gap-3">
                <span class="settings-section-icon"><i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i></span>
                <div>
                    <h2 id="appearance-settings" class="text-lg font-black text-white">Appearance</h2>
                    <p class="text-sm text-slate-400">Choose a light or dark color theme for every page.</p>
                </div>
            </div>

            <div class="settings-option flex items-center justify-between gap-4 py-4">
                <div class="flex items-start gap-3">
                    <span class="settings-option-icon"><i class="fa-solid fa-moon" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="font-bold text-white">Dark mode</h3>
                        <p class="mt-1 text-sm text-slate-400">Use a darker background and panels throughout CppQuest.</p>
                    </div>
                </div>
                <button type="button" class="settings-toggle" role="switch" aria-checked="false"
                        data-setting-toggle="theme" onclick="toggleDarkMode()" aria-label="Toggle dark mode">
                    <span class="settings-toggle-track"><span class="settings-toggle-thumb"></span></span>
                    <span data-setting-state class="settings-toggle-state">Off</span>
                </button>
            </div>
        </section>

        <section class="settings-section rounded-2xl border p-5 sm:p-6" aria-labelledby="audio-settings">
            <div class="mb-5 flex items-center gap-3">
                <span class="settings-section-icon"><i class="fa-solid fa-volume-high" aria-hidden="true"></i></span>
                <div>
                    <h2 id="audio-settings" class="text-lg font-black text-white">Audio</h2>
                    <p class="text-sm text-slate-400">Choose which sounds play during your adventures.</p>
                </div>
            </div>

            <div class="settings-options divide-y">
                <div class="settings-option flex items-center justify-between gap-4 py-4">
                    <div class="flex items-start gap-3">
                        <span class="settings-option-icon"><i class="fa-solid fa-bolt" aria-hidden="true"></i></span>
                        <div>
                            <h3 class="font-bold text-white">Sound effects</h3>
                            <p class="mt-1 text-sm text-slate-400">Enable button, challenge, and game feedback sounds.</p>
                        </div>
                    </div>
                    <button type="button" class="settings-toggle" role="switch" aria-checked="true"
                            data-setting-toggle="effects" onclick="toggleGameEffects()" aria-label="Toggle sound effects">
                        <span class="settings-toggle-track"><span class="settings-toggle-thumb"></span></span>
                        <span data-setting-state class="settings-toggle-state">On</span>
                    </button>
                </div>

                <div class="settings-option flex items-center justify-between gap-4 py-4">
                    <div class="flex items-start gap-3">
                        <span class="settings-option-icon"><i class="fa-solid fa-music" aria-hidden="true"></i></span>
                        <div>
                            <h3 class="font-bold text-white">Chiptune music</h3>
                            <p class="mt-1 text-sm text-slate-400">Play the CppQuest theme while this browser tab is open.</p>
                        </div>
                    </div>
                    <button type="button" class="settings-toggle" role="switch" aria-checked="false"
                            data-setting-toggle="music" onclick="toggleSettingsMusic(this)" aria-label="Toggle chiptune music">
                        <span class="settings-toggle-track"><span class="settings-toggle-thumb"></span></span>
                        <span data-setting-state class="settings-toggle-state">Off</span>
                    </button>
                </div>
                <p id="musicSettingsMessage" class="hidden text-sm text-amber-200" role="status"></p>
            </div>
        </section>

        <section class="settings-section rounded-2xl border p-5 sm:p-6" aria-labelledby="gameplay-settings">
            <div class="mb-5 flex items-center gap-3">
                <span class="settings-section-icon"><i class="fa-solid fa-gamepad" aria-hidden="true"></i></span>
                <div>
                    <h2 id="gameplay-settings" class="text-lg font-black text-white">Gameplay &amp; accessibility</h2>
                    <p class="text-sm text-slate-400">Adjust visual motion to fit your play style.</p>
                </div>
            </div>

            <div class="settings-option flex items-center justify-between gap-4 py-4">
                <div class="flex items-start gap-3">
                    <span class="settings-option-icon"><i class="fa-solid fa-wind" aria-hidden="true"></i></span>
                    <div>
                        <h3 class="font-bold text-white">Reduce animations</h3>
                        <p class="mt-1 text-sm text-slate-400">Limit interface transitions and decorative movement.</p>
                    </div>
                </div>
                <button type="button" class="settings-toggle" role="switch" aria-checked="false"
                        data-setting-toggle="motion" onclick="toggleReducedMotion()" aria-label="Toggle reduced motion">
                    <span class="settings-toggle-track"><span class="settings-toggle-thumb"></span></span>
                    <span data-setting-state class="settings-toggle-state">Off</span>
                </button>
            </div>
        </section>

        <section class="settings-section rounded-2xl border p-5 sm:p-6" aria-labelledby="account-settings">
            <div class="mb-5 flex items-center gap-3">
                <span class="settings-section-icon"><i class="fa-solid fa-user-gear" aria-hidden="true"></i></span>
                <div>
                    <h2 id="account-settings" class="text-lg font-black text-white">Account</h2>
                    <p class="text-sm text-slate-400">Manage your player identity and session.</p>
                </div>
            </div>

            <div class="settings-account flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center">
                <?= pixelAvatar((int)$user['id'], 48, (int)$user['avatar_id']) ?>
                <div class="min-w-0 flex-1">
                    <div class="truncate font-black text-white"><?= htmlspecialchars($user['display_name']) ?></div>
                    <div class="mt-1 font-mono text-xs text-slate-400">UID · <?= htmlspecialchars(playerUid((int)$user['id'])) ?></div>
                </div>
                <a href="/cpp_learning/profile.php?edit=1" class="settings-account-action rounded-lg border px-4 py-2 text-center text-sm font-bold text-white">
                    <i class="fa-solid fa-pen mr-2" aria-hidden="true"></i>Edit profile
                </a>
            </div>

            <section class="settings-option mt-5 border-t pt-5" aria-labelledby="change-password-title">
                <h3 id="change-password-title" class="font-bold text-white">Change password</h3>
                <p class="mt-1 text-sm text-slate-400">Change your password while signed in. Use 12–72 bytes; email recovery is not configured.</p>
                <?php if ($passwordError): ?>
                    <p class="mt-3 text-sm text-red-300" role="alert"><?= htmlspecialchars($passwordError) ?></p>
                <?php elseif ($passwordChanged): ?>
                    <p class="mt-3 text-sm text-emerald-300" role="status">Your password was changed.</p>
                <?php endif; ?>
                <form method="POST" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
                    <label class="text-sm text-slate-300 sm:col-span-2" for="currentPassword">
                        Current password
                        <input id="currentPassword" type="password" name="current_password" required autocomplete="current-password"
                               class="mt-1 block w-full rounded-lg border px-3 py-2">
                    </label>
                    <label class="text-sm text-slate-300" for="newPassword">
                        New password
                        <input id="newPassword" type="password" name="new_password" required minlength="12" maxlength="72" autocomplete="new-password"
                               class="mt-1 block w-full rounded-lg border px-3 py-2">
                    </label>
                    <label class="text-sm text-slate-300" for="confirmPassword">
                        Confirm new password
                        <input id="confirmPassword" type="password" name="confirm_password" required minlength="12" maxlength="72" autocomplete="new-password"
                               class="mt-1 block w-full rounded-lg border px-3 py-2">
                    </label>
                    <button type="submit" class="game-button rounded-lg px-4 py-2 font-bold sm:col-span-2">Update password</button>
                </form>
            </section>

            <div class="settings-signout mt-5 flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center">
                <span class="settings-signout-icon"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i></span>
                <div class="flex-1">
                    <h3 class="font-bold text-white">Sign out</h3>
                    <p class="mt-1 text-sm text-slate-400">End this session on this device.</p>
                </div>
                <form method="POST" action="/cpp_learning/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
                    <button type="submit" class="settings-signout-action rounded-lg border px-4 py-2 text-center text-sm font-bold">
                        Sign out
                    </button>
                </form>
            </div>
        </section>
    </div>
</main>

<script>
function toggleSettingsMusic(button) {
    const shouldEnable = button.getAttribute('aria-checked') !== 'true';
    const message = document.getElementById('musicSettingsMessage');
    const started = window.cppqSetMusicEnabled(shouldEnable);
    if (!started) {
        message.textContent = 'Chiptune playback is not supported by this browser.';
        message.classList.remove('hidden');
        return;
    }

    message.textContent = '';
    message.classList.add('hidden');
}

function toggleReducedMotion() {
    const enabled = document.querySelector('[data-setting-toggle="motion"]').getAttribute('aria-checked') !== 'true';
    window.cppqSetReducedMotion(enabled);
}

function toggleDarkMode() {
    const enabled = document.querySelector('[data-setting-toggle="theme"]').getAttribute('aria-checked') !== 'true';
    window.cppqSetTheme(enabled);
}
</script>
</body>
</html>
