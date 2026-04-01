<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();

try {
    $totalUsers = fetchOne("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $totalProjects = fetchOne("SELECT COUNT(*) as total FROM projects");
    $totalQuestions = fetchOne("SELECT COUNT(*) as total FROM qbank");
    $totalCompleted = fetchOne("SELECT COUNT(*) as total FROM user_project_progress WHERE status = 'completed'");
    $avgScore = fetchOne("SELECT AVG(score) as average FROM user_project_progress WHERE status = 'completed'");
    $topUsers = fetchAll("SELECT u.full_name, COUNT(upp.id) as completed, AVG(upp.score) as avg_score FROM users u JOIN user_project_progress upp ON u.id = upp.user_id AND upp.status = 'completed' WHERE u.role = 'user' GROUP BY u.id ORDER BY completed DESC LIMIT 5");
    $projectStats = fetchAll("SELECT p.title, p.total_questions, (SELECT COUNT(*) FROM user_project_progress WHERE project_id = p.id) as participants, (SELECT COUNT(*) FROM user_project_progress WHERE project_id = p.id AND status = 'completed') as completed, (SELECT AVG(score) FROM user_project_progress WHERE project_id = p.id AND status = 'completed') as avg_score FROM projects p ORDER BY participants DESC LIMIT 6");
    $questionTypes = fetchAll("SELECT question_type, COUNT(*) as count FROM qbank GROUP BY question_type");
    $dialectStats = fetchAll("SELECT dialect_type, COUNT(*) as count FROM qbank GROUP BY dialect_type");
} catch (Exception $e) {
    $totalUsers = $totalProjects = $totalQuestions = $totalCompleted = $avgScore = ['total' => 0, 'average' => 0];
    $topUsers = $projectStats = $questionTypes = $dialectStats = [];
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

// إعداد بيانات الرسوم البيانية للهجات
$dialectChartLabels = [];
$dialectChartCounts = [];
$dialectChartColors = [];
foreach ($dialectStats as $ds) {
    $dialect = $ds['dialect_type'] ?? 'General';
    $dialectChartLabels[] = $dialectLabels[$dialect] ?? $dialect;
    $dialectChartCounts[] = (int)$ds['count'];
    $dialectChartColors[] = $dialectColors[$dialect] ?? '#6b7280';
}

$questionTypeLabels = [];
$questionTypeCounts = [];
$typeNames = ['multiple_choice' => 'اختيار متعدد', 'true_false' => 'صح/خطأ', 'open_ended' => 'مفتوح', 'list_selection' => 'قائمة'];
foreach ($questionTypes as $qt) {
    $questionTypeLabels[] = $typeNames[$qt['question_type']] ?? $qt['question_type'];
    $questionTypeCounts[] = (int)$qt['count'];
}

$projectLabels = [];
$projectParticipants = [];
$projectCompleted = [];
foreach ($projectStats as $p) {
    $projectLabels[] = mb_substr($p['title'], 0, 15);
    $projectParticipants[] = (int)$p['participants'];
    $projectCompleted[] = (int)$p['completed'];
}

$currentPage = 'statistics';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإحصائيات - الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary-green: #1a5c4b; --dark-green: #0d3d32; --deep-green: #0a2e26; --accent-magenta: #9c2d5a; --cream-bg: #f8f5f0; --cream-light: #fcfaf7; --text-dark: #1a1a1a; --text-gray: #5a5a5a; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tajawal', sans-serif; background: var(--cream-bg); color: var(--text-dark); line-height: 1.7; direction: rtl; }
        header { background: var(--cream-light); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        nav { max-width: 1300px; margin: 0 auto; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { display: flex; text-decoration: none; align-items: center; gap: 15px; }
        .nav-logo { width: 50px; height: 50px; border-radius: 10px; overflow: hidden; }
        .nav-logo img { width: 100%; height: 100%; object-fit: cover; }
        .nav-title { font-family: 'Amiri', serif; font-size: 22px; font-weight: 700; color: var(--accent-magenta); }
        .nav-badge { background: var(--dark-green); color: white; font-size: 11px; padding: 3px 10px; border-radius: 20px; }
        .nav-links { display: flex; align-items: center; gap: 35px; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--text-dark); font-weight: 500; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .nav-links a svg { width: 18px; height: 18px; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-magenta); }
        .nav-user { display: flex; align-items: center; gap: 15px; }
        .user-name { font-weight: 600; }
        .btn-logout { background: transparent; border: 2px solid var(--accent-magenta); color: var(--accent-magenta); padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; }
        .btn-logout:hover { background: var(--accent-magenta); color: white; }
        .pattern-strip { height: 45px; background-color: var(--deep-green); background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png'); background-repeat: repeat-x; background-size: auto 100%; margin-top: 80px; }
        .main-content { max-width: 1300px; margin: 0 auto; padding: 40px; }
        .page-title { font-family: 'Amiri', serif; font-size: 32px; color: var(--dark-green); margin-bottom: 30px; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-right: 4px solid var(--accent-magenta); }
        .stat-icon { width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary-green), var(--dark-green)); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .stat-icon svg { width: 24px; height: 24px; stroke: white; }
        .stat-value { font-size: 32px; font-weight: 800; color: var(--accent-magenta); margin-bottom: 5px; }
        .stat-label { font-size: 14px; color: var(--text-gray); }
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .chart-title { font-size: 18px; color: var(--dark-green); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .chart-title svg { width: 20px; height: 20px; }
        .chart-container { height: 250px; }
        .tables-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 40px; }
        .table-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .table-title { font-size: 18px; color: var(--dark-green); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.05); }
        th { background: var(--cream-bg); font-weight: 600; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-primary { background: #f3e8ff; color: #7c3aed; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .empty-state { text-align: center; padding: 40px; color: var(--text-gray); }
        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } .charts-grid, .tables-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { nav { flex-wrap: wrap; gap: 15px; } .nav-links { order: 3; width: 100%; justify-content: center; gap: 15px; flex-wrap: wrap; } .pattern-strip { margin-top: 140px; } .main-content { padding: 20px; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <header id="header">
        <nav>
            <a href="dashboard.php" class="nav-brand">
                <div class="nav-logo"><img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا"></div>
                <span class="nav-title">عِزّنا بلهجتنا</span>
                <span class="nav-badge">لوحة الإدارة</span>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>الرئيسية</a></li>
                <li><a href="projects.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>المشاريع</a></li>
                <li><a href="question_bank.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>بنك الأسئلة</a></li>
                <li><a href="users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>المستخدمين</a></li>
                <li><a href="statistics.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>الإحصائيات</a></li>
            </ul>
            <div class="nav-user">
                <span class="user-name">مرحباً، <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
            </div>
        </nav>
    </header>

    <div class="pattern-strip"></div>

    <div class="main-content">
        <h1 class="page-title">إحصائيات المنصة</h1>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                <div class="stat-value"><?php echo $totalUsers['total'] ?? 0; ?></div>
                <div class="stat-label">المستخدمين</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
                <div class="stat-value"><?php echo $totalProjects['total'] ?? 0; ?></div>
                <div class="stat-label">المشاريع</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                <div class="stat-value"><?php echo $totalQuestions['total'] ?? 0; ?></div>
                <div class="stat-label">الأسئلة</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div class="stat-value"><?php echo $totalCompleted['total'] ?? 0; ?></div>
                <div class="stat-label">مكتملة</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></div>
                <div class="stat-value"><?php echo number_format($avgScore['average'] ?? 0, 1); ?>%</div>
                <div class="stat-label">المعدل</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>توزيع أنواع الأسئلة</h3>
                <div class="chart-container"><canvas id="questionTypesChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    توزيع الأسئلة حسب اللهجة
                </h3>
                <div class="chart-container"><canvas id="dialectsChart"></canvas></div>
            </div>
        </div>
        
        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>إحصائيات المشاريع</h3>
                <div class="chart-container"><canvas id="projectsChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>أداء المشاريع</h3>
                <div class="chart-container"><canvas id="projectScoresChart"></canvas></div>
            </div>
        </div>

        <!-- Tables -->
        <div class="tables-grid">
            <div class="table-card">
                <h3 class="table-title">أفضل المستخدمين</h3>
                <?php if (empty($topUsers)): ?>
                    <div class="empty-state"><p>لا توجد بيانات بعد</p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>#</th><th>المستخدم</th><th>المشاريع المكتملة</th><th>المعدل</th></tr></thead>
                    <tbody>
                    <?php foreach ($topUsers as $i => $user): ?>
                    <tr>
                        <td><span class="badge badge-info"><?php echo $i + 1; ?></span></td>
                        <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                        <td><span class="badge badge-primary"><?php echo $user['completed']; ?></span></td>
                        <td><span class="badge badge-success"><?php echo number_format($user['avg_score'], 1); ?>%</span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            <div class="table-card">
                <h3 class="table-title">إحصائيات اللهجات</h3>
                <table>
                    <thead><tr><th>اللهجة</th><th>عدد الأسئلة</th></tr></thead>
                    <tbody>
                    <?php foreach ($dialectStats as $ds): 
                        $dialect = $ds['dialect_type'] ?? 'General';
                        $dialectLabel = $dialectLabels[$dialect] ?? $dialect;
                        $color = $dialectColors[$dialect] ?? '#6b7280';
                    ?>
                    <tr>
                        <td><span class="badge" style="background: <?php echo $color; ?>; color: white;"><?php echo $dialectLabel; ?></span></td>
                        <td><strong><?php echo $ds['count']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dialectStats)): ?>
                    <tr><td colspan="2" class="empty-state">لا توجد بيانات</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>

    <script>
        // Question Types Chart
        new Chart(document.getElementById('questionTypesChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($questionTypeLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($questionTypeCounts); ?>,
                    backgroundColor: ['#9c2d5a', '#1a5c4b', '#f59e0b', '#3b82f6']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Dialects Chart
        new Chart(document.getElementById('dialectsChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($dialectChartLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($dialectChartCounts); ?>,
                    backgroundColor: <?php echo json_encode($dialectChartColors); ?>
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Projects Chart
        new Chart(document.getElementById('projectsChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($projectLabels); ?>,
                datasets: [
                    { label: 'المشاركين', data: <?php echo json_encode($projectParticipants); ?>, backgroundColor: '#1a5c4b' },
                    { label: 'المكتملين', data: <?php echo json_encode($projectCompleted); ?>, backgroundColor: '#9c2d5a' }
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });

        // Project Scores Chart
        new Chart(document.getElementById('projectScoresChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($projectLabels); ?>,
                datasets: [{
                    label: 'المعدل',
                    data: <?php echo json_encode(array_map(function($p) { return round($p['avg_score'] ?? 0, 1); }, $projectStats)); ?>,
                    borderColor: '#9c2d5a',
                    fill: false
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</body>
</html>
