<?php
/**
 * تشخيص مشكلة save.php
 * ضع هذا الملف في: /htdocs/api/user/answers/diagnostic.php
 * ثم افتح: https://3zna-b6b3na.ct.ws/api/user/answers/diagnostic.php
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<html><head><meta charset='UTF-8'><title>تشخيص</title></head><body dir='rtl' style='font-family:Arial,sans-serif; padding:20px;'>";
echo "<h1>🔍 تشخيص مشكلة حفظ الإجابات</h1>";

// 1. فحص الملفات المطلوبة
echo "<h2>1️⃣ فحص الملفات:</h2>";

$files = [
    '../../../config/constants.php',
    '../../../config/database.php',
    '../../../includes/functions.php'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    $color = $exists ? 'green' : 'red';
    $status = $exists ? '✅ موجود' : '❌ غير موجود';
    echo "<p style='color:$color'>$file → $status</p>";
}

// 2. فحص الاتصال بقاعدة البيانات
echo "<h2>2️⃣ فحص قاعدة البيانات:</h2>";

try {
    require_once '../../../config/database.php';
    echo "<p style='color:green'>✅ الاتصال بقاعدة البيانات ناجح</p>";
    
    // فحص جدول answers
    $stmt = $pdo->query("DESCRIBE answers");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p><strong>أعمدة جدول answers:</strong></p>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li>$col</li>";
    }
    echo "</ul>";
    
    // فحص UNIQUE constraint
    $stmt = $pdo->query("SHOW INDEX FROM answers WHERE Key_name = 'unique_user_project_question'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($indexes) > 0) {
        echo "<p style='color:green'>✅ UNIQUE constraint موجود</p>";
    } else {
        echo "<p style='color:red'>❌ UNIQUE constraint غير موجود!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ خطأ في قاعدة البيانات: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 3. فحص دالة checkAnswer
echo "<h2>3️⃣ فحص دالة checkAnswer:</h2>";

try {
    require_once '../../../includes/functions.php';
    
    if (function_exists('checkAnswer')) {
        echo "<p style='color:green'>✅ دالة checkAnswer موجودة</p>";
        
        // اختبار الدالة
        $testQuestion = [
            'question_type' => 'true_false',
            'meta' => json_encode(['correct_answer' => true])
        ];
        
        $testAnswer = ['answer' => true];
        
        try {
            $result = checkAnswer($testQuestion, $testAnswer);
            echo "<p style='color:green'>✅ دالة checkAnswer تعمل بشكل صحيح (النتيجة: " . ($result ? 'صح' : 'خطأ') . ")</p>";
        } catch (Exception $e) {
            echo "<p style='color:orange'>⚠️ دالة checkAnswer تطلع خطأ: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p style='color:red'>❌ دالة checkAnswer غير موجودة!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ خطأ في تحميل functions.php: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 4. فحص الجلسة
echo "<h2>4️⃣ فحص الجلسة:</h2>";

session_start();

if (isset($_SESSION['user_id'])) {
    echo "<p style='color:green'>✅ الجلسة نشطة - user_id: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p style='color:orange'>⚠️ لا توجد جلسة نشطة (سجل دخولك أولاً)</p>";
}

// 5. فحص PHP version وإعدادات
echo "<h2>5️⃣ معلومات الخادم:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>JSON متاح: " . (function_exists('json_encode') ? '✅ نعم' : '❌ لا') . "</p>";
echo "<p>PDO متاح: " . (class_exists('PDO') ? '✅ نعم' : '❌ لا') . "</p>";

// 6. فحص أخطاء PHP الأخيرة
echo "<h2>6️⃣ آخر أخطاء PHP:</h2>";

$errorLog = '../../../logs/php_errors.log';

if (file_exists($errorLog)) {
    $lines = file($errorLog);
    $lastErrors = array_slice($lines, -10);
    
    echo "<pre style='background:#f5f5f5; padding:15px; border-radius:5px; overflow:auto;'>";
    echo htmlspecialchars(implode('', $lastErrors));
    echo "</pre>";
} else {
    echo "<p style='color:orange'>⚠️ ملف error log غير موجود</p>";
}

echo "<hr>";
echo "<h2>📝 الخلاصة:</h2>";
echo "<p>إذا كل شيء أخضر ✅ والمشكلة لسه موجودة، أرسلني screenshot من هذه الصفحة</p>";

echo "</body></html>";