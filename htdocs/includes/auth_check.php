<?php
/**
 * التحقق من صلاحيات المستخدم والجلسة
 * Data Annotation Platform - Authentication Check
 */

// بدء الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تضمين ملفات الإعداد
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';

/**
 * دالة للتحقق من تسجيل دخول المستخدم
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_email']);
}

/**
 * دالة للتحقق من أن المستخدم admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * دالة للحصول على بيانات المستخدم الحالي
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, email, full_name, role, is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * دالة للتحقق من نشاط الجلسة وانتهائها
 */
function checkSessionTimeout() {
    if (isLoggedIn()) {
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            
            // إذا انتهت مدة الجلسة
            if ($elapsed > SESSION_TIMEOUT) {
                session_unset();
                session_destroy();
                return false;
            }
        }
        
        // تحديث وقت آخر نشاط
        $_SESSION['last_activity'] = time();
        
        // تحديث قاعدة البيانات
        updateUserActivity();
    }
    
    return true;
}

/**
 * دالة لتحديث نشاط المستخدم في قاعدة البيانات
 */
function updateUserActivity() {
    if (!isLoggedIn()) {
        return;
    }
    
    global $pdo;
    
    try {
        $sessionId = session_id();
        $userId = $_SESSION['user_id'];
        $ipAddress = getUserIP();
        $userAgent = getUserAgent();
        
        // تحديث أو إدراج سجل الجلسة
        $stmt = $pdo->prepare("
            INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, last_activity)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_activity = NOW()
        ");
        
        $stmt->execute([$userId, $sessionId, $ipAddress, $userAgent]);
    } catch (Exception $e) {
        // تجاهل الخطأ إذا الجدول غير موجود
    }
}

/**
 * دالة للتحقق من Remember Me Token
 */
function checkRememberToken() {
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        global $pdo;
        
        $token = $_COOKIE['remember_token'];
        
        // البحث عن المستخدم بناءً على الـ token
        $stmt = $pdo->prepare("
            SELECT id, email, full_name, role, is_active 
            FROM users 
            WHERE remember_token = ? 
            AND remember_token_expires > NOW()
            AND is_active = 1
        ");
        
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // تسجيل دخول المستخدم
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['last_activity'] = time();
            
            // تحديث آخر تسجيل دخول
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return true;
        } else {
            // حذف الـ cookie إذا كان غير صالح
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    
    return false;
}

/**
 * دالة لإجبار المستخدم على تسجيل الدخول
 */
function requireLogin() {
    checkRememberToken();
    
    if (!checkSessionTimeout() || !isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . PUBLIC_URL . '/login.php?error=session_expired');
        exit;
    }
}

/**
 * دالة لإجبار صلاحيات admin
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header('Location: ' . PUBLIC_URL . '/user/dashboard.php?error=unauthorized');
        exit;
    }
}

/**
 * دالة لتسجيل خروج المستخدم
 */
function logout() {
    global $pdo;
    
    // حذف سجل الجلسة من قاعدة البيانات
    if (isLoggedIn()) {
        $sessionId = session_id();
        $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        
        // حذف remember token
        if (isset($_COOKIE['remember_token'])) {
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    
    // حذف جميع بيانات الجلسة
    session_unset();
    session_destroy();
}

// فحص الجلسة تلقائياً عند تضمين الملف
checkSessionTimeout();
checkRememberToken();
?>