<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /cpp_learning/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CppQuest — C++ Output Challenges</title>
    <link rel="stylesheet" href="/cpp_learning/assets/tailwind.css">
    <link rel="stylesheet" href="/cpp_learning/assets/theme.css?v=20261101-contrast">
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <main class="mx-auto flex min-h-screen max-w-5xl items-center px-5 py-16">
        <div class="w-full rounded-3xl border border-indigo-500/30 bg-gradient-to-br from-slate-900 to-indigo-950/70 p-7 shadow-2xl sm:p-12">
            <div class="mb-8 inline-flex items-center gap-2 rounded-full border border-cyan-400/30 bg-cyan-950/40 px-4 py-2 text-sm font-semibold text-cyan-200">
                <span aria-hidden="true">⚡</span> CppQuest · Learn by playing
            </div>
            <h1 class="max-w-3xl text-4xl font-black leading-tight sm:text-6xl">
                The challenges are the game.
            </h1>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-slate-300">
                Read real C++ programs, predict exactly what they print, and submit your answer.
                Clear missions, unlock harder challenges, earn XP, and build your coding streak.
            </p>

            <ol class="mt-9 grid gap-4 sm:grid-cols-3">
                <li class="rounded-xl border border-slate-700 bg-slate-950/60 p-4">
                    <span class="text-sm font-bold text-indigo-300">01 · Study</span>
                    <p class="mt-2 text-sm text-slate-300">Inspect the code in each mission.</p>
                </li>
                <li class="rounded-xl border border-slate-700 bg-slate-950/60 p-4">
                    <span class="text-sm font-bold text-cyan-300">02 · Predict</span>
                    <p class="mt-2 text-sm text-slate-300">Work out the program's exact output.</p>
                </li>
                <li class="rounded-xl border border-slate-700 bg-slate-950/60 p-4">
                    <span class="text-sm font-bold text-amber-300">03 · Progress</span>
                    <p class="mt-2 text-sm text-slate-300">Submit, earn XP, and unlock new levels.</p>
                </li>
            </ol>

            <div class="mt-9 flex flex-wrap gap-3">
                <a href="/cpp_learning/register.php" class="rounded-lg bg-indigo-600 px-6 py-3 font-bold transition hover:bg-indigo-500">
                    Create account &amp; play
                </a>
                <a href="/cpp_learning/login.php" class="rounded-lg border border-slate-600 px-6 py-3 font-semibold text-slate-200 transition hover:border-indigo-400 hover:text-white">
                    Sign in
                </a>
            </div>
            <p class="mt-4 text-sm text-slate-400">Sign in to save your answers, progress, and XP.</p>
        </div>
    </main>
</body>
</html>
