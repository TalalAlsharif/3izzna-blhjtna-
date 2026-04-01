<?php
session_start();

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

/* سجّل الأخطاء في ملف بدل ما تطلع للمتصفح */
@ini_set('log_errors', '1');
@ini_set('error_log', dirname(__DIR__, 2) . '/logs/php_errors.log');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'طريقة غير مسموحة', null, 405);
}

try {
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $email    = sanitizeInput($_POST['email'] ?? '');
    $email    = strtolower(trim($email));

    $password        = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
        sendJsonResponse(false, 'يرجى ملء جميع الحقول');
    }

    if (strlen($fullName) < 3) {
        sendJsonResponse(false, 'الاسم يجب أن يكون 3 أحرف على الأقل');
    }

    if (!isValidEmail($email)) {
        sendJsonResponse(false, 'البريد الإلكتروني غير صحيح');
    }

    if ($password !== $confirmPassword) {
        sendJsonResponse(false, 'كلمتا المرور غير متطابقتين');
    }

    if (!isStrongPassword($password)) {
        sendJsonResponse(false, 'كلمة المرور يجب أن تحتوي على 8 أحرف على الأقل، حرف كبير، حرف صغير، ورقم');
    }

    $pdo->beginTransaction();

    /* التحقق من التكرار */
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        $pdo->rollBack();
        sendJsonResponse(false, 'البريد الإلكتروني مسجل مسبقاً');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    /*
        تعطيل التحقق من الإيميل مؤقتاً:
        - نخلي is_email_verified = 1 مباشرة
        - ولا نسوي insert في email_verifications
        - ولا نرسل بريد
    */
    $insertStmt = $pdo->prepare("
        INSERT INTO users (email, password, full_name, role, is_active, is_email_verified)
        VALUES (?, ?, ?, 'user', 1, 1)
    ");
    $insertStmt->execute([$email, $hashedPassword, $fullName]);

    $pdo->commit();

    sendJsonResponse(true, 'تم التسجيل بنجاح! يمكنك تسجيل الدخول الآن.');

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Registration Error: " . $e->getMessage());

    sendJsonResponse(false, 'حدث خطأ أثناء التسجيل', [
        'debug' => ['message' => $e->getMessage()]
    ], 500);
}
