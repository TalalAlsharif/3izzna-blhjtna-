<?php
// ⚠️ لا تضع session_start() هنا!

// تحديد اللغة الافتراضية
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'ar';
}

// تبديل اللغة
if (isset($_GET['lang'])) {
    $requested_lang = $_GET['lang'];
    if (in_array($requested_lang, ['ar', 'en'])) {
        $_SESSION['lang'] = $requested_lang;
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit();
    }
}

// تحميل ملف اللغة
$lang = $_SESSION['lang'];
$lang_file = __DIR__ . "/../languages/{$lang}.php";

if (file_exists($lang_file)) {
    $translations = require $lang_file;
} else {
    $translations = require __DIR__ . "/../languages/ar.php";
}

// دالة الترجمة
function t($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

// الاتجاه
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';