<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../includes/email_sender.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// التحقق من الـ token
if (empty($token)) {
    header('Location: login.php?error=invalid_token');
    exit;
}

try {
    $reset = fetchOne("
        SELECT * FROM password_resets 
        WHERE token = ? AND expires_at > NOW()
    ", [$token]);
    
    if (!$reset) {
        header('Location: login.php?error=expired_token');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    header('Location: login.php?error=system_error');
    exit;
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $error = 'empty_fields';
    } elseif ($password !== $confirmPassword) {
        $error = 'password_mismatch';
    } elseif (!isStrongPassword($password)) {
        $error = 'weak_password';
    } else {
        try {
            // تحديث كلمة المرور
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $reset['email']]);
            
            // حذف الـ token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            
            // إرسال إشعار
            $user = fetchOne("SELECT full_name FROM users WHERE email = ?", [$reset['email']]);
            sendPasswordChangedNotification($reset['email'], $user['full_name']);
            
            header('Location: login.php?success=password_reset');
            exit;
            
        } catch (Exception $e) {
            error_log("Update Password Error: " . $e->getMessage());
            $error = 'system_error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/main.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/responsive.css">
</head>
<body style="background: linear-gradient(135deg, var(--green-gradient-start) 0%, var(--green-gradient-end) 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    
    <div style="width: 100%; max-width: 450px;">
        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="background: var(--dark-green-sidebar); padding: 20px 30px; border-radius: var(--radius-lg); display: inline-block; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                <h1 style="color: white; font-size: 28px; font-weight: 700; margin: 0 0 5px 0; letter-spacing: 2px;">DATA</h1>
                <h2 style="color: white; font-size: 20px; font-weight: 600; margin: 0 0 3px 0; letter-spacing: 1px;">ANNOTATION</h2>
                <p style="color: rgba(255,255,255,0.8); font-size: 12px; margin: 0; letter-spacing: 1px;">PLATFORM</p>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card" style="box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <h2 style="font-size: 24px; font-weight: 700; color: var(--dark-green-panel); margin-bottom: 10px; text-align: center;">
                إعادة تعيين كلمة المرور
            </h2>
            <p style="text-align: center; color: var(--text-gray); margin-bottom: 30px; font-size: 14px;">
                أدخل كلمة المرور الجديدة
            </p>

            <!-- Messages -->
            <?php if ($error === 'empty_fields'): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    جميع الحقول مطلوبة
                </div>
            <?php elseif ($error === 'password_mismatch'): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    كلمات المرور غير متطابقة
                </div>
            <?php elseif ($error === 'weak_password'): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    كلمة المرور ضعيفة. يجب أن تحتوي على 8 أحرف على الأقل، حرف كبير، حرف صغير، ورقم
                </div>
            <?php elseif ($error === 'system_error'): ?>
                <div class="alert alert-error" style="margin-bottom: 20px;">
                    حدث خطأ. حاول مرة أخرى.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور الجديدة</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input" 
                        placeholder="أدخل كلمة المرور الجديدة"
                        required
                        autofocus
                    >
                    <small style="color: var(--text-gray); font-size: 12px; display: block; margin-top: 5px;">
                        8 أحرف على الأقل، حرف كبير، حرف صغير، ورقم
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">تأكيد كلمة المرور</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-input" 
                        placeholder="أعد إدخال كلمة المرور"
                        required
                    >
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 20px;">
                    ✓ تعيين كلمة المرور
                </button>

                <div style="text-align: center;">
                    <a href="login.php" style="color: var(--burgundy-accent); text-decoration: none; font-size: 14px; font-weight: 500;">
                        ← العودة لتسجيل الدخول
                    </a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>