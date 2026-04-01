<?php
session_start();
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

/* حذف remember me token من قاعدة البيانات إن وجد */
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
        $stmt->execute([ (int)$_SESSION['user_id'] ]);
    }
} catch (Throwable $e) {
    error_log("Logout DB Error: " . $e->getMessage());
}

/* حذف الكوكي */
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

/* إنهاء الجلسة */
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
}

session_destroy();

/* رجّع لصفحة الدخول */
header('Location: ' . BASE_URL . '/index.php');
exit;

