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
    $projectId = (int)($_POST['project_id'] ?? 0);
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $isPublic = isset($_POST['is_public']) && $_POST['is_public'] == '1' ? 1 : 0;
    $isActive = isset($_POST['is_active']) && $_POST['is_active'] == '1' ? 1 : 0;
    $questions = $_POST['questions'] ?? [];
    
    if (!$projectId || empty($title) || empty($questions)) {
        sendJsonResponse(false, 'بيانات غير مكتملة');
    }
    
    // ═══════════════════════════════════════════════════════════
    // 🛡️ إزالة التكرارات من الأسئلة
    // ═══════════════════════════════════════════════════════════
    $questions = array_unique(array_map('intval', $questions));
    $questions = array_values($questions);
    
    beginTransaction();
    
    try {
        // تحديث المشروع
        $stmt = $pdo->prepare("
            UPDATE projects 
            SET title = ?, description = ?, is_public = ?, is_active = ?, total_questions = ?
            WHERE id = ?
        ");
        $stmt->execute([$title, $description, $isPublic, $isActive, count($questions), $projectId]);
        
        // حذف الأسئلة القديمة
        $stmt = $pdo->prepare("DELETE FROM project_questions WHERE project_id = ?");
        $stmt->execute([$projectId]);
        
        // إضافة الأسئلة الجديدة
        $stmt = $pdo->prepare("
            INSERT INTO project_questions (project_id, question_id, question_order)
            VALUES (?, ?, ?)
        ");
        
        foreach ($questions as $index => $questionId) {
            $stmt->execute([$projectId, (int)$questionId, $index + 1]);
        }
        
        commit();
        
        sendJsonResponse(true, 'تم تحديث المشروع بنجاح');
        
    } catch (Exception $e) {
        rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Update Project Error: " . $e->getMessage());
    sendJsonResponse(false, 'حدث خطأ أثناء تحديث المشروع');
}
?>