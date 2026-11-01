<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (isLoggedIn()) { header('Location: /cpp_learning/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $username = trim(is_string($usernameInput) ? $usernameInput : '');
    $password = is_string($passwordInput) ? $passwordInput : '';

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        $error = 'Your form expired. Reload this page and try again.';
    } elseif ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Update streak
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $streak = $user['streak'];
            if ($user['last_played'] === $yesterday) $streak++;
            elseif ($user['last_played'] !== $today) $streak = 1;

            $pdo->prepare("UPDATE users SET streak=?, last_played=? WHERE id=?")
                ->execute([$streak, $today, $user['id']]);

            session_regenerate_id(true);
            $userId = (int)$user['id'];
            $_SESSION['user_id'] = $userId;
            $_SESSION['show_welcome_back'] = true;
            refreshSession($pdo, $userId);
            $_SESSION['streak'] = $streak;

            $profileStmt = $pdo->prepare("SELECT 1 FROM user_profiles WHERE user_id = ?");
            $profileStmt->execute([$userId]);
            if ($profileStmt->fetchColumn()) {
                unset($_SESSION['onboarding_required']);
                header('Location: /cpp_learning/dashboard.php');
            } else {
                $_SESSION['onboarding_required'] = true;
                header('Location: /cpp_learning/onboarding.php');
            }
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CppQuest — Login</title>
    <link rel="stylesheet" href="/cpp_learning/assets/tailwind.css">
    <style>body{background:#0f172a;}</style>
    <link rel="stylesheet" href="/cpp_learning/assets/theme.css?v=20261101-contrast">
</head>
<body class="min-h-screen flex items-center justify-center text-white">
<div class="w-full max-w-md px-4">
    <div class="text-center mb-8">
        <div class="text-5xl mb-3">⚡</div>
        <h1 class="text-3xl font-bold text-indigo-400">CppQuest</h1>
        <p class="text-slate-400 mt-1">Learn C++ by predicting code output</p>
    </div>
    <div class="bg-slate-800 rounded-2xl p-8 border border-slate-700">
        <h2 class="text-xl font-semibold mb-6">Sign In</h2>
        <?php if ($error): ?>
            <div role="alert" class="bg-red-900/50 border border-red-700 text-red-300 px-4 py-3 rounded-lg mb-4 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
            <div>
                <label for="loginUsername" class="block text-sm text-slate-400 mb-1">Username</label>
                <input id="loginUsername" type="text" name="username" required autofocus autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div>
                <label for="loginPassword" class="block text-sm text-slate-400 mb-1">Password</label>
                <input id="loginPassword" type="password" name="password" required autocomplete="current-password"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white focus:border-indigo-500 focus:outline-none transition">
            </div>
            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-500 font-semibold py-2.5 rounded-lg transition mt-2">
                Start Playing →
            </button>
        </form>
        <p class="text-center text-slate-400 text-sm mt-4">
            No account? <a href="/cpp_learning/register.php" class="text-indigo-400 hover:underline">Register free</a>
        </p>
    </div>
</div>
</body>
</html>
