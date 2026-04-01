<?php
/**
 * API: Save Answer - نسخة محسّنة مع مقارنة ذكية
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

ob_start();

function sendResponse($success, $message, $data = null, $httpCode = 200) {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code($httpCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// دالة checkAnswer محسّنة (تتعامل مع المسافات والحروف العربية والرموز)
function checkAnswer($question, $userAnswer) {
    $meta = json_decode($question['meta'] ?? '{}', true);
    if (!is_array($meta)) return null;
    
    // دالة مساعدة لتنظيف النص العربي
    $normalizeText = function($text) {
        if (!is_string($text)) return $text;
        $text = trim((string)$text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = str_replace(['أ', 'إ', 'آ'], 'ا', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text);
        return mb_strtolower($text, 'UTF-8');
    };
    
    // دالة محسّنة لاستخراج الرمز (أ، ب، ج، د، A، B، C، D)
    $extractOption = function($text) {
        if (!is_string($text)) return $text;
        $text = trim($text);
        
        // إذا كان النص يبدأ برمز متبوع بـ ) أو . أو :
        if (preg_match('/^([أ-ي]|[A-Za-z])[)\.:]\s*/u', $text, $matches)) {
            return $matches[1];
        }
        
        // إذا كان النص رمز واحد فقط
        if (preg_match('/^([أ-ي]|[A-Za-z])$/u', $text)) {
            return $text;
        }
        
        return $text;
    };
    
    // دالة مقارنة نصوص محسّنة
    $compareTexts = function($text1, $text2) use ($normalizeText, $extractOption) {
        // استخراج الرموز
        $option1 = $extractOption($text1);
        $option2 = $extractOption($text2);
        
        // مقارنة الرموز
        $normalized1 = $normalizeText($option1);
        $normalized2 = $normalizeText($option2);
        
        if ($normalized1 === $normalized2) {
            return true;
        }
        
        // إذا فشلت مقارنة الرموز، قارن النص الكامل
        $fullNormalized1 = $normalizeText($text1);
        $fullNormalized2 = $normalizeText($text2);
        
        return $fullNormalized1 === $fullNormalized2;
    };
    
    // دالة مقارنة مصفوفات
    $compareArrays = function($arr1, $arr2) use ($compareTexts) {
        if (count($arr1) !== count($arr2)) return false;
        
        $matched = [];
        foreach ($arr1 as $item1) {
            $found = false;
            foreach ($arr2 as $idx => $item2) {
                if (!in_array($idx, $matched) && $compareTexts($item1, $item2)) {
                    $matched[] = $idx;
                    $found = true;
                    break;
                }
            }
            if (!$found) return false;
        }
        
        return true;
    };

    switch ($question['question_type']) {
        case 'true_false':
            if (!isset($meta['correct_answer'])) return null;
            
            // تحويل true/false إلى صح/خطأ
            $userAns = $userAnswer['answer'];
            if ($userAns === true || $userAns === 'true' || $userAns === 'صحيح' || $userAns === 'صح') {
                $userAns = 'صحيح';
            } elseif ($userAns === false || $userAns === 'false' || $userAns === 'خطأ') {
                $userAns = 'خطأ';
            }
            
            $correctAns = $meta['correct_answer'];
            if (strpos($correctAns, 'صح') !== false || strpos($correctAns, 'A)') !== false) {
                $correctAns = 'صحيح';
            } elseif (strpos($correctAns, 'خطأ') !== false || strpos($correctAns, 'B)') !== false) {
                $correctAns = 'خطأ';
            }
            
            return $compareTexts($userAns, $correctAns);

        case 'multiple_choice':
            // دعم كل من correct_answer و correct_answers
            $correctAnswers = [];
            if (!empty($meta['correct_answers'])) {
                $correctAnswers = $meta['correct_answers'];
            } elseif (!empty($meta['correct_answer'])) {
                $correctAnswers = [$meta['correct_answer']];
            } else {
                return null;
            }
            
            if (!empty($meta['allow_multiple'])) {
                $selected = $userAnswer['selected'] ?? [];
                return $compareArrays($selected, $correctAnswers);
            } else {
                $selected = $userAnswer['selected'][0] ?? null;
                foreach ($correctAnswers as $correct) {
                    if ($compareTexts($selected, $correct)) return true;
                }
                return false;
            }

        case 'list_selection':
            // دعم كل من correct_answer و correct_answers
            $correctAnswers = [];
            if (!empty($meta['correct_answers'])) {
                $correctAnswers = $meta['correct_answers'];
            } elseif (!empty($meta['correct_answer'])) {
                $correctAnswers = [$meta['correct_answer']];
            } else {
                return null;
            }
            
            $selected = $userAnswer['selected'] ?? [];
            return $compareArrays($selected, $correctAnswers);

        case 'open_ended':
            return null;

        default:
            return null;
    }
}

try {
    // 1. تضمين الملفات (بدون functions.php)
    require_once '../../../config/constants.php';
    require_once '../../../config/database.php';
    
    // 2. بدء الجلسة
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
    
    // 3. التحقق من الجلسة
    if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
        sendResponse(false, 'الجلسة منتهية. سجل دخولك مرة أخرى', null, 401);
    }
    
    $userId = (int)$_SESSION['user_id'];
    
    // 4. التحقق من طريقة الطلب
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'طريقة غير مسموحة', null, 405);
    }
    
    // 5. قراءة البيانات
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        sendResponse(false, 'الطلب فارغ', null, 400);
    }
    
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'بيانات غير صالحة', null, 400);
    }
    
    // 6. استخراج البيانات
    $projectId = isset($input['project_id']) ? (int)$input['project_id'] : 0;
    $questionId = isset($input['question_id']) ? (int)$input['question_id'] : 0;
    $answerData = isset($input['answer_data']) ? $input['answer_data'] : null;
    
    if ($projectId <= 0 || $questionId <= 0 || !is_array($answerData)) {
        sendResponse(false, 'بيانات غير مكتملة', null, 400);
    }
    
    // 7. التحقق من وجود السؤال
    $stmt = $pdo->prepare("SELECT id, question_type, meta FROM qbank WHERE id = ? LIMIT 1");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$question) {
        sendResponse(false, 'السؤال غير موجود', null, 404);
    }
    
    $questionType = $question['question_type'];
    
    // 8. التحقق من صحة البيانات حسب نوع السؤال
    if ($questionType === 'open_ended') {
        if (!isset($answerData['text']) || trim($answerData['text']) === '') {
            sendResponse(false, 'النص فارغ', null, 400);
        }
    } elseif ($questionType === 'true_false') {
        if (!isset($answerData['answer'])) {
            sendResponse(false, 'الإجابة مفقودة', null, 400);
        }
        if ($answerData['answer'] === 'true') {
            $answerData['answer'] = true;
        } elseif ($answerData['answer'] === 'false') {
            $answerData['answer'] = false;
        }
    } else {
        if (!isset($answerData['selected']) || !is_array($answerData['selected']) || count($answerData['selected']) === 0) {
            sendResponse(false, 'لم تختر أي إجابة', null, 400);
        }
    }
    
    // 9. حساب صحة الإجابة
    $isCorrect = null;
    
    try {
        $isCorrect = checkAnswer($question, $answerData);
    } catch (Exception $e) {
        // تجاهل أخطاء التصحيح - ليس حرجاً
        error_log('checkAnswer error: ' . $e->getMessage());
    }
    
    // 10. تحويل البيانات إلى JSON
    $answerJson = json_encode($answerData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // 11. محاولة الحفظ
    // الطريقة الأولى: محاولة التحديث
    $updateStmt = $pdo->prepare("
        UPDATE answers 
        SET answer_data = ?, is_correct = ?, answered_at = NOW()
        WHERE user_id = ? AND project_id = ? AND question_id = ?
    ");
    
    $updateStmt->execute([$answerJson, $isCorrect, $userId, $projectId, $questionId]);
    
    // إذا لم يتم التحديث (لا يوجد صف)، نحاول الإدراج
    if ($updateStmt->rowCount() === 0) {
        try {
            $insertStmt = $pdo->prepare("
                INSERT INTO answers (user_id, project_id, question_id, answer_data, is_correct, answered_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $insertStmt->execute([$userId, $projectId, $questionId, $answerJson, $isCorrect]);
            
        } catch (PDOException $e) {
            // إذا فشل الإدراج بسبب تكرار
            if ($e->getCode() == 23000) {
                // نحاول التحديث مرة أخرى
                $updateStmt->execute([$answerJson, $isCorrect, $userId, $projectId, $questionId]);
            } else {
                throw $e;
            }
        }
    }
    
    // 12. تحديث آخر نشاط
    $_SESSION['last_activity'] = time();
    
    // 13. إرسال الاستجابة
    sendResponse(true, 'تم حفظ الإجابة بنجاح', [
        'user_id' => $userId,
        'project_id' => $projectId,
        'question_id' => $questionId,
        'is_correct' => $isCorrect
    ]);
    
} catch (PDOException $e) {
    error_log('Save Answer DB Error: ' . $e->getMessage());
    sendResponse(false, 'خطأ في قاعدة البيانات', null, 500);
    
} catch (Exception $e) {
    error_log('Save Answer Error: ' . $e->getMessage());
    sendResponse(false, 'حدث خطأ أثناء الحفظ', null, 500);
}