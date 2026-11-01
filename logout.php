<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the sign-out form to end your session.');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Your session expired. Reload the page and try again.');
}

$_SESSION = [];
$cookie = session_get_cookie_params();
if (ini_get('session.use_cookies')) {
    setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();
header('Location: /cpp_learning/login.php');
exit;
