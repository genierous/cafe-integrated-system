<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
}

$back = $_POST['back'] ?? $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
// Only allow redirecting back within this app
if (strpos($back, BASE_URL) !== 0 && strpos($back, '/') !== 0) {
    $back = 'dashboard.php';
}
header('Location: ' . $back);
exit;
