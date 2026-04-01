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
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $questions = $_POST['questions'] ?? [];

    $isActive = (isset($_POST['is_active']) && $_POST['is_active'] === '1') ? 1 : 0;
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $maxParticipants = !empty($_POST['participant_limit']) ? (int)$_POST['participant_limit'] : null;

    $allowRetake = (isset($_POST['allow_retry']) && $_POST['allow_retry'] === '1') ? 1 : 0;
    $showResults = (isset($_POST['show_results']) && $_POST['show_results'] === '1') ? 1 : 0;
    $shuffleQuestions = (isset($_POST['shuffle_questions']) && $_POST['shuffle_questions'] === '1') ? 1 : 0;

    if (empty($title)) {
        sendJsonResponse(false, 'عنوان المشروع مطلوب');
    }

    if (empty($questions) || !is_array($questions)) {
        sendJsonResponse(false, 'يجب اختيار سؤال واحد على الأقل');
    }

    if ($startDate && $endDate) {
        if (strtotime($endDate) <= strtotime($startDate)) {
            sendJsonResponse(false, 'تاريخ الانتهاء يجب أن يكون بعد تاريخ البدء');
        }
    }

    // إزالة التكرارات من الأسئلة
    $questions = array_unique(array_map('intval', $questions));
    $questions = array_values($questions);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO projects (
            title, description, created_by, is_active, total_questions,
            start_date, end_date, participant_limit,
            allow_retake, show_results, shuffle_questions,
            current_participants
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        $title,
        $description,
        (int)$_SESSION['user_id'],
        $isActive,
        count($questions),
        $startDate,
        $endDate,
        $maxParticipants,
        $allowRetake,
        $showResults,
        $shuffleQuestions
    ]);

    $projectId = (int)$pdo->lastInsertId();
    if ($projectId <= 0) {
        throw new Exception('فشل الحصول على معرف المشروع بعد الإنشاء.');
    }

    if ($shuffleQuestions) {
        shuffle($questions);
    }

    $stmt = $pdo->prepare("
        INSERT INTO project_questions (project_id, question_id, question_order)
        VALUES (?, ?, ?)
    ");

    foreach ($questions as $index => $questionId) {
        $stmt->execute([$projectId, (int)$questionId, $index + 1]);
    }

    $pdo->commit();

    sendJsonResponse(true, 'تم إنشاء المشروع بنجاح', ['project_id' => $projectId]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Create Project Error: " . $e->getMessage());
    sendJsonResponse(false, 'حدث خطأ أثناء إنشاء المشروع: ' . $e->getMessage());
}
?>