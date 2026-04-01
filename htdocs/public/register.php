<?php
session_start();
require_once __DIR__ . '/../config/constants.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - عزنا بلهجتنا</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/main.css">
    <style>
        .password-field-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            color: #6b7280;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:hover {
            color: #1a5c4b;
        }
        
        .toggle-password svg {
            width: 20px;
            height: 20px;
        }
        
        .form-input.has-toggle {
            padding-left: 45px;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-box">
        <div class="auth-card">
            <h2>إنشاء حساب جديد</h2>

            <div id="error-container"></div>
            <div id="success-container"></div>

            <form id="registerForm" method="POST" autocomplete="off">
                <div class="form-group">
                    <label class="form-label">الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-input" required minlength="3">
                </div>

                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="password" name="password" class="form-input has-toggle" required minlength="8">
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password', this)" aria-label="إظهار كلمة المرور">
                            <!-- أيقونة العين المغلقة (افتراضي) -->
                            <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                            <!-- أيقونة العين المفتوحة (مخفية) -->
                            <svg class="eye-on" style="display:none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                    <small style="display: block; margin-top: 5px; color: #6b7280; font-size: 12px;">
                        يجب أن تكون 8 أحرف على الأقل
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label">تأكيد كلمة المرور</label>
                    <div class="password-field-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input has-toggle" required>
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password', this)" aria-label="إظهار تأكيد كلمة المرور">
                            <!-- أيقونة العين المغلقة (افتراضي) -->
                            <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                <line x1="1" y1="1" x2="23" y2="23"></line>
                            </svg>
                            <!-- أيقونة العين المفتوحة (مخفية) -->
                            <svg class="eye-on" style="display:none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full" id="registerBtn">إنشاء حساب</button>
                
                <div style="text-align: center; margin-top: 15px;">
                    <span style="color: #6b7280; font-size: 14px;">لديك حساب بالفعل؟</span>
                    <a href="login.php" style="color: #9c2d5a; font-weight: 600; text-decoration: none; margin-right: 5px;">تسجيل الدخول</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// دالة إظهار/إخفاء كلمة المرور
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const eyeOff = button.querySelector('.eye-off');
    const eyeOn = button.querySelector('.eye-on');
    
    if (input.type === 'password') {
        input.type = 'text';
        eyeOff.style.display = 'none';
        eyeOn.style.display = 'block';
        button.setAttribute('aria-label', 'إخفاء كلمة المرور');
    } else {
        input.type = 'password';
        eyeOff.style.display = 'block';
        eyeOn.style.display = 'none';
        button.setAttribute('aria-label', 'إظهار كلمة المرور');
    }
}

// معالج نموذج التسجيل
document.getElementById('registerForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const form = this;
    const btn = document.getElementById('registerBtn');
    const errorContainer = document.getElementById('error-container');
    const successContainer = document.getElementById('success-container');

    errorContainer.innerHTML = '';
    successContainer.innerHTML = '';

    const p1 = document.getElementById('password').value;
    const p2 = document.getElementById('confirm_password').value;

    if (p1 !== p2) {
        errorContainer.innerHTML = '<div class="alert alert-error">كلمتا المرور غير متطابقتين</div>';
        return;
    }

    if (p1.length < 8) {
        errorContainer.innerHTML = '<div class="alert alert-error">كلمة المرور يجب أن تكون 8 أحرف على الأقل</div>';
        return;
    }

    let didSucceed = false;

    btn.disabled = true;
    btn.textContent = 'جاري إنشاء الحساب...';

    try {
        const response = await fetch('<?php echo API_URL; ?>/auth/register.php', {
            method: 'POST',
            body: new FormData(form)
        });

        const rawText = await response.text();
        let data;

        try {
            data = JSON.parse(rawText);
        } catch (err) {
            console.error('RAW RESPONSE:', rawText);
            throw new Error('Invalid JSON');
        }

        if (data.success) {
            didSucceed = true;

            successContainer.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';

            const loginUrl = 'login.php?success=registered';

            successContainer.innerHTML +=
                '<div style="margin-top:12px;">' +
                '<a class="btn btn-primary w-full" href="' + loginUrl + '">الانتقال لتسجيل الدخول</a>' +
                '</div>';

            btn.disabled = true;
            btn.textContent = 'تم، جاري تحويلك...';

            try {
                window.location.assign(loginUrl);
            } catch (e1) {}

            setTimeout(() => {
                try { window.top.location.href = loginUrl; } catch (e2) {}
                try { window.location.href = loginUrl; } catch (e3) {}
            }, 400);

            return;
        }

        errorContainer.innerHTML = '<div class="alert alert-error">' + (data.message || 'حدث خطأ') + '</div>';

    } catch (error) {
        console.error('Registration error:', error);
        errorContainer.innerHTML = '<div class="alert alert-error">حدث خطأ في الاتصال. حاول مرة أخرى</div>';
    } finally {
        if (!didSucceed) {
            btn.disabled = false;
            btn.textContent = 'إنشاء حساب';
        }
    }
});
</script>
</body>
</html>