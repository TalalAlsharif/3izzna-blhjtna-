<?php
session_start();

require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireLogin();

/* تعريفات اللهجات */
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
 * تنظيف نص السؤال إذا كان يحتوي داخل النص:
 * "الخيارات:" أو "الإجابة الصحيحة:"
 */
function stripEmbeddedOptionsFromText($text)
{
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
        header('Location: projects.php?error=project_not_found');
        exit;
    }

    /* تجهيز/تحديث التقدم */
    $progress = fetchOne(
        "SELECT * FROM user_project_progress WHERE user_id = ? AND project_id = ?",
        [$userId, $projectId]
    );

    if (!$progress) {
        $stmt = $pdo->prepare("
            INSERT INTO user_project_progress (user_id, project_id, status, total_questions, started_at)
            VALUES (?, ?, 'in_progress', ?, NOW())
        ");
        $stmt->execute([$userId, $projectId, (int)$project['total_questions']]);

        $progress = fetchOne(
            "SELECT * FROM user_project_progress WHERE user_id = ? AND project_id = ?",
            [$userId, $projectId]
        );
    } elseif (($progress['status'] ?? '') === 'not_started') {
        $pdo->prepare("
            UPDATE user_project_progress
            SET status = 'in_progress', started_at = NOW()
            WHERE id = ?
        ")->execute([(int)$progress['id']]);
    }

    /* جلب الأسئلة + إجابات المستخدم */
    $questions = fetchAll("
        SELECT 
            q.id,
            q.question_text,
            q.question_type,
            q.meta,
            q.dialect_type,
            pq.question_order,
            a.answer_data
        FROM project_questions pq
        JOIN qbank q ON pq.question_id = q.id
        LEFT JOIN answers a 
            ON a.question_id = q.id 
            AND a.user_id = ? 
            AND a.project_id = ?
        WHERE pq.project_id = ?
        ORDER BY pq.question_order ASC
    ", [$userId, $projectId, $projectId]);

    if (empty($questions)) {
        header('Location: projects.php?error=no_questions');
        exit;
    }

    $answeredCount = 0;
    foreach ($questions as $q) {
        if (!empty($q['answer_data'])) {
            $answeredCount++;
        }
    }
    $progressPercentage = ($answeredCount / count($questions)) * 100;

} catch (Throwable $e) {
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
    <title><?php echo htmlspecialchars($project['title']); ?> - حل المشروع</title>

    <!-- خطوط -->
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- (اختياري) CSS خارجي لو موجود عندك -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/app.css?v=1">

    <style>
        :root { --primary-green:#1a5c4b; --dark-green:#0d3d32; --deep-green:#0a2e26; --accent-magenta:#9c2d5a; --cream-bg:#f8f5f0; --cream-light:#fcfaf7; --text-dark:#1a1a1a; --text-gray:#5a5a5a; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Tajawal',sans-serif; background:var(--cream-bg); color:var(--text-dark); line-height:1.7; }

        header { background:var(--cream-light); border-bottom:1px solid rgba(0,0,0,0.05); position:fixed; top:0; left:0; right:0; z-index:1000; }
        header.scrolled { background:#fff; box-shadow:0 4px 20px rgba(0,0,0,0.1); }
        nav { max-width:1300px; margin:0 auto; padding:15px 40px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { display:flex; align-items:center; gap:15px; }
        .nav-logo { width:50px; height:50px; border-radius:10px; overflow:hidden; background:#fff; }
        .nav-logo img { width:100%; height:100%; object-fit:cover; display:block; }
        .nav-title { font-family:'Amiri',serif; font-size:22px; font-weight:700; color:var(--accent-magenta); }

        .nav-links { display:flex; align-items:center; gap:40px; list-style:none; }
        .nav-links a { text-decoration:none; color:var(--text-dark); font-weight:500; font-size:15px; display:flex; align-items:center; gap:8px; position:relative; padding-bottom:5px; }
        .nav-links a svg { width:18px; height:18px; }
        .nav-links a::after { content:''; position:absolute; bottom:0; right:0; width:0; height:2px; background:var(--accent-magenta); transition:width .3s; }
        .nav-links a:hover,.nav-links a.active { color:var(--accent-magenta); }
        .nav-links a:hover::after,.nav-links a.active::after { width:100%; }

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

        .progress-section { display:flex; align-items:center; gap:15px; }
        .progress-text { font-size:14px; white-space:nowrap; }
        .progress-bar { flex:1; height:8px; background:rgba(255,255,255,0.3); border-radius:8px; overflow:hidden; }
        .progress-fill { height:100%; background:linear-gradient(90deg,#fbbf24,#f59e0b); border-radius:8px; transition:width .5s; }

        .message { padding:12px 20px; border-radius:10px; margin-bottom:15px; font-weight:600; text-align:center; display:none; }
        .message.error { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
        .message.ok { background:#dcfce7; color:#166534; border:1px solid #bbf7d0; }

        .q-navigator { background:#fff; border-radius:12px; padding:15px 20px; margin-bottom:20px; box-shadow:0 2px 10px rgba(0,0,0,0.05); display:flex; align-items:center; justify-content:center; gap:10px; }
        .nav-arrow { width:40px; height:40px; border:2px solid #e5e5e5; background:#fff; border-radius:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:.3s; font-size:18px; color:var(--text-gray); }
        .nav-arrow:hover:not(:disabled) { border-color:var(--accent-magenta); color:var(--accent-magenta); }
        .nav-arrow:disabled { opacity:.3; cursor:not-allowed; }
        .q-numbers { display:flex; gap:8px; }
        .q-num { width:44px; height:44px; border:2px solid #e5e5e5; background:#fff; border-radius:10px; font-weight:800; font-size:15px; cursor:pointer; transition:.3s; display:flex; align-items:center; justify-content:center; color:var(--text-gray); }
        .q-num:hover { border-color:var(--accent-magenta); color:var(--accent-magenta); }
        .q-num.active { background:var(--accent-magenta); color:#fff; border-color:var(--accent-magenta); }
        .q-num.answered { background:#dcfce7; border-color:#22c55e; color:#166534; }
        .q-num.answered.active { background:#22c55e; color:#fff; }
        .q-counter { font-size:14px; color:var(--text-gray); font-weight:700; white-space:nowrap; padding:0 10px; }

        .question-card { background:#fff; border-radius:16px; padding:25px; box-shadow:0 4px 15px rgba(0,0,0,0.05); margin-bottom:20px; display:none; }
        .question-card.active { display:block; animation:fadeIn .25s ease; }
        @keyframes fadeIn { from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:translateY(0)} }

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

        .options-container { margin-bottom:20px; }
        .option-hint { color:var(--text-gray); font-size:13px; margin-bottom:12px; font-weight:600; }
        .option-btn { display:block; width:100%; padding:16px 20px; margin-bottom:10px; border:2px solid #e5e5e5; background:#fff; border-radius:10px; text-align:right; cursor:pointer; transition:.2s; font-size:15px; font-weight:700; }
        .option-btn:hover { border-color:var(--accent-magenta); background:#fef7f9; }
        .option-btn.selected { border-color:var(--accent-magenta); background:var(--accent-magenta); color:#fff; }

        .answer-textarea { width:100%; padding:18px; border:2px solid #e5e5e5; border-radius:10px; font-size:15px; font-family:'Tajawal',sans-serif; resize:vertical; min-height:120px; transition:border-color .3s; }
        .answer-textarea:focus { outline:none; border-color:var(--accent-magenta); }

        .nav-buttons { display:flex; justify-content:space-between; align-items:center; padding-top:20px; border-top:1px solid #f0f0f0; gap:15px; }
        .btn-nav { padding:14px 30px; border-radius:10px; font-weight:800; font-size:15px; cursor:pointer; transition:.2s; border:2px solid #e5e5e5; background:#fff; color:var(--text-dark); }
        .btn-nav:hover { border-color:var(--accent-magenta); color:var(--accent-magenta); }
        .btn-nav:disabled { opacity:.35; cursor:not-allowed; }
        .btn-finish { background:linear-gradient(135deg,#22c55e,#16a34a); color:#fff; border:none; padding:14px 35px; }
        .btn-finish:hover { transform:translateY(-1px); box-shadow:0 4px 15px rgba(34,197,94,0.25); }

        .save-indicator { position:fixed; bottom:20px; left:20px; background:#22c55e; color:#fff; padding:10px 20px; border-radius:10px; font-weight:800; font-size:14px; display:none; z-index:1000; }

        footer { background:var(--deep-green); color:#fff; text-align:center; padding:20px; margin-top:40px; }
        footer p { opacity:.85; font-size:14px; }

        @media (max-width:768px){
            nav{flex-wrap:wrap; gap:15px; padding:15px 20px;}
            .nav-links{order:3; width:100%; justify-content:center; gap:20px; padding-top:15px; border-top:1px solid rgba(0,0,0,0.08);}
            .pattern-strip{margin-top:130px;}
            .main-content{padding:20px 15px;}
            .project-top{flex-direction:column; align-items:flex-start;}
            .q-num{width:38px; height:38px; font-size:13px;}
            .nav-arrow{width:36px; height:36px;}
        }
    </style>
</head>
<body>

<header id="header">
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
                <h1><?php echo htmlspecialchars($project['title']); ?></h1>
                <a href="projects.php" class="back-btn">← رجوع</a>
            </div>

            <div class="progress-section">
                <span class="progress-text"><span id="answered-count"><?php echo (int)$answeredCount; ?></span>/<?php echo (int)count($questions); ?></span>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill" style="width: <?php echo (float)$progressPercentage; ?>%"></div>
                </div>
                <span class="progress-text"><span id="progress-percentage"><?php echo (int)round($progressPercentage); ?></span>%</span>
            </div>
        </div>
    </div>

    <div id="save-message" class="message"></div>

    <div class="q-navigator">
        <button class="nav-arrow" id="prevPage" onclick="changePage(-1)">→</button>
        <div class="q-numbers" id="qNumbers"></div>
        <button class="nav-arrow" id="nextPage" onclick="changePage(1)">←</button>
        <span class="q-counter" id="qCounter"></span>
    </div>

    <?php foreach ($questions as $index => $question):
        $meta = json_decode((string)$question['meta'], true);
        if (!is_array($meta)) { $meta = []; }

        $savedAnswer = !empty($question['answer_data']) ? json_decode((string)$question['answer_data'], true) : null;
        if (!is_array($savedAnswer)) { $savedAnswer = null; }

        $typeLabels = [
            'open_ended'      => 'مفتوح',
            'multiple_choice' => 'اختيار',
            'true_false'      => 'صح/خطأ',
            'list_selection'  => 'قائمة'
        ];

        $isFirst = ($index === 0);
        $isLast  = ($index === (count($questions) - 1));

        $dialect = $question['dialect_type'] ?? 'General';
        $dialectLabel = $dialectLabels[$dialect] ?? $dialect;

        $cleanQuestionText = stripEmbeddedOptionsFromText($question['question_text'] ?? '');
    ?>
        <div class="question-card <?php echo $isFirst ? 'active' : ''; ?>"
             id="question-<?php echo (int)$index; ?>"
             data-question-id="<?php echo (int)$question['id']; ?>"
             data-type="<?php echo htmlspecialchars((string)$question['question_type']); ?>"
             data-answered="<?php echo !empty($question['answer_data']) ? '1' : '0'; ?>">

            <div class="question-meta">
                <span class="question-number">السؤال <?php echo (int)($index + 1); ?> من <?php echo (int)count($questions); ?></span>
                <div>
                    <span class="badge-dialect badge-<?php echo htmlspecialchars((string)$dialect); ?>"><?php echo htmlspecialchars((string)$dialectLabel); ?></span>
                    <span class="question-type"><?php echo htmlspecialchars($typeLabels[$question['question_type']] ?? (string)$question['question_type']); ?></span>
                </div>
            </div>

            <div class="question-text">
                <p><?php echo htmlspecialchars($cleanQuestionText); ?></p>
            </div>

            <div class="options-container">
                <?php if ($question['question_type'] === 'open_ended'): ?>
                    <textarea
                        class="answer-textarea"
                        placeholder="اكتب إجابتك هنا..."
                        oninput="scheduleAutoSave(<?php echo (int)$index; ?>)"
                        onblur="queueSave(<?php echo (int)$index; ?>)"><?php echo $savedAnswer ? htmlspecialchars((string)($savedAnswer['text'] ?? '')) : ''; ?></textarea>

                <?php elseif ($question['question_type'] === 'multiple_choice'): ?>
                    <?php
                        $allowMultiple = (bool)($meta['allow_multiple'] ?? false);
                        $savedSelections = $savedAnswer['selected'] ?? [];
                        if (!is_array($savedSelections)) { $savedSelections = []; }
                    ?>
                    <?php if ($allowMultiple): ?><div class="option-hint">يمكنك اختيار أكثر من إجابة</div><?php endif; ?>
                    <?php foreach (($meta['options'] ?? []) as $option): ?>
                        <button type="button"
                                class="option-btn <?php echo in_array($option, $savedSelections, true) ? 'selected' : ''; ?>"
                                data-value="<?php echo htmlspecialchars((string)$option); ?>"
                                onclick="selectAndQueueSave(this, <?php echo (int)$index; ?>, <?php echo $allowMultiple ? 'true' : 'false'; ?>)"><?php echo htmlspecialchars((string)$option); ?></button>
                    <?php endforeach; ?>

                <?php elseif ($question['question_type'] === 'true_false'): ?>
                    <?php $savedValue = $savedAnswer['answer'] ?? null; ?>
                    <button type="button"
                            class="option-btn <?php echo ($savedValue === true) ? 'selected' : ''; ?>"
                            data-value="true"
                            onclick="selectAndQueueSave(this, <?php echo (int)$index; ?>, false)">✓ صح</button>

                    <button type="button"
                            class="option-btn <?php echo ($savedValue === false) ? 'selected' : ''; ?>"
                            data-value="false"
                            onclick="selectAndQueueSave(this, <?php echo (int)$index; ?>, false)">✗ خطأ</button>

                <?php elseif ($question['question_type'] === 'list_selection'): ?>
                    <?php
                        $max = (int)($meta['max_selections'] ?? count($meta['options'] ?? []));
                        $savedSelections = $savedAnswer['selected'] ?? [];
                        if (!is_array($savedSelections)) { $savedSelections = []; }
                    ?>
                    <div class="option-hint">اختر حتى <?php echo (int)$max; ?> خيارات</div>
                    <?php foreach (($meta['options'] ?? []) as $option): ?>
                        <button type="button"
                                class="option-btn <?php echo in_array($option, $savedSelections, true) ? 'selected' : ''; ?>"
                                data-value="<?php echo htmlspecialchars((string)$option); ?>"
                                data-max="<?php echo (int)$max; ?>"
                                onclick="selectListAndQueueSave(this, <?php echo (int)$index; ?>)"><?php echo htmlspecialchars((string)$option); ?></button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="nav-buttons">
                <button class="btn-nav" onclick="goToPrev()" <?php echo $isFirst ? 'disabled' : ''; ?>>→ السابق</button>

                <?php if ($isLast): ?>
                    <button class="btn-nav btn-finish" onclick="finishProject()">إنهاء المشروع</button>
                <?php else: ?>
                    <button class="btn-nav" onclick="goToNext()">التالي ←</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="saveIndicator" class="save-indicator">تم الحفظ</div>
<footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>

<script>
    const projectId = <?php echo (int)$projectId; ?>;
    const totalQuestions = <?php echo (int)count($questions); ?>;
    const visibleCount = 5;

    let currentQuestion = 0;
    let currentPage = 0;

    let saving = false;
    let pendingSaveIndex = null;

    const openEndedTimers = {};

    window.addEventListener('scroll', () => {
        const h = document.getElementById('header');
        if (h) h.classList.toggle('scrolled', window.scrollY > 50);
    });

    document.addEventListener('DOMContentLoaded', () => {
        renderNavigator();
        updateCounter();
    });

    function renderNavigator() {
        const container = document.getElementById('qNumbers');
        container.innerHTML = '';

        const totalPages = Math.ceil(totalQuestions / visibleCount);
        const start = currentPage * visibleCount;
        const end = Math.min(start + visibleCount, totalQuestions);

        for (let i = start; i < end; i++) {
            const card = document.getElementById('question-' + i);
            const isAnswered = card.dataset.answered === '1';
            const isActive = i === currentQuestion;

            const btn = document.createElement('button');
            btn.className = 'q-num' + (isActive ? ' active' : '') + (isAnswered ? ' answered' : '');
            btn.textContent = i + 1;
            btn.onclick = () => showQuestion(i);
            container.appendChild(btn);
        }

        document.getElementById('prevPage').disabled = currentPage === 0;
        document.getElementById('nextPage').disabled = currentPage >= totalPages - 1;
    }

    function updateCounter() {
        document.getElementById('qCounter').textContent = (currentQuestion + 1) + '/' + totalQuestions;
    }

    function changePage(dir) {
        const totalPages = Math.ceil(totalQuestions / visibleCount);
        currentPage = Math.max(0, Math.min(totalPages - 1, currentPage + dir));
        renderNavigator();
    }

    function showQuestion(index) {
        document.querySelectorAll('.question-card').forEach(c => c.classList.remove('active'));
        document.getElementById('question-' + index).classList.add('active');
        currentQuestion = index;

        const newPage = Math.floor(index / visibleCount);
        if (newPage !== currentPage) currentPage = newPage;

        renderNavigator();
        updateCounter();
        window.scrollTo({ top: 120, behavior: 'smooth' });
    }

    function goToNext() {
        if (currentQuestion < totalQuestions - 1) showQuestion(currentQuestion + 1);
    }

    function goToPrev() {
        if (currentQuestion > 0) showQuestion(currentQuestion - 1);
    }

    function showMsg(text, type) {
        const msg = document.getElementById('save-message');
        msg.textContent = text;
        msg.className = 'message ' + (type === 'ok' ? 'ok' : 'error');
        msg.style.display = 'block';
        setTimeout(() => msg.style.display = 'none', 2500);
    }

    function showSaveIndicator() {
        const indicator = document.getElementById('saveIndicator');
        indicator.style.display = 'block';
        setTimeout(() => indicator.style.display = 'none', 1200);
    }

    function updateProgress() {
        const answered = document.querySelectorAll('.question-card[data-answered="1"]').length;
        const pct = (answered / totalQuestions) * 100;
        document.getElementById('answered-count').textContent = answered;
        document.getElementById('progress-percentage').textContent = Math.round(pct);
        document.getElementById('progress-fill').style.width = pct + '%';
    }

    function selectAndQueueSave(btn, questionIndex, allowMultiple) {
        if (!allowMultiple) {
            btn.parentElement.querySelectorAll('.option-btn').forEach(b => b.classList.remove('selected'));
        }
        btn.classList.toggle('selected');
        queueSave(questionIndex);
    }

    function selectListAndQueueSave(btn, questionIndex) {
        const max = parseInt(btn.dataset.max, 10);
        const selected = btn.parentElement.querySelectorAll('.option-btn.selected');
        if (!btn.classList.contains('selected') && selected.length >= max) {
            showMsg('الحد الأقصى ' + max + ' خيارات', 'error');
            return;
        }
        btn.classList.toggle('selected');
        queueSave(questionIndex);
    }

    function scheduleAutoSave(questionIndex) {
        clearTimeout(openEndedTimers[questionIndex]);
        openEndedTimers[questionIndex] = setTimeout(() => queueSave(questionIndex), 600);
    }

    function queueSave(questionIndex) {
        if (saving) {
            pendingSaveIndex = questionIndex;
            return;
        }
        autoSave(questionIndex);
    }

    async function autoSave(questionIndex) {
        const card = document.getElementById('question-' + questionIndex);
        if (!card) return;

        const questionId = parseInt(card.dataset.questionId, 10);
        const type = card.dataset.type;

        let answerData = null;

        if (type === 'open_ended') {
            const text = card.querySelector('.answer-textarea').value.trim();
            if (!text) return;
            answerData = { text: text };
        } else if (type === 'true_false') {
            const sel = card.querySelector('.option-btn.selected');
            if (!sel) return;
            answerData = { answer: (sel.dataset.value === 'true') };
        } else {
            const selected = card.querySelectorAll('.option-btn.selected');
            if (selected.length === 0) return;
            answerData = { selected: Array.from(selected).map(b => b.dataset.value) };
        }

        saving = true;

        try {
            const res = await fetch('<?php echo API_URL; ?>/user/answers/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    project_id: projectId,
                    question_id: questionId,
                    answer_data: answerData
                })
            });

            let data = null;
            try { data = await res.json(); } catch (e) { data = null; }

            if (res.status === 401) {
                showMsg('انتهت الجلسة. سجل دخولك مرة ثانية.', 'error');
            } else if (!res.ok) {
                showMsg('خطأ في الحفظ (HTTP ' + res.status + ')', 'error');
            } else if (data && data.success) {
                card.dataset.answered = '1';
                updateProgress();
                renderNavigator();
                showSaveIndicator();
            } else {
                showMsg((data && data.message) ? data.message : 'فشل الحفظ', 'error');
            }

        } catch (e) {
            showMsg('خطأ اتصال أثناء الحفظ', 'error');
        }

        saving = false;

        if (pendingSaveIndex !== null && pendingSaveIndex !== questionIndex) {
            const idx = pendingSaveIndex;
            pendingSaveIndex = null;
            autoSave(idx);
        } else {
            pendingSaveIndex = null;
        }
    }

    async function finishProject() {
        const answered = document.querySelectorAll('.question-card[data-answered="1"]').length;

        if (answered < totalQuestions) {
            if (!confirm('لم تجب على كل الأسئلة (' + answered + '/' + totalQuestions + '). إنهاء؟')) return;
        }
        if (!confirm('هل أنت متأكد من إنهاء المشروع؟')) return;

        try {
            const res = await fetch('<?php echo API_URL; ?>/user/answers/submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ project_id: projectId })
            });

            const data = await res.json();

            if (data && data.success) {
                alert('تم إنهاء المشروع بنجاح.');
                window.location.href = 'projects.php';
            } else {
                alert((data && data.message) ? data.message : 'تعذر إنهاء المشروع');
            }
        } catch (e) {
            alert('خطأ في الاتصال');
        }
    }
</script>

</body>
</html>
