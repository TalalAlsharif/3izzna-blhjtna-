<?php
/**
 * إرسال البريد الإلكتروني (محاكاة صامتة)
 * لا يطبع أي مخرجات أبداً حتى لا يكسر JSON.
 */

function sendEmail($to, $subject, $body, $isHTML = true) {
    try {
        $currentDate = date('Y-m-d H:i:s');

        $logMessage  = "\n=====================================\n";
        $logMessage .= "To: " . $to . "\n";
        $logMessage .= "Subject: " . $subject . "\n";
        $logMessage .= "Date: " . $currentDate . "\n";
        $logMessage .= "-------------------------------------\n";
        $logMessage .= $body . "\n";
        $logMessage .= "=====================================\n\n";

        $logDir  = dirname(__DIR__) . '/logs';
        $logFile = $logDir . '/emails.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents($logFile, $logMessage, FILE_APPEND);
        return true;

    } catch (Throwable $e) {
        /* صامت */
        return true;
    }
}

function sendVerificationEmail($email, $fullName, $token) {
    $verificationLink = BASE_URL . "/public/verify_email.php?token=" . $token;

    $subject = "تأكيد البريد الإلكتروني - منصة تعليق البيانات";

    $body  = "مرحباً " . $fullName . ",\n\n";
    $body .= "شكراً لتسجيلك في منصة تعليق البيانات.\n\n";
    $body .= "رابط التحقق:\n" . $verificationLink . "\n\n";
    $body .= "هذا الرابط صالح لمدة 24 ساعة.\n\n";
    $body .= "مع التحية.\n";

    return sendEmail($email, $subject, $body);
}

/* دوال احتياطية (لو استخدمتها لاحقاً لا تسبب Fatal Error) */
function sendPasswordResetEmail($email, $fullName, $token) {
    $resetLink = BASE_URL . "/public/reset_password.php?token=" . $token;
    $subject = "استعادة كلمة المرور - منصة تعليق البيانات";
    $body  = "مرحباً " . $fullName . ",\n\n";
    $body .= "رابط إعادة التعيين:\n" . $resetLink . "\n\n";
    $body .= "مع التحية.\n";
    return sendEmail($email, $subject, $body);
}
