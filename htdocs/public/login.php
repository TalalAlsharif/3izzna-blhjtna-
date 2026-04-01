<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }
    exit;
}

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

$error = '';
$success = isset($_GET['success']) ? $_GET['success'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($email) || empty($password)) {
        $error = 'empty_fields';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                if (!$user['is_active']) {
                    $error = 'account_inactive';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['last_activity'] = time();
                    
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    
                    if ($user['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } else {
                        header('Location: user/dashboard.php');
                    }
                    exit;
                }
            } else {
                $error = 'invalid_credentials';
            }
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
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
    <title>تسجيل الدخول - عزنا بلهجتنا</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/main.css">
    <style>
        .password-wrapper {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            padding: 5px;
        }
        .password-toggle:hover {
            color: #333;
        }
        .password-toggle svg {
            width: 20px;
            height: 20px;
        }
        .form-input.password-input {
            padding-left: 45px;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-card">
                <h2>تسجيل الدخول</h2>
                <p class="subtitle">سجل دخولك للمتابعة</p>

                <?php if ($success === 'logout'): ?>
                    <div class="alert alert-success">تم تسجيل الخروج بنجاح</div>
                <?php elseif ($success === 'registered'): ?>
                    <div class="alert alert-success">تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول</div>
                <?php endif; ?>
                
                <?php if ($error === 'invalid_credentials'): ?>
                    <div class="alert alert-error">البريد الإلكتروني أو كلمة المرور غير صحيحة</div>
                <?php elseif ($error === 'empty_fields'): ?>
                    <div class="alert alert-error">يرجى ملء جميع الحقول</div>
                <?php elseif ($error === 'account_inactive'): ?>
                    <div class="alert alert-error">حسابك موقوف. تواصل مع الإدارة</div>
                <?php elseif ($error === 'system_error'): ?>
                    <div class="alert alert-error">حدث خطأ في النظام. حاول مرة أخرى</div>
                <?php elseif ($error === 'session_expired'): ?>
                    <div class="alert alert-error">انتهت صلاحية الجلسة. سجل دخولك مرة أخرى</div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-input" placeholder="أدخل بريدك الإلكتروني" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label">كلمة المرور</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" class="form-input password-input" placeholder="أدخل كلمة المرور" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <svg id="eyeIcon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-checkbox">
                            <input type="checkbox" name="remember_me">
                            <span>تذكرني</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-full">تسجيل الدخول</button>

                    <div style="text-align: center; margin-top: 20px;">
                        <a href="forgot_password.php" style="color: var(--accent-magenta); text-decoration: none;">نسيت كلمة المرور؟</a>
                    </div>

                    <div class="auth-footer">
                        <span style="color: var(--text-gray);">لا تملك حساباً؟</span>
                        <a href="register.php">سجل الآن</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
            } else {
                passwordInput.type = 'password';
                eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }
    </script>
</body>
</html>
