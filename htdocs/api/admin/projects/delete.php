<?php
/**
 * API لحذف مشروع
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../../../config/database.php';

// التحقق من تسجيل الدخول
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'يرجى تسجيل الدخول']);
    exit;
}

// تحقق من الدور - الـ session تستخدم user_role
$role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';
if ($role !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح - يجب أن تكون أدمن. الدور الحالي: ' . $role]);
    exit;
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);
$projectId = $input['project_id'] ?? null;

if (!$projectId) {
    echo json_encode(['success' => false, 'message' => 'معرف المشروع مطلوب']);
    exit;
}

try {
    global $pdo;
    
    // التحقق من وجود المشروع
    $stmt = $pdo->prepare("SELECT id, title FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();
    
    if (!$project) {
        echo json_encode(['success' => false, 'message' => 'المشروع غير موجود']);
        exit;
    }
    
    // حذف المشروع
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmt->execute([$projectId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم حذف المشروع بنجاح'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطأ في قاعدة البيانات: ' . $e->getMessage()
    ]);
}
?>
