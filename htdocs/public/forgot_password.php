<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'يرجى إدخال البريد الإلكتروني';
        $messageType = 'error';
    } else {
        try {
            $user = fetchOne("SELECT id, full_name FROM users WHERE email = ?", [$email]);
            
            if ($user) {
                $token = generateToken(64);
                $expires = date('Y-m-d H:i:s', time() + 3600);
                
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);
                
                $message = 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني';
                $messageType = 'success';
            } else {
                $message = 'البريد الإلكتروني غير مسجل';
                $messageType = 'error';
            }
        } catch (Exception $e) {
            $message = 'حدث خطأ. يرجى المحاولة لاحقاً';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور - عزنا بلهجتنا</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/main.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-logo">
                <div class="auth-logo-img">
                    <img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا">
                </div>
                <h1>عِزّنا بلهجتنا</h1>
            </div>

            <div class="auth-card">
                <h2>استعادة كلمة المرور</h2>
                <p class="subtitle">أدخل بريدك الإلكتروني وسنرسل لك رابط لإعادة تعيين كلمة المرور</p>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-input" placeholder="أدخل بريدك الإلكتروني" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">إرسال رابط الاستعادة</button>

                    <div class="auth-footer">
                        <a href="login.php">العودة لتسجيل الدخول</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
