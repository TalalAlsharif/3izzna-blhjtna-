<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireLogin();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// تهيئة المتغيرات بقيم افتراضية
$completed = ['total' => 0];
$inProgress = ['total' => 0];
$notStarted = ['total' => 0];
$avgScore = ['avg' => 0];
$totalAnswers = ['total' => 0];
$correctAnswers = ['total' => 0];
$wrongAnswers = ['total' => 0];
$bestScore = ['best' => 0];
$history = [];
$projectScores = [];
$answersByType = [];
$projectsInProgress = [];
$availableProjects = [];
$recentActivity = [];
$userRank = ['rank' => 1];
$totalActiveUsers = ['total' => 1];

try {
    // إحصائيات المشاريع
    $result = fetchOne("SELECT COUNT(*) as total FROM user_project_progress WHERE user_id = ? AND status = 'completed'", [$userId]);
    if ($result) $completed = $result;
    
    $result = fetchOne("SELECT COUNT(*) as total FROM user_project_progress WHERE user_id = ? AND status = 'in_progress'", [$userId]);
    if ($result) $inProgress = $result;
    
    $result = fetchOne("SELECT COUNT(*) as total FROM user_project_progress WHERE user_id = ? AND status = 'not_started'", [$userId]);
    if ($result) $notStarted = $result;
    
    $result = fetchOne("SELECT AVG(score) as avg FROM user_project_progress WHERE user_id = ? AND status = 'completed'", [$userId]);
    if ($result && $result['avg'] !== null) $avgScore = $result;
    
    // إحصائيات الإجابات
    $result = fetchOne("SELECT COUNT(*) as total FROM answers WHERE user_id = ?", [$userId]);
    if ($result) $totalAnswers = $result;
    
    $result = fetchOne("SELECT COUNT(*) as total FROM answers WHERE user_id = ? AND is_correct = 1", [$userId]);
    if ($result) $correctAnswers = $result;
    
    $result = fetchOne("SELECT COUNT(*) as total FROM answers WHERE user_id = ? AND is_correct = 0", [$userId]);
    if ($result) $wrongAnswers = $result;
    
    $result = fetchOne("SELECT MAX(score) as best FROM user_project_progress WHERE user_id = ? AND status = 'completed'", [$userId]);
    if ($result && $result['best'] !== null) $bestScore = $result;
    
    // سجل المشاريع المكتملة
    $result = fetchAll("
        SELECT p.title, p.total_questions, upp.score, upp.completed_at 
        FROM user_project_progress upp 
        JOIN projects p ON p.id = upp.project_id 
        WHERE upp.user_id = ? AND upp.status = 'completed' 
        ORDER BY upp.completed_at DESC 
        LIMIT 10
    ", [$userId]);
    if ($result) $history = $result;
    
    // نتائج المشاريع للرسم البياني
    $result = fetchAll("
        SELECT p.title, upp.score 
        FROM user_project_progress upp 
        JOIN projects p ON p.id = upp.project_id 
        WHERE upp.user_id = ? AND upp.status = 'completed' 
        ORDER BY upp.completed_at ASC 
        LIMIT 10
    ", [$userId]);
    if ($result) $projectScores = $result;
    
    // إحصائيات حسب نوع السؤال
    $result = fetchAll("
        SELECT q.question_type, 
               COUNT(*) as total, 
               SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) as correct 
        FROM answers a 
        JOIN qbank q ON q.id = a.question_id 
        WHERE a.user_id = ? 
        GROUP BY q.question_type
    ", [$userId]);
    if ($result) $answersByType = $result;
    
    // المشاريع قيد التقدم
    $result = fetchAll("
        SELECT p.id, p.title, p.total_questions,
               (SELECT COUNT(*) FROM answers WHERE user_id = ? AND project_id = p.id) as answered
        FROM user_project_progress upp 
        JOIN projects p ON p.id = upp.project_id 
        WHERE upp.user_id = ? AND upp.status = 'in_progress' 
        ORDER BY upp.last_activity DESC 
        LIMIT 5
    ", [$userId, $userId]);
    if ($result) $projectsInProgress = $result;
    
    // المشاريع المتاحة (التي لم يبدأها المستخدم بعد)
    $result = fetchAll("
        SELECT p.id, p.title, p.description, p.total_questions,
               (SELECT COUNT(DISTINCT user_id) FROM user_project_progress WHERE project_id = p.id) as participants
        FROM projects p 
        WHERE p.is_active = 1 AND p.is_public = 1
        AND p.id NOT IN (SELECT project_id FROM user_project_progress WHERE user_id = ?)
        ORDER BY p.created_at DESC 
        LIMIT 6
    ", [$userId]);
    if ($result) $availableProjects = $result;
    
    // نشاط آخر 7 أيام
    $result = fetchAll("
        SELECT DATE(answered_at) as date, COUNT(*) as count 
        FROM answers 
        WHERE user_id = ? AND answered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        GROUP BY DATE(answered_at) 
        ORDER BY date ASC
    ", [$userId]);
    if ($result) $recentActivity = $result;
    
    // ترتيب المستخدم - حساب فعلي
    $result = fetchOne("SELECT COUNT(DISTINCT user_id) as total FROM user_project_progress WHERE status = 'completed'");
    if ($result) $totalActiveUsers = $result;
    
    // حساب الترتيب الفعلي بناءً على عدد المشاريع المكتملة ومتوسط النتيجة
    $myCompleted = $completed['total'] ?? 0;
    $myAvg = $avgScore['avg'] ?? 0;
    
    if ($myCompleted > 0) {
        // حساب الترتيب بناءً على مجموع: (عدد المشاريع المكتملة × 10) + متوسط النتيجة
        $myScore = ($myCompleted * 10) + $myAvg;
        
        $result = fetchOne("
            SELECT COUNT(*) as better_count FROM (
                SELECT user_id, 
                       (COUNT(*) * 10) + COALESCE(AVG(score), 0) as total_score
                FROM user_project_progress 
                WHERE status = 'completed'
                GROUP BY user_id
                HAVING total_score > ?
            ) as rankings
        ", [$myScore]);
        
        $userRank['rank'] = ($result ? (int)$result['better_count'] : 0) + 1;
    } else {
        // إذا لم يكمل أي مشروع، ترتيبه هو آخر المستخدمين النشطين
        $userRank['rank'] = max(1, ($totalActiveUsers['total'] ?? 0) + 1);
    }
    
    // إحصائيات حسب اللهجة
    $answersByDialect = fetchAll("
        SELECT q.dialect_type, 
               COUNT(*) as total, 
               SUM(CASE WHEN a.is_correct = 1 THEN 1 ELSE 0 END) as correct 
        FROM answers a 
        JOIN qbank q ON q.id = a.question_id 
        WHERE a.user_id = ? 
        GROUP BY q.dialect_type
    ", [$userId]);
    if (!$answersByDialect) $answersByDialect = [];
    
} catch (Exception $e) {
    error_log("Statistics Error: " . $e->getMessage());
}

// تعريفات اللهجات
$dialectLabels = [
    'Central'  => 'الوسطى',
    'Western'  => 'الغربية',
    'Eastern'  => 'الشرقية',
    'Southern' => 'الجنوبية',
    'Northern' => 'الشمالية',
    'General'  => 'عام',
];

$dialectColors = [
    'Central'  => '#8b5cf6',
    'Western'  => '#ec4899',
    'Eastern'  => '#06b6d4',
    'Southern' => '#10b981',
    'Northern' => '#f59e0b',
    'General'  => '#6b7280',
];

// تحضير بيانات الرسوم البيانية
$chartLabels = [];
$chartScores = [];
foreach ($projectScores as $ps) {
    $chartLabels[] = mb_substr($ps['title'], 0, 12);
    $chartScores[] = round($ps['score'] ?? 0, 1);
}

$typeLabels = [];
$typeCorrect = [];
$typeTotal = [];
$typeNames = [
    'multiple_choice' => 'اختيار متعدد',
    'true_false' => 'صح/خطأ',
    'open_ended' => 'مفتوح',
    'list_selection' => 'قائمة'
];
foreach ($answersByType as $at) {
    $typeLabels[] = $typeNames[$at['question_type']] ?? $at['question_type'];
    $typeCorrect[] = (int)($at['correct'] ?? 0);
    $typeTotal[] = (int)($at['total'] ?? 0);
}

$activityLabels = [];
$activityCounts = [];
$arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $activityLabels[] = $arabicDays[(int)date('w', strtotime($date))];
    $found = false;
    foreach ($recentActivity as $ra) {
        if ($ra['date'] === $date) {
            $activityCounts[] = (int)$ra['count'];
            $found = true;
            break;
        }
    }
    if (!$found) $activityCounts[] = 0;
}

$currentPage = 'statistics';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إحصائياتي - عزنا بلهجتنا</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary-green: #1a5c4b; --dark-green: #0d3d32; --deep-green: #0a2e26; --accent-magenta: #9c2d5a; --light-magenta: #b33d6a; --cream-bg: #f8f5f0; --cream-light: #fcfaf7; --text-dark: #1a1a1a; --text-gray: #5a5a5a; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tajawal', sans-serif; background: var(--cream-bg); color: var(--text-dark); line-height: 1.7; direction: rtl; }
        header { background: var(--cream-light); border-bottom: 1px solid rgba(0,0,0,0.05); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; transition: all 0.3s ease; }
        header.scrolled { background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        nav { max-width: 1300px; margin: 0 auto; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { display: flex; align-items: center; gap: 15px; }
        .nav-logo { width: 50px; height: 50px; border-radius: 10px; overflow: hidden; }
        .nav-logo img { width: 100%; height: 100%; object-fit: cover; }
        .nav-title { font-family: 'Amiri', serif; font-size: 22px; font-weight: 700; color: var(--accent-magenta); }
        .nav-links { display: flex; align-items: center; gap: 40px; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--text-dark); font-weight: 500; font-size: 15px; transition: color 0.3s; position: relative; padding-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .nav-links a svg { width: 18px; height: 18px; }
        .nav-links a::after { content: ''; position: absolute; bottom: 0; right: 0; width: 0; height: 2px; background: var(--accent-magenta); transition: width 0.3s ease; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-magenta); }
        .nav-links a:hover::after, .nav-links a.active::after { width: 100%; }
        .nav-user { display: flex; align-items: center; gap: 20px; }
        .user-name { font-weight: 600; color: var(--text-dark); }
        .btn-logout { background: transparent; border: 2px solid var(--accent-magenta); color: var(--accent-magenta); padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.3s; }
        .btn-logout:hover { background: var(--accent-magenta); color: white; }
        .pattern-strip { height: 45px; background-color: var(--deep-green); background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png'); background-repeat: repeat-x; background-size: auto 100%; margin-top: 80px; }
        .main-content { max-width: 1300px; margin: 0 auto; padding: 40px; }
        .welcome-banner { background: linear-gradient(135deg, var(--dark-green) 0%, var(--deep-green) 100%); border-radius: 20px; padding: 30px 40px; color: white; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden; }
        .welcome-banner::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png'); background-repeat: repeat; opacity: 0.05; }
        .welcome-info { position: relative; z-index: 1; }
        .welcome-info h1 { font-family: 'Amiri', serif; font-size: 28px; margin-bottom: 5px; }
        .welcome-info p { opacity: 0.9; font-size: 15px; }
        .rank-badge { position: relative; z-index: 1; background: rgba(255,255,255,0.15); border-radius: 16px; padding: 20px 30px; text-align: center; backdrop-filter: blur(10px); }
        .rank-badge .rank-value { font-size: 36px; font-weight: 800; color: #fbbf24; }
        .rank-badge .rank-label { font-size: 13px; opacity: 0.9; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); position: relative; overflow: hidden; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card::before { content: ''; position: absolute; top: 0; right: 0; width: 4px; height: 100%; background: var(--accent-magenta); }
        .stat-card.green::before { background: var(--primary-green); }
        .stat-card.gold::before { background: #f59e0b; }
        .stat-icon { width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary-green), var(--dark-green)); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .stat-icon svg { width: 24px; height: 24px; stroke: white; }
        .stat-value { font-size: 28px; font-weight: 800; color: var(--accent-magenta); margin-bottom: 5px; }
        .stat-card.green .stat-value { color: var(--primary-green); }
        .stat-card.gold .stat-value { color: #f59e0b; }
        .stat-label { font-size: 13px; color: var(--text-gray); font-weight: 500; }
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 30px; }
        .chart-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .chart-title { font-family: 'Amiri', serif; font-size: 18px; color: var(--dark-green); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .chart-title svg { width: 22px; height: 22px; stroke: var(--accent-magenta); }
        .chart-container { position: relative; height: 280px; }
        .section-title { font-family: 'Amiri', serif; font-size: 24px; color: var(--dark-green); margin: 30px 0 20px; }
        .project-cards-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .project-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .project-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .project-card-header h4 { font-size: 16px; font-weight: 700; color: var(--dark-green); margin: 0; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-badge.in-progress { background: #fef3c7; color: #92400e; }
        .project-card-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
        .mini-stat { text-align: center; padding: 10px; background: var(--cream-bg); border-radius: 10px; }
        .mini-stat-value { font-size: 18px; font-weight: 800; color: var(--accent-magenta); }
        .mini-stat-label { font-size: 10px; color: var(--text-gray); margin-top: 3px; }
        .progress-bar { height: 8px; background: #e5e5e5; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary-green), var(--accent-magenta)); border-radius: 10px; }
        .tables-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; }
        .table-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .table-title { font-family: 'Amiri', serif; font-size: 18px; color: var(--dark-green); margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px; }
        th { background: var(--cream-bg); font-weight: 600; color: var(--dark-green); }
        tr:hover { background: var(--cream-light); }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #dc2626; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        
        /* Dialect Stats */
        .dialect-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .dialect-stat-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-right: 4px solid; }
        .dialect-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .dialect-badge { padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; }
        .dialect-percentage { font-size: 24px; font-weight: 800; }
        .dialect-stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 15px; }
        .dialect-stat-item { text-align: center; padding: 10px; background: var(--cream-bg); border-radius: 8px; }
        .dialect-stat-value { font-size: 20px; font-weight: 700; display: block; }
        .dialect-stat-label { font-size: 11px; color: var(--text-gray); }
        .dialect-progress { height: 6px; background: #e5e5e5; border-radius: 6px; overflow: hidden; }
        .dialect-progress-fill { height: 100%; border-radius: 6px; transition: width 0.5s; }
        
        .empty-state { text-align: center; padding: 40px; color: var(--text-gray); }
        .empty-chart { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-gray); }
        .empty-chart svg { width: 60px; height: 60px; stroke: #ddd; margin-bottom: 15px; }
        .btn { display: inline-block; padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.3s; }
        .btn-primary { background: var(--accent-magenta); color: white; }
        .btn-outline { background: transparent; border: 2px solid var(--primary-green); color: var(--primary-green); }
        .btn-outline:hover { background: var(--primary-green); color: white; }
        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
        footer p { opacity: 0.8; font-size: 14px; }
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } .project-cards-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .charts-grid, .tables-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { nav { flex-wrap: wrap; gap: 15px; padding: 15px 20px; } .nav-links { order: 3; width: 100%; justify-content: center; gap: 20px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); flex-wrap: wrap; } .pattern-strip { margin-top: 140px; } .main-content { padding: 20px; } .stats-grid { grid-template-columns: 1fr 1fr; } .project-cards-grid { grid-template-columns: 1fr; } .welcome-banner { flex-direction: column; gap: 20px; text-align: center; } }
    </style>
</head>
<body>
    <header id="header">
        <nav>
            <a href="dashboard.php" class="nav-brand" style="text-decoration: none;">
                <div class="nav-logo"><img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا"></div>
                <span class="nav-title">عِزّنا بلهجتنا</span>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>الرئيسية</a></li>
                <li><a href="projects.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>المشاريع</a></li>
                <li><a href="statistics.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>إحصائياتي</a></li>
            </ul>
            <div class="nav-user">
                <span class="user-name">مرحباً، <?php echo htmlspecialchars($userName); ?></span>
                <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
            </div>
        </nav>
    </header>

    <div class="pattern-strip"></div>

    <div class="main-content">
        <div class="welcome-banner">
            <div class="welcome-info">
                <h1>مرحباً، <?php echo htmlspecialchars($userName); ?>!</h1>
                <p>إليك نظرة شاملة على أدائك وإنجازاتك في المنصة</p>
            </div>
            <div class="rank-badge">
                <div class="rank-value">#<?php echo $userRank['rank']; ?></div>
                <div class="rank-label">ترتيبك من <?php echo $totalActiveUsers['total']; ?> مستخدم</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card green">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div class="stat-value"><?php echo $completed['total']; ?></div>
                <div class="stat-label">مشاريع مكتملة</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                <div class="stat-value" style="color: #f59e0b;"><?php echo $inProgress['total']; ?></div>
                <div class="stat-label">قيد التقدم</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                <div class="stat-value"><?php echo number_format($avgScore['avg'] ?? 0, 1); ?>%</div>
                <div class="stat-label">متوسط النتيجة</div>
            </div>
            <div class="stat-card gold">
                <div class="stat-icon" style="background: linear-gradient(135deg, #fbbf24, #f59e0b);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></div>
                <div class="stat-value"><?php echo number_format($bestScore['best'] ?? 0, 1); ?>%</div>
                <div class="stat-label">أعلى نتيجة</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
                <div class="stat-value"><?php echo $totalAnswers['total']; ?></div>
                <div class="stat-label">إجمالي الإجابات</div>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>تطور نتائجي</h3>
                <div class="chart-container">
                    <?php if (empty($chartScores)): ?>
                        <div class="empty-chart">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                            <p>لا توجد بيانات بعد</p>
                            <small>أكمل بعض المشاريع لرؤية تطور نتائجك</small>
                        </div>
                    <?php else: ?>
                        <canvas id="scoresLineChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>توزيع حالة المشاريع</h3>
                <div class="chart-container">
                    <?php if ($completed['total'] == 0 && $inProgress['total'] == 0 && $notStarted['total'] == 0): ?>
                        <div class="empty-chart">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/></svg>
                            <p>لا توجد مشاريع بعد</p>
                            <small>ابدأ مشروعاً جديداً من صفحة المشاريع</small>
                        </div>
                    <?php else: ?>
                        <canvas id="statusChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>أدائي حسب نوع السؤال</h3>
                <div class="chart-container">
                    <?php if (empty($typeLabels)): ?>
                        <div class="empty-chart">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/></svg>
                            <p>لا توجد إجابات بعد</p>
                            <small>أجب على بعض الأسئلة لرؤية أدائك</small>
                        </div>
                    <?php else: ?>
                        <canvas id="typePerformanceChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>نشاطي في آخر 7 أيام</h3>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>

        <!-- إحصائيات اللهجات -->
        <?php if (!empty($answersByDialect)): ?>
        <h2 class="section-title">أدائي حسب اللهجة</h2>
        <div class="dialect-stats-grid">
            <?php foreach ($answersByDialect as $dialStat): 
                $dialect = $dialStat['dialect_type'] ?? 'General';
                $dialectLabel = $dialectLabels[$dialect] ?? $dialect;
                $dialectColor = $dialectColors[$dialect] ?? '#6b7280';
                $total = (int)$dialStat['total'];
                $correct = (int)$dialStat['correct'];
                $percentage = $total > 0 ? round(($correct / $total) * 100) : 0;
            ?>
            <div class="dialect-stat-card" style="border-right-color: <?php echo $dialectColor; ?>;">
                <div class="dialect-header">
                    <span class="dialect-badge" style="background: <?php echo $dialectColor; ?>;"><?php echo $dialectLabel; ?></span>
                    <span class="dialect-percentage" style="color: <?php echo $dialectColor; ?>;"><?php echo $percentage; ?>%</span>
                </div>
                <div class="dialect-stats-row">
                    <div class="dialect-stat-item">
                        <span class="dialect-stat-value"><?php echo $total; ?></span>
                        <span class="dialect-stat-label">إجمالي</span>
                    </div>
                    <div class="dialect-stat-item">
                        <span class="dialect-stat-value" style="color: #22c55e;"><?php echo $correct; ?></span>
                        <span class="dialect-stat-label">صحيح</span>
                    </div>
                    <div class="dialect-stat-item">
                        <span class="dialect-stat-value" style="color: #ef4444;"><?php echo $total - $correct; ?></span>
                        <span class="dialect-stat-label">خاطئ</span>
                    </div>
                </div>
                <div class="dialect-progress">
                    <div class="dialect-progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $dialectColor; ?>;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($projectsInProgress)): ?>
        <h2 class="section-title">المشاريع قيد التقدم</h2>
        <div class="project-cards-grid">
            <?php foreach ($projectsInProgress as $p): $progressPercent = $p['total_questions'] > 0 ? round(($p['answered'] / $p['total_questions']) * 100) : 0; ?>
            <div class="project-card">
                <div class="project-card-header">
                    <h4><?php echo htmlspecialchars($p['title']); ?></h4>
                    <span class="status-badge in-progress">قيد التقدم</span>
                </div>
                <div class="project-card-stats">
                    <div class="mini-stat"><div class="mini-stat-value"><?php echo $p['total_questions']; ?></div><div class="mini-stat-label">إجمالي الأسئلة</div></div>
                    <div class="mini-stat"><div class="mini-stat-value" style="color: var(--primary-green);"><?php echo $p['answered']; ?></div><div class="mini-stat-label">تمت الإجابة</div></div>
                    <div class="mini-stat"><div class="mini-stat-value" style="color: #f59e0b;"><?php echo $progressPercent; ?>%</div><div class="mini-stat-label">الإنجاز</div></div>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $progressPercent; ?>%;"></div></div>
                <div style="margin-top: 15px; text-align: center;"><a href="solve.php?project_id=<?php echo $p['id']; ?>" class="btn btn-outline" style="padding: 8px 20px; font-size: 13px;">متابعة</a></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($availableProjects)): ?>
        <h2 class="section-title">مشاريع متاحة للمشاركة</h2>
        <div class="project-cards-grid">
            <?php foreach ($availableProjects as $p): ?>
            <div class="project-card" style="border: 2px dashed rgba(26, 92, 75, 0.3);">
                <div class="project-card-header">
                    <h4><?php echo htmlspecialchars($p['title']); ?></h4>
                    <span class="status-badge" style="background: #dbeafe; color: #1e40af;">جديد</span>
                </div>
                <?php if (!empty($p['description'])): ?>
                <p style="font-size: 13px; color: var(--text-gray); margin-bottom: 15px; line-height: 1.6;"><?php echo htmlspecialchars(mb_substr($p['description'], 0, 80)); ?><?php echo mb_strlen($p['description']) > 80 ? '...' : ''; ?></p>
                <?php endif; ?>
                <div class="project-card-stats">
                    <div class="mini-stat"><div class="mini-stat-value"><?php echo $p['total_questions']; ?></div><div class="mini-stat-label">سؤال</div></div>
                    <div class="mini-stat"><div class="mini-stat-value" style="color: var(--primary-green);"><?php echo $p['participants'] ?? 0; ?></div><div class="mini-stat-label">مشارك</div></div>
                    <div class="mini-stat"><div class="mini-stat-value" style="color: var(--accent-magenta);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div><div class="mini-stat-label">متاح</div></div>
                </div>
                <div style="margin-top: 15px; text-align: center;"><a href="solve.php?project_id=<?php echo $p['id']; ?>" class="btn btn-primary" style="padding: 10px 25px; font-size: 13px;">ابدأ الآن</a></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="tables-grid">
            <div class="table-card">
                <h3 class="table-title">سجل المشاريع المكتملة</h3>
                <?php if (empty($history)): ?>
                    <div class="empty-state"><p>لم تكمل أي مشروع بعد</p><a href="projects.php" class="btn btn-primary" style="margin-top:15px;">ابدأ مشروع جديد</a></div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>#</th><th>المشروع</th><th>الأسئلة</th><th>النتيجة</th><th>التاريخ</th></tr></thead>
                        <tbody>
                            <?php foreach ($history as $i => $h): ?>
                            <tr>
                                <td><span class="badge badge-info"><?php echo $i + 1; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($h['title']); ?></strong></td>
                                <td><?php echo $h['total_questions']; ?></td>
                                <td><span class="badge <?php echo $h['score'] >= 80 ? 'badge-success' : ($h['score'] >= 50 ? 'badge-warning' : 'badge-error'); ?>"><?php echo number_format($h['score'], 1); ?>%</span></td>
                                <td style="color:var(--text-gray); font-size: 13px;"><?php echo $h['completed_at'] ? date('Y/m/d', strtotime($h['completed_at'])) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="table-card">
                <h3 class="table-title">ملخص الإجابات</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 25px; background: #dcfce7; border-radius: 12px;">
                        <div style="font-size: 32px; font-weight: 800; color: #166534;"><?php echo $correctAnswers['total']; ?></div>
                        <div style="font-size: 14px; color: #166534; margin-top: 5px;">إجابات صحيحة</div>
                    </div>
                    <div style="text-align: center; padding: 25px; background: #fee2e2; border-radius: 12px;">
                        <div style="font-size: 32px; font-weight: 800; color: #dc2626;"><?php echo $wrongAnswers['total']; ?></div>
                        <div style="font-size: 14px; color: #dc2626; margin-top: 5px;">إجابات خاطئة</div>
                    </div>
                </div>
                <?php $totalCorrectWrong = $correctAnswers['total'] + $wrongAnswers['total']; $correctPercent = $totalCorrectWrong > 0 ? round(($correctAnswers['total'] / $totalCorrectWrong) * 100) : 0; ?>
                <div style="text-align: center; padding: 20px; background: var(--cream-bg); border-radius: 12px;">
                    <div style="font-size: 14px; color: var(--text-gray); margin-bottom: 10px;">نسبة الإجابات الصحيحة</div>
                    <div style="width: 120px; height: 120px; margin: 0 auto; position: relative;">
                        <svg viewBox="0 0 36 36" style="transform: rotate(-90deg);"><path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e5e5e5" stroke-width="3"/><path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="<?php echo $correctPercent >= 70 ? '#22c55e' : ($correctPercent >= 50 ? '#f59e0b' : '#ef4444'); ?>" stroke-width="3" stroke-dasharray="<?php echo $correctPercent; ?>, 100" stroke-linecap="round"/></svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 24px; font-weight: 800; color: var(--dark-green);"><?php echo $correctPercent; ?>%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>
    
    <script>
        window.addEventListener('scroll', function() { document.getElementById('header').classList.toggle('scrolled', window.scrollY > 50); });
        Chart.defaults.font.family = 'Tajawal';
        
        <?php if (!empty($chartScores)): ?>
        new Chart(document.getElementById('scoresLineChart').getContext('2d'), {
            type: 'line',
            data: { labels: <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>, datasets: [{ label: 'النتيجة %', data: <?php echo json_encode($chartScores); ?>, borderColor: '#9c2d5a', backgroundColor: 'rgba(156, 45, 90, 0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#9c2d5a', pointRadius: 6, pointHoverRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
        });
        <?php endif; ?>
        
        <?php if ($completed['total'] > 0 || $inProgress['total'] > 0 || $notStarted['total'] > 0): ?>
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: { labels: ['مكتملة', 'قيد التقدم', 'لم تبدأ'], datasets: [{ data: [<?php echo $completed['total']; ?>, <?php echo $inProgress['total']; ?>, <?php echo $notStarted['total']; ?>], backgroundColor: ['#1a5c4b', '#f59e0b', '#e5e5e5'], borderWidth: 0, hoverOffset: 10 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', rtl: true, labels: { padding: 20, usePointStyle: true } } } }
        });
        <?php endif; ?>
        
        <?php if (!empty($typeLabels)): ?>
        new Chart(document.getElementById('typePerformanceChart').getContext('2d'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($typeLabels, JSON_UNESCAPED_UNICODE); ?>, datasets: [{ label: 'صحيحة', data: <?php echo json_encode($typeCorrect); ?>, backgroundColor: '#1a5c4b', borderRadius: 8 }, { label: 'إجمالي', data: <?php echo json_encode($typeTotal); ?>, backgroundColor: 'rgba(156, 45, 90, 0.3)', borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', rtl: true } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
        });
        <?php endif; ?>
        
        new Chart(document.getElementById('activityChart').getContext('2d'), {
            type: 'bar',
            data: { labels: <?php echo json_encode($activityLabels, JSON_UNESCAPED_UNICODE); ?>, datasets: [{ label: 'عدد الإجابات', data: <?php echo json_encode($activityCounts); ?>, backgroundColor: ['rgba(156, 45, 90, 0.7)', 'rgba(26, 92, 75, 0.7)', 'rgba(124, 58, 237, 0.7)', 'rgba(245, 158, 11, 0.7)', 'rgba(16, 185, 129, 0.7)', 'rgba(59, 130, 246, 0.7)', 'rgba(239, 68, 68, 0.7)'], borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { stepSize: 1 } }, x: { grid: { display: false } } } }
        });
    </script>
</body>
</html>
