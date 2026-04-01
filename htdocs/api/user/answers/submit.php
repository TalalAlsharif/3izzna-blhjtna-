<?php
session_start();

require_once '../../../config/database.php';
require_once '../../../config/constants.php';
require_once '../../../includes/auth_check.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'طريقة غير مسموحة', null, 405);
}

try {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(false, 'JSON غير صالح', [
            'json_error' => json_last_error_msg()
        ], 400);
    }

    $projectId = (int)($input['project_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($projectId <= 0 || $userId <= 0) {
        sendJsonResponse(false, 'بيانات غير مكتملة', null, 400);
    }

    $totalRow = fetchOne("
        SELECT COUNT(*) AS total
        FROM project_questions
        WHERE project_id = ?
    ", [$projectId]);

    $totalQuestions = (int)($totalRow['total'] ?? 0);

    $correctRow = fetchOne("
        SELECT COUNT(*) AS total
        FROM answers
        WHERE user_id = ? AND project_id = ? AND is_correct = 1
    ", [$userId, $projectId]);

    $correctAnswers = (int)($correctRow['total'] ?? 0);

    $score = ($totalQuestions > 0) ? (($correctAnswers / $totalQuestions) * 100) : 0;

    /* تأكد من وجود progress */
    $progress = fetchOne("
        SELECT id
        FROM user_project_progress
        WHERE user_id = ? AND project_id = ?
    ", [$userId, $projectId]);

    if (!$progress) {
        $stmtIns = $pdo->prepare("
            INSERT INTO user_project_progress (user_id, project_id, status, total_questions, started_at)
            VALUES (?, ?, 'in_progress', ?, NOW())
        ");
        $stmtIns->execute([$userId, $projectId, $totalQuestions]);

        $progress = fetchOne("
            SELECT id
            FROM user_project_progress
            WHERE user_id = ? AND project_id = ?
        ", [$userId, $projectId]);
    }

    $stmt = $pdo->prepare("
        UPDATE user_project_progress
        SET status = 'completed',
            score = ?,
            correct_answers = ?,
            total_questions = ?,
            completed_at = NOW()
        WHERE user_id = ? AND project_id = ?
    ");

    $stmt->execute([$score, $correctAnswers, $totalQuestions, $userId, $projectId]);

    sendJsonResponse(true, 'تم إنهاء المشروع بنجاح', [
        'score' => number_format($score, 2),
        'correct_answers' => $correctAnswers,
        'total_questions' => $totalQuestions
    ]);

} catch (Exception $e) {
    error_log("Submit Project Error: " . $e->getMessage());
    sendJsonResponse(false, 'حدث خطأ أثناء إنهاء المشروع', null, 500);
}
