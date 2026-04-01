<?php
session_start();

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireLogin();

$dialectLabels = [
    'Central'  => 'الوسطى',
    'Western'  => 'الغربية',
    'Eastern'  => 'الشرقية',
    'Southern' => 'الجنوبية',
    'Northern' => 'الشمالية',
    'General'  => 'عام',
];

if (!isset($_GET['project_id'])) {
    header('Location: projects.php');
    exit;
}

$projectId = (int)$_GET['project_id'];
$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = (string)($_SESSION['user_name'] ?? '');

/**
 * تنظيف نص السؤال
 */
function stripEmbeddedOptionsFromText($text) {
    $text = (string)$text;
    $patterns = [
        '/\R+\s*الخيارات\s*:.*$/u',
        '/\R+\s*الإجابة\s*الصحيحة\s*:.*$/u',
        '/\R+\s*الاجابة\s*الصحيحة\s*:.*$/u',
    ];
    foreach ($patterns as $p) {
        $text = preg_replace($p, '', $text);
    }
    return trim($text);
}

try {
    $project = fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    
    if (!$project) {
        header('Location: projects.php?error=not_found');
        exit;
    }
    
    $progress = fetchOne("
        SELECT * FROM user_project_progress 
        WHERE user_id = ? AND project_id = ?
    ", [$userId, $projectId]);
    
    if (!$progress || $progress['status'] !== 'completed') {
        header('Location: solve.php?project_id=' . $projectId);
        exit;
    }
    
    $questions = fetchAll("
        SELECT q.*, pq.question_order, a.answer_data, a.is_correct
        FROM project_questions pq
        JOIN qbank q ON pq.question_id = q.id
        LEFT JOIN answers a ON a.question_id = q.id AND a.user_id = ? AND a.project_id = ?
        WHERE pq.project_id = ?
        ORDER BY pq.question_order ASC
    ", [$userId, $projectId, $projectId]);
    
    $totalQuestions = count($questions);
    $answeredQuestions = 0;
    $correctAnswers = 0;
    
    foreach ($questions as $q) {
        if (!empty($q['answer_data'])) {
            $answeredQuestions++;
            if ($q['is_correct'] === 1 || $q['is_correct'] === '1') {
                $correctAnswers++;
            }
        }
    }
    
    $score = ($totalQuestions > 0) ? (($correctAnswers / $totalQuestions) * 100) : 0;
    
} catch (Exception $e) {
    error_log("Review Error: " . $e->getMessage());
    header('Location: projects.php?error=system_error');
    exit;
}

$currentPage = 'projects';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مراجعة: <?php echo htmlspecialchars($project['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/app.css?v=1">
    <style>
        :root { --primary-green:#1a5c4b; --dark-green:#0d3d32; --deep-green:#0a2e26; --accent-magenta:#9c2d5a; --cream-bg:#f8f5f0; --cream-light:#fcfaf7; --text-dark:#1a1a1a; --text-gray:#5a5a5a; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Tajawal',sans-serif; background:var(--cream-bg); color:var(--text-dark); line-height:1.7; }

        header { background:var(--cream-light); border-bottom:1px solid rgba(0,0,0,0.05); position:fixed; top:0; left:0; right:0; z-index:1000; }
        nav { max-width:1300px; margin:0 auto; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:15px; }
        .nav-logo { width:50px; height:50px; border-radius:10px; overflow:hidden; background:#fff; }
        .nav-logo img { width:100%; height:100%; object-fit:cover; }
        .nav-title { font-family:'Amiri',serif; font-size:22px; font-weight:700; color:var(--accent-magenta); }
        .nav-links { display:flex; align-items:center; gap:40px; list-style:none; }
        .nav-links a { text-decoration:none; color:var(--text-dark); font-weight:500; font-size:15px; display:flex; align-items:center; gap:8px; position:relative; padding-bottom:5px; }
        .nav-links a svg { width:18px; height:18px; }
        .nav-links a::after { content:''; position:absolute; bottom:0; right:0; width:0; height:2px; background:var(--accent-magenta); transition:width .3s; }
        .nav-links a:hover, .nav-links a.active { color:var(--accent-magenta); }
        .nav-links a:hover::after, .nav-links a.active::after { width:100%; }
        .nav-user { display:flex; align-items:center; gap:20px; }
        .user-name { font-weight:600; }
        .btn-logout { background:transparent; border:2px solid var(--accent-magenta); color:var(--accent-magenta); padding:8px 20px; border-radius:8px; font-weight:600; font-size:14px; text-decoration:none; transition:.3s; }
        .btn-logout:hover { background:var(--accent-magenta); color:#fff; }

        .pattern-strip { height:45px; background-color:var(--deep-green); background-image:url('<?php echo ASSETS_URL; ?>/images/pattern.png'); background-repeat:repeat-x; background-size:auto 100%; margin-top:80px; }
        .main-content { max-width:900px; margin:0 auto; padding:30px 20px; }

        .project-header { background:linear-gradient(135deg,var(--dark-green),var(--deep-green)); border-radius:16px; padding:25px; color:#fff; margin-bottom:25px; position:relative; overflow:hidden; }
        .project-header::before { content:''; position:absolute; inset:0; background-image:url('<?php echo ASSETS_URL; ?>/images/pattern.png'); opacity:.06; }
        .project-header-content { position:relative; z-index:1; }
        .project-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; gap:12px; }
        .project-header h1 { font-family:'Amiri',serif; font-size:24px; }
        .back-btn { background:rgba(255,255,255,0.2); color:#fff; padding:8px 16px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; transition:.3s; }
        .back-btn:hover { background:rgba(255,255,255,0.3); }

        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; margin-top:20px; }
        .stat-card { background:rgba(255,255,255,0.15); border-radius:12px; padding:15px; text-align:center; }
        .stat-number { font-size:32px; font-weight:800; margin-bottom:5px; }
        .stat-label { font-size:13px; opacity:0.9; }

        .question-card { background:#fff; border-radius:16px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.05); margin-bottom:20px; }
        .question-meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
        .question-number { font-weight:800; font-size:15px; color:var(--dark-green); }
        .question-type { background:var(--cream-bg); padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; color:var(--text-gray); }

        .badge-dialect { padding:5px 12px; border-radius:20px; font-size:11px; font-weight:800; color:#fff; margin-right:8px; }
        .badge-Central { background:#8b5cf6; }
        .badge-Western { background:#ec4899; }
        .badge-Eastern { background:#06b6d4; }
        .badge-Southern { background:#10b981; }
        .badge-Northern { background:#f59e0b; }
        .badge-General { background:#6b7280; }

        .question-text { background:linear-gradient(135deg,#f8f5f0,#fcfaf7); padding:20px; border-radius:12px; margin-bottom:20px; border-right:4px solid var(--accent-magenta); }
        .question-text p { font-size:17px; line-height:1.9; }

        .answer-section { margin-bottom:20px; }
        .section-title { font-size:14px; font-weight:700; color:var(--text-gray); margin-bottom:10px; }
        .user-answer { background:#f0f9ff; border:2px solid #3b82f6; padding:15px; border-radius:10px; margin-bottom:10px; }
        .correct-answer { background:#dcfce7; border:2px solid #22c55e; padding:15px; border-radius:10px; }
        .wrong-answer { background:#fee2e2; border:2px solid#ef4444; padding:15px; border-radius:10px; margin-bottom:10px; }

        .result-badge { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:20px; font-weight:700; font-size:13px; }
        .result-correct { background:#dcfce7; color:#166534; }
        .result-wrong { background:#fee2e2; color:#dc2626; }
        .result-na { background:#f3f4f6; color:#6b7280; }

        footer { background:var(--deep-green); color:#fff; text-align:center; padding:20px; margin-top:40px; }
        footer p { opacity:.85; font-size:14px; }
    </style>
</head>
<body>

<header>
    <nav>
        <a href="dashboard.php" class="nav-brand" style="text-decoration:none;">
            <div class="nav-logo">
                <img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا" onerror="this.style.display='none';">
            </div>
            <span class="nav-title">عِزّنا بلهجتنا</span>
        </a>

        <ul class="nav-links">
            <li><a href="dashboard.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>الرئيسية</a></li>

            <li><a href="projects.php" class="active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>المشاريع</a></li>

            <li><a href="statistics.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>إحصائياتي</a></li>
        </ul>

        <div class="nav-user">
            <span class="user-name">مرحباً، <?php echo htmlspecialchars($userName); ?></span>
            <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
        </div>
    </nav>
</header>

<div class="pattern-strip"></div>

<div class="main-content">
    <div class="project-header">
        <div class="project-header-content">
            <div class="project-top">
                <h1>مراجعة: <?php echo htmlspecialchars($project['title']); ?></h1>
                <a href="projects.php" class="back-btn">← رجوع</a>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalQuestions; ?></div>
                    <div class="stat-label">إجمالي الأسئلة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $correctAnswers; ?></div>
                    <div class="stat-label">إجابات صحيحة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($score, 1); ?>%</div>
                    <div class="stat-label">النتيجة النهائية</div>
                </div>
            </div>
        </div>
    </div>

    <h2 style="font-family:'Amiri',serif; font-size:24px; margin-bottom:20px; color:var(--dark-green);">تفاصيل الإجابات</h2>

    <?php foreach ($questions as $index => $question):
        $meta = json_decode((string)$question['meta'], true);
        if (!is_array($meta)) { $meta = []; }

        $userAnswer = !empty($question['answer_data']) ? json_decode((string)$question['answer_data'], true) : null;
        if (!is_array($userAnswer)) { $userAnswer = null; }

        $typeLabels = [
            'open_ended'      => 'مفتوح',
            'multiple_choice' => 'اختيار',
            'true_false'      => 'صح/خطأ',
            'list_selection'  => 'قائمة'
        ];

        $dialect = $question['dialect_type'] ?? 'General';
        $dialectLabel = $dialectLabels[$dialect] ?? $dialect;
        $cleanQuestionText = stripEmbeddedOptionsFromText($question['question_text'] ?? '');
        
        $isCorrect = ($question['is_correct'] === 1 || $question['is_correct'] === '1');
        $hasCorrectAnswer = isset($meta['correct_answer']) || isset($meta['correct_answers']);
    ?>
        <div class="question-card">
            <div class="question-meta">
                <span class="question-number">السؤال <?php echo ($index + 1); ?></span>
                <div>
                    <span class="badge-dialect badge-<?php echo htmlspecialchars($dialect); ?>"><?php echo htmlspecialchars($dialectLabel); ?></span>
                    <span class="question-type"><?php echo htmlspecialchars($typeLabels[$question['question_type']] ?? $question['question_type']); ?></span>
                    
                    <?php if ($userAnswer): ?>
                        <?php if ($hasCorrectAnswer): ?>
                            <span class="result-badge <?php echo $isCorrect ? 'result-correct' : 'result-wrong'; ?>">
                                <?php echo $isCorrect ? '✓ صح' : '✗ خطأ'; ?>
                            </span>
                        <?php else: ?>
                            <span class="result-badge result-na">لا يوجد تصحيح</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="result-badge result-na">لم تجب</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="question-text">
                <p><?php echo htmlspecialchars($cleanQuestionText); ?></p>
            </div>

            <div class="answer-section">
                <?php if ($userAnswer): ?>
                    <div class="section-title">إجابتك:</div>
                    <div class="<?php echo ($hasCorrectAnswer && !$isCorrect) ? 'wrong-answer' : 'user-answer'; ?>">
                        <?php
                        if ($question['question_type'] === 'open_ended') {
                            echo '<p>' . htmlspecialchars($userAnswer['text'] ?? '') . '</p>';
                        } elseif ($question['question_type'] === 'true_false') {
                            echo '<p>' . ($userAnswer['answer'] ? 'صح ✓' : 'خطأ ✗') . '</p>';
                        } else {
                            foreach ($userAnswer['selected'] ?? [] as $sel) {
                                echo '<p>• ' . htmlspecialchars($sel) . '</p>';
                            }
                        }
                        ?>
                    </div>
                <?php else: ?>
                    <div class="section-title">إجابتك:</div>
                    <div style="padding:15px; background:#f3f4f6; border-radius:10px; color:#6b7280; font-style:italic;">
                        لم تجب على هذا السؤال
                    </div>
                <?php endif; ?>

                <?php if ($hasCorrectAnswer && $question['question_type'] !== 'open_ended'): ?>
                    <div class="section-title" style="margin-top:15px;">الإجابة الصحيحة:</div>
                    <div class="correct-answer">
                        <?php
                        if ($question['question_type'] === 'true_false') {
                            echo '<p>' . ($meta['correct_answer'] ? 'صح ✓' : 'خطأ ✗') . '</p>';
                        } else {
                            foreach ($meta['correct_answers'] ?? [] as $correct) {
                                echo '<p>• ' . htmlspecialchars($correct) . '</p>';
                            }
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<footer>
    <p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p>
</footer>

</body>
</html>