<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();

function safeJsonDecode($json) {
    if (!is_string($json) || trim($json) === '') return [];
    $data = json_decode($json, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($data)) ? $data : [];
}

function extractOptionsFromQuestionText($questionText) {
    $result = [
        'clean_text' => $questionText,
        'options' => [],
        'correct_letter' => null
    ];

    if (!is_string($questionText)) return $result;

    if (preg_match('/الإجابة\s*الصحيحة\s*:\s*([أ-يA-D])/u', $questionText, $m)) {
        $result['correct_letter'] = trim($m[1]);
    }

    $parts = preg_split('/الخيارات\s*:\s*/u', $questionText, 2);
    if (count($parts) < 2) return $result;

    $before = $parts[0];
    $after = $parts[1];

    $result['clean_text'] = trim(preg_replace('/الإجابة\s*الصحيحة\s*:.*$/u', '', $before));
    $after = preg_replace('/الإجابة\s*الصحيحة\s*:.*$/u', '', $after);

    if (preg_match_all('/([أ-يA-D])\)\s*(.+?)(?=(?:\n\s*[أ-يA-D]\)\s*)|$)/us', $after, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $row) {
            $letter = trim($row[1]);
            $text = trim($row[2]);
            if ($text !== '') $result['options'][] = $letter . ') ' . $text;
        }
    }

    return $result;
}

$rows = fetchAll("SELECT id, question_text, question_type, meta FROM qbank ORDER BY id ASC");
$updated = 0;

foreach ($rows as $r) {
    $id = (int)$r['id'];
    $qType = $r['question_type'];
    $meta = safeJsonDecode($r['meta'] ?? '');
    $fallback = extractOptionsFromQuestionText($r['question_text'] ?? '');

    // فقط الأنواع اللي تحتاج خيارات
    if (in_array($qType, ['multiple_choice', 'list_selection'], true)) {
        $hasOptions = !empty($meta['options']) && is_array($meta['options']);
        if (!$hasOptions && !empty($fallback['options'])) {
            $meta['options'] = $fallback['options'];
        }

        if (empty($meta['correct_answers']) && !empty($fallback['correct_letter'])) {
            $meta['correct_answers'] = [$fallback['correct_letter']];
        }

        // اختيار متعدد افتراضي: اختيار واحد
        if (!isset($meta['allow_multiple'])) {
            $meta['allow_multiple'] = false;
        }

        $newMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare("UPDATE qbank SET meta = ? WHERE id = ?");
        $stmt->execute([$newMeta, $id]);
        $updated++;
    }

    if ($qType === 'true_false') {
        // نخزن correct_answer إن أمكن
        if (empty($meta['correct_answer']) && !empty($fallback['correct_letter'])) {
            $meta['correct_answer'] = ($fallback['correct_letter'] === 'أ') ? 'صح' : 'خطأ';
            $newMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $stmt = $pdo->prepare("UPDATE qbank SET meta = ? WHERE id = ?");
            $stmt->execute([$newMeta, $id]);
            $updated++;
        }
    }
}

echo "<h2>تم تحديث meta لعدد: {$updated} سجل</h2>";
echo "<p>بعدها احذف هذا الملف للأمان.</p>";
