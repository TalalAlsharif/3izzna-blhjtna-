<?php
session_start();

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'طريقة غير مسموحة', null, 405);
}

try {
    $email = sanitizeInput($_POST['email'] ?? '');
    $email = strtolower(trim($email));

    $password = (string)($_POST['password'] ?? '');
    $remember = isset($_POST['remember']) && $_POST['remember'] == '1';

    if ($email === '' || $password === '') {
        sendJsonResponse(false, 'يرجى ملء جميع الحقول');
    }

    if (!isValidEmail($email)) {
        sendJsonResponse(false, 'البريد الإلكتروني غير صحيح');
    }

    $stmt = $pdo->prepare("
        SELECT id, email, password, full_name, role, is_active, is_email_verified
        FROM users
        WHERE email = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJsonResponse(false, 'البريد الإلكتروني أو كلمة المرور غير صحيحة');
    }

    if (!password_verify($password, $user['password'])) {
        sendJsonResponse(false, 'البريد الإلكتروني أو كلمة المرور غير صحيحة');
    }

    if (!(int)$user['is_active']) {
        sendJsonResponse(false, 'حسابك غير نشط. يرجى التواصل مع الإدارة');
    }

    /* تعطيل شرط التحقق من البريد مؤقتاً */
    // if (!(int)$user['is_email_verified']) {
    //     sendJsonResponse(false, 'يرجى التحقق من بريدك الإلكتروني أولاً');
    // }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();

    /* تحديث آخر دخول (إذا عندك عمود last_login) */
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $updateStmt->execute([(int)$user['id']]);
    } catch (Throwable $e) {
        /* تجاهل لو العمود غير موجود */
    }

    if ($remember) {
        $token = generateToken(64);
        $expires = date('Y-m-d H:i:s', time() + REMEMBER_ME_DURATION);

        /* إذا الأعمدة غير موجودة راح يطلع خطأ - لذلك حاطينها داخل try */
        try {
            $tokenStmt = $pdo->prepare("
                UPDATE users
                SET remember_token = ?, remember_token_expires = ?
                WHERE id = ?
            ");
            $tokenStmt->execute([$token, $expires, (int)$user['id']]);

            setcookie('remember_token', $token, time() + REMEMBER_ME_DURATION, '/', '', true, true);
        } catch (Throwable $e) {
            /* تجاهل */
        }
    }

    /* تسجيل الجلسة إذا جدول user_sessions موجود */
    try {
        $sessionId = session_id();
        $ipAddress = getUserIP();
        $userAgent = getUserAgent();

        $sessionStmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_activity = NOW()
        ");
        $sessionStmt->execute([(int)$user['id'], $sessionId, $ipAddress, $userAgent]);
    } catch (Throwable $e) {
        /* تجاهل */
    }

    $redirect = ($user['role'] === 'admin')
        ? PUBLIC_URL . '/admin/dashboard.php'
        : PUBLIC_URL . '/user/dashboard.php';

    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
    }

    sendJsonResponse(true, 'تم تسجيل الدخول بنجاح', ['redirect' => $redirect]);

} catch (Throwable $e) {
    error_log("Login Error: " . $e->getMessage());
    sendJsonResponse(false, 'حدث خطأ أثناء تسجيل الدخول', [
        'debug' => ['message' => $e->getMessage()]
    ], 500);
}
