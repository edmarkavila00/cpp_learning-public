<?php
$root = dirname(__DIR__);
$checks = [
    'session cookies are HTTP-only and same-site' => [
        $root . '/includes/auth.php',
        ["'httponly' => true", "'samesite' => 'Lax'"],
    ],
    'registration rotates the session and enforces a long password' => [
        $root . '/register.php',
        ['session_regenerate_id(true)', 'strlen($password) < 12', "name=\"csrf_token\""],
    ],
    'login has named controls, viewport, and CSRF validation' => [
        $root . '/login.php',
        ['name="viewport"', 'for="loginUsername"', 'for="loginPassword"', "isValidCsrfToken"],
    ],
    'answer submission checks CSRF and schedules reviews' => [
        $root . '/run.php',
        ['isValidCsrfToken($input[\'csrf_token\'] ?? null)', 'INSERT INTO challenge_reviews', "'output_feedback'"],
    ],
    'study group workflow is owner-scoped and supports assignments' => [
        $root . '/study_groups.php',
        ['owner_id = ?', 'INSERT INTO study_group_assignments', 'Member assignment progress'],
    ],
    'challenge reports are validated and manageable by configured reviewers' => [
        $root . '/report_challenge.php',
        ['isValidCsrfToken', 'issue_type', 'INTERVAL 1 HOUR'],
    ],
    'topic practice and progress review are connected' => [
        $root . '/challenges.php',
        ['topicFilter', 'data-category', 'cppq_challenge_category'],
    ],
    'production PHP templates use locally generated Tailwind CSS' => [
        $root . '/includes/header.php',
        ['/cpp_learning/assets/tailwind.css'],
    ],
];

$failures = [];
foreach ($checks as $name => [$path, $needles]) {
    $contents = file_get_contents($path);
    if ($contents === false) {
        $failures[] = "$name: could not read $path";
        continue;
    }

    foreach ($needles as $needle) {
        if (strpos($contents, $needle) === false) {
            $failures[] = "$name: missing expected content: $needle";
        }
    }
}

$features = file_get_contents($root . '/db/features.sql');
foreach (['challenge_reviews', 'challenge_reports', 'study_groups', 'study_group_members', 'study_group_assignments'] as $table) {
    if ($features === false || strpos($features, "CREATE TABLE IF NOT EXISTS $table") === false) {
        $failures[] = "database migration is missing $table";
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'All ' . count($checks) . ' smoke checks passed.' . PHP_EOL;
