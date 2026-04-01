<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login.php?error=invalid_token');
    exit;
}

try {
    // البحث عن الـ token
    $verification = fetchOne("
        SELECT * FROM email_verifications 
        WHERE token = ? AND expires_at > NOW()
    ", [$token]);
    
    if (!$verification) {
        header('Location: login.php?error=expired_token');
        exit;
    }
    
    // تحديث المستخدم
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_email_verified = 1 
        WHERE email = ?
    ");
    $stmt->execute([$verification['email']]);
    
    // حذف الـ token
    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE token = ?");
    $stmt->execute([$token]);
    
    header('Location: login.php?success=email_verified');
    exit;
    
} catch (Exception $e) {
    error_log("Verify Email Error: " . $e->getMessage());
    header('Location: login.php?error=system_error');
    exit;
}
?>