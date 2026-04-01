<?php
/**
 * دوال مساعدة عامة
 * Data Annotation Platform - Helper Functions
 */

/**
 * دالة لتنظيف وحماية النصوص من XSS
 * (مضافة لأن register.php يعتمد عليها)
 */
function sanitizeInput($data) {
    $data = trim((string)$data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * دالة للتحقق من صحة البريد الإلكتروني
 * (مضافة لأن register.php يعتمد عليها)
 */
function isValidEmail($email) {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * دالة لتوليد رمز عشوائي آمن
 */
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * دالة لإرسال استجابة JSON
 */
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    /* منع أي مخرجات سابقة تكسر JSON */
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    $jsonOptions = defined('JSON_OPTIONS')
        ? JSON_OPTIONS
        : (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo json_encode($response, $jsonOptions);
    exit;
}

/**
 * دالة للتحقق من قوة كلمة المرور
 */
function isStrongPassword($password) {
    // على الأقل 8 أحرف
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }

    // يجب أن تحتوي على حرف كبير وصغير ورقم
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }

    return true;
}

/**
 * دالة للحصول على IP المستخدم
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

/**
 * دالة للحصول على User Agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * دالة لتحويل التاريخ إلى صيغة عربية
 */
function formatArabicDate($date) {
    $timestamp = strtotime($date);
    $arabicMonths = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];

    $day = date('d', $timestamp);
    $month = $arabicMonths[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);

    return "$day $month $year - $time";
}

/**
 * دالة لحساب الوقت النسبي (منذ كم من الوقت)
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'الآن';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "منذ $minutes دقيقة";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "منذ $hours ساعة";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "منذ $days يوم";
    } else {
        return formatArabicDate($datetime);
    }
}

/**
 * دالة لحساب النسبة المئوية
 */
function calculatePercentage($part, $total) {
    if ($total == 0) {
        return 0;
    }
    return round(($part / $total) * 100, 2);
}

/**
 * دالة للتحقق من صلاحية JSON
 */
function isValidJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * دالة لتصحيح الإجابة تلقائياً
 */
function checkAnswer($question, $userAnswer) {
    $meta = json_decode($question['meta'], true);

    switch ($question['question_type']) {
        case 'true_false':
            return $userAnswer['answer'] === $meta['correct_answer'];

        case 'multiple_choice':
            if ($meta['allow_multiple']) {
                // إذا كان يسمح باختيارات متعددة
                sort($userAnswer['selected']);
                sort($meta['correct_answers']);
                return $userAnswer['selected'] === $meta['correct_answers'];
            } else {
                // اختيار واحد فقط
                return in_array($userAnswer['selected'][0], $meta['correct_answers']);
            }

        case 'list_selection':
            // إذا كانت هناك إجابات صحيحة محددة
            if (!empty($meta['correct_answers'])) {
                sort($userAnswer['selected']);
                sort($meta['correct_answers']);
                return $userAnswer['selected'] === $meta['correct_answers'];
            }
            // إذا لم تكن هناك إجابة صحيحة محددة (سؤال رأي)
            return null;

        case 'open_ended':
            // الأسئلة المفتوحة لا يمكن تصحيحها تلقائياً
            return null;

        default:
            return null;
    }
}

/**
 * دالة لتنظيف اسم الملف
 */
function sanitizeFilename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    return $filename;
}

/**
 * دالة للحصول على امتداد الملف
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * دالة للتحقق من نوع الملف المسموح
 */
function isAllowedFileType($filename) {
    $extension = getFileExtension($filename);
    return in_array($extension, ALLOWED_FILE_TYPES);
}

/**
 * دالة لإعادة التوجيه
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * دالة لإنشاء رابط محمي من CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(32);
    }
    return $_SESSION['csrf_token'];
}

/**
 * دالة للتحقق من CSRF Token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * دالة لتنسيق حجم الملف
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * دالة لقص النص
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}

/**
 * دالة لعرض رسائل الأخطاء
 */
function displayErrors($errors) {
    if (!empty($errors)) {
        echo '<div class="alert alert-error">';
        foreach ($errors as $error) {
            echo '<p>' . htmlspecialchars($error) . '</p>';
        }
        echo '</div>';
    }
}

/**
 * دالة لعرض رسائل النجاح
 */
function displaySuccess($message) {
    if (!empty($message)) {
        echo '<div class="alert alert-success">';
        echo '<p>' . htmlspecialchars($message) . '</p>';
        echo '</div>';
    }
}
?>
