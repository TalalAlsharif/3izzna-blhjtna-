<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'طريقة غير مسموحة', null, 405);
}

try {
    // التحقق من وجود ملف
    if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
        sendJsonResponse(false, 'يرجى رفع ملف JSON صحيح');
    }
    
    $file = $_FILES['json_file'];
    
    // التحقق من نوع الملف
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'json') {
        sendJsonResponse(false, 'يجب أن يكون الملف بصيغة JSON');
    }
    
    // قراءة محتوى الملف
    $jsonContent = file_get_contents($file['tmp_name']);
    $questions = json_decode($jsonContent, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(false, 'ملف JSON غير صحيح: ' . json_last_error_msg());
    }
    
    if (!is_array($questions)) {
        sendJsonResponse(false, 'يجب أن يحتوي الملف على مصفوفة من الأسئلة');
    }
    
    $skipDuplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] == '1';
    
    $importedCount = 0;
    $skippedCount = 0;
    
    // البدء بمعاملة
    beginTransaction();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO qbank (question_text, question_type, meta, created_by)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($questions as $question) {
            // التحقق من الحقول المطلوبة
            if (!isset($question['question_text']) || !isset($question['question_type'])) {
                continue;
            }
            
            $questionText = trim($question['question_text']);
            $questionType = $question['question_type'];
            $meta = isset($question['meta']) ? $question['meta'] : [];
            
            // التحقق من نوع السؤال
            $validTypes = ['open_ended', 'multiple_choice', 'true_false', 'list_selection'];
            if (!in_array($questionType, $validTypes)) {
                continue;
            }
            
            // التحقق من التكرار
            if ($skipDuplicates) {
                $checkStmt = $pdo->prepare("SELECT id FROM qbank WHERE question_text = ?");
                $checkStmt->execute([$questionText]);
                if ($checkStmt->fetch()) {
                    $skippedCount++;
                    continue;
                }
            }
            
            // إدراج السؤال
            $stmt->execute([
                $questionText,
                $questionType,
                json_encode($meta, JSON_UNESCAPED_UNICODE),
                $_SESSION['user_id']
            ]);
            
            $importedCount++;
        }
        
        // تأكيد المعاملة
        commit();
        
        sendJsonResponse(true, 'تم استيراد الأسئلة بنجاح', [
            'imported_count' => $importedCount,
            'skipped_count' => $skippedCount
        ]);
        
    } catch (Exception $e) {
        rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Import Questions Error: " . $e->getMessage());
    sendJsonResponse(false, 'حدث خطأ أثناء استيراد الأسئلة');
}
?>