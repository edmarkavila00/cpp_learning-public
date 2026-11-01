<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) { header('Location: /cpp_learning/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $confirmInput = $_POST['confirm'] ?? '';
    $username = trim(is_string($usernameInput) ? $usernameInput : '');
    $password = is_string($passwordInput) ? $passwordInput : '';
    $confirm = is_string($confirmInput) ? $confirmInput : '';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your form expired. Reload this page and try again.';
    } elseif (!$username || !$password || !$confirm) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 12) {
        $error = 'Password must be at least 12 characters.';
    } elseif (strlen($password) > 72) {
        $error = 'Password must be no more than 72 bytes.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username already taken.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password, streak, last_played) VALUES (?, ?, 1, ?)")
                ->execute([$username, $hash, date('Y-m-d')]);

            $userId = (int)$pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id']  = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['display_name'] = $username;
            $_SESSION['avatar_id'] = random_int(0, 11);
            $_SESSION['bio'] = '';
            $_SESSION['xp']       = 0;
            $_SESSION['level']    = 1;
            $_SESSION['streak']   = 1;
            $_SESSION['onboarding_required'] = true;

            header('Location: /cpp_learning/onboarding.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CppQuest — Register</title>
    <link rel="stylesheet" href="/cpp_learning/assets/tailwind.css">
    <style>body{background:#0f172a;}</style>
    <link rel="stylesheet" href="/cpp_learning/assets/theme.css?v=20261101-contrast">
</head>
<body class="min-h-screen flex items-center justify-center text-white">
<div class="w-full max-w-md px-4">
    <div class="text-center mb-8">
        <div class="text-5xl mb-3">⚡</div>
        <h1 class="text-3xl font-bold text-indigo-400">CppQuest</h1>
        <p class="text-slate-400 mt-1">Create your free account</p>
    </div>
    <div class="bg-slate-800 rounded-2xl p-8 border border-slate-700">
        <h2 class="text-xl font-semibold mb-6">Create Account</h2>
        <?php if ($error): ?>
            <div role="alert" class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded-lg mb-4 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
            <div>
                <label for="registerUsername" class="block text-sm text-slate-400 mb-1">Username</label>
                <input id="registerUsername" type="text" name="username" required autofocus minlength="3" autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div>
                <label for="registerPassword" class="block text-sm text-slate-400 mb-1">Password</label>
                <input id="registerPassword" type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div>
                <label for="registerConfirm" class="block text-sm text-slate-400 mb-1">Confirm Password</label>
                <input id="registerConfirm" type="password" name="confirm" required autocomplete="new-password"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
            </div>
            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-500 font-semibold py-2.5 rounded-lg transition mt-2">
                Create Account →
            </button>
        </form>
        <p class="text-center text-slate-400 text-sm mt-4">
            Have an account? <a href="/cpp_learning/login.php" class="text-indigo-400 hover:underline">Sign in</a>
        </p>
    </div>
</div>
</body>
</html>
