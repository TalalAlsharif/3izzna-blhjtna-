<?php
/**
 * Enhanced Question Bank Page with Better Error Handling
 * نسخة محسنة من صفحة بنك الأسئلة مع معالجة أخطاء أفضل
 */

session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();

// متغير لتخزين رسائل التصحيح (يمكن تعطيلها في الإنتاج)
$debugMode = false; // غيرها إلى true للتصحيح
$debugMessages = [];
$hasError = false;

// تعريفات اللهجات
$dialectLabels = [
    'Central'  => 'الوسطى',
    'Western'  => 'الغربية',
    'Eastern'  => 'الشرقية',
    'Southern' => 'الجنوبية',
    'Northern' => 'الشمالية',
    'General'  => 'عام',
];

$dialectFilters = [
    'all'      => 'الكل',
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

$filterType = $_GET['type'] ?? 'all';
$filterDialect = $_GET['dialect'] ?? 'all';
$questions = [];
$totalQuestions = ['total' => 0];
$questionTypes = [];
$dialectCounts = [];

// التحقق من الاتصال بقاعدة البيانات
if ($debugMode) {
    try {
        $testQuery = $pdo->query("SELECT 1");
        $debugMessages[] = "✅ اتصال قاعدة البيانات ناجح";
    } catch (Exception $e) {
        $hasError = true;
        $debugMessages[] = "❌ خطأ في الاتصال: " . $e->getMessage();
    }
}

try {
    $whereConditions = [];
    $params = [];
    
    if ($filterType !== 'all') {
        $whereConditions[] = "question_type = ?";
        $params[] = $filterType;
    }
    
    if ($filterDialect !== 'all') {
        $whereConditions[] = "dialect_type = ?";
        $params[] = $filterDialect;
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    $questionsResult = fetchAll("SELECT * FROM qbank $whereClause ORDER BY id DESC LIMIT 100", $params);
    $questions = is_array($questionsResult) ? $questionsResult : [];
    
    // تشخيص إضافي في حالة عدم وجود نتائج
    if ($debugMode && empty($questions) && $filterType !== 'all') {
        $allCount = fetchOne("SELECT COUNT(*) as total FROM qbank");
        $debugMessages[] = "📊 إجمالي الأسئلة في الجدول: " . ($allCount['total'] ?? 0);
        
        $typeCount = fetchOne("SELECT COUNT(*) as total FROM qbank WHERE question_type = ?", [$filterType]);
        $debugMessages[] = "📊 أسئلة من نوع '{$filterType}': " . ($typeCount['total'] ?? 0);
    }
    
    $totalResult = fetchOne("SELECT COUNT(*) as total FROM qbank");
    $totalQuestions = is_array($totalResult) ? $totalResult : ['total' => 0];
    
    $typesResult = fetchAll("SELECT question_type, COUNT(*) as count FROM qbank GROUP BY question_type");
    $questionTypes = is_array($typesResult) ? $typesResult : [];
    
    $dialectsResult = fetchAll("SELECT dialect_type, COUNT(*) as count FROM qbank GROUP BY dialect_type");
    if (is_array($dialectsResult)) {
        foreach ($dialectsResult as $d) {
            $dialectCounts[$d['dialect_type']] = $d['count'];
        }
    }
} catch (Exception $e) {
    if ($debugMode) {
        $hasError = true;
        $debugMessages[] = "❌ خطأ في جلب البيانات: " . $e->getMessage();
    }
}

$currentPage = 'questions';
$typeLabels = ['multiple_choice' => 'اختيار متعدد', 'true_false' => 'صح/خطأ', 'open_ended' => 'مفتوح', 'list_selection' => 'قائمة'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بنك الأسئلة - الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-family: 'Amiri', serif; font-size: 32px; color: var(--dark-green); }
        .btn { padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; border: none; cursor: pointer; }
        .btn-primary { background: var(--accent-magenta); color: white; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-box { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-box .value { font-size: 28px; font-weight: 800; color: var(--accent-magenta); margin-bottom: 5px; }
        .stat-box .label { font-size: 13px; color: var(--text-gray); }
        .stat-box.dialect { border-top: 3px solid; }
        
        .filter-bar { background: white; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .filter-section { margin-bottom: 15px; }
        .filter-section:last-child { margin-bottom: 0; }
        .filter-label { font-weight: 600; font-size: 14px; color: var(--dark-green); margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .filter-label svg { width: 18px; height: 18px; }
        .filter-buttons { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn-outline { padding: 8px 16px; border-radius: 8px; border: 2px solid #e5e5e5; background: white; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: 600; transition: all 0.3s; text-decoration: none; color: var(--text-dark); }
        .btn-outline:hover { border-color: var(--accent-magenta); color: var(--accent-magenta); }
        .btn-outline.active { background: var(--accent-magenta); color: white; border-color: var(--accent-magenta); }
        .btn-outline.dialect-btn { position: relative; padding-right: 28px; }
        .btn-outline.dialect-btn::before { content: ''; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 10px; height: 10px; border-radius: 50%; }
        
        .dialect-Central::before { background: #8b5cf6; }
        .dialect-Western::before { background: #ec4899; }
        .dialect-Eastern::before { background: #06b6d4; }
        .dialect-Southern::before { background: #10b981; }
        .dialect-Northern::before { background: #f59e0b; }
        .dialect-General::before { background: #6b7280; }
        
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.05); }
        th { background: var(--cream-bg); font-weight: 600; }
        tr:hover { background: var(--cream-light); }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; color: white; display: inline-block; }
        .badge-multiple_choice { background: #9c2d5a; }
        .badge-true_false { background: #1a5c4b; }
        .badge-open_ended { background: #d97706; }
        .badge-list_selection { background: #2563eb; }
        
        .badge-dialect { padding: 4px 10px; border-radius: 15px; font-size: 10px; font-weight: 600; color: white; }
        .badge-Central { background: #8b5cf6; }
        .badge-Western { background: #ec4899; }
        .badge-Eastern { background: #06b6d4; }
        .badge-Southern { background: #10b981; }
        .badge-Northern { background: #f59e0b; }
        .badge-General { background: #6b7280; }
        
        .empty-state { text-align: center; padding: 60px; color: var(--text-gray); }
        .results-count { font-size: 14px; color: var(--text-gray); margin-bottom: 15px; }
        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
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
                <li><a href="question_bank.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>بنك الأسئلة</a></li>
                <li><a href="users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>المستخدمين</a></li>
                <li><a href="statistics.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>الإحصائيات</a></li>
            </ul>
            <div class="nav-user">
                <span class="user-name">مرحباً، <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
            </div>
        </nav>
    </header>

    <div class="pattern-strip"></div>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">بنك الأسئلة</h1>
            <a href="import_questions.php" class="btn btn-primary">استيراد أسئلة</a>
        </div>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="value"><?php echo $totalQuestions['total'] ?? 0; ?></div>
                <div class="label">إجمالي الأسئلة</div>
            </div>
            <?php foreach ($questionTypes as $type): ?>
            <div class="stat-box">
                <div class="value"><?php echo $type['count']; ?></div>
                <div class="label"><?php echo $typeLabels[$type['question_type']] ?? $type['question_type']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="stats-grid">
            <?php foreach ($dialectLabels as $key => $label): ?>
            <div class="stat-box dialect" style="border-color: <?php echo $dialectColors[$key]; ?>">
                <div class="value" style="color: <?php echo $dialectColors[$key]; ?>"><?php echo $dialectCounts[$key] ?? 0; ?></div>
                <div class="label"><?php echo $label; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="filter-bar">
            <div class="filter-section">
                <div class="filter-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                    فلترة حسب نوع السؤال
                </div>
                <div class="filter-buttons">
                    <a href="?type=all&dialect=<?php echo $filterDialect; ?>" class="btn-outline <?php echo $filterType === 'all' ? 'active' : ''; ?>">الكل</a>
                    <?php foreach ($typeLabels as $key => $label): ?>
                    <a href="?type=<?php echo $key; ?>&dialect=<?php echo $filterDialect; ?>" class="btn-outline <?php echo $filterType === $key ? 'active' : ''; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="filter-section">
                <div class="filter-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    فلترة حسب اللهجة
                </div>
                <div class="filter-buttons">
                    <?php foreach ($dialectFilters as $key => $label): ?>
                    <a href="?type=<?php echo $filterType; ?>&dialect=<?php echo $key; ?>" class="btn-outline dialect-btn <?php echo $key !== 'all' ? 'dialect-' . $key : ''; ?> <?php echo $filterDialect === $key ? 'active' : ''; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="results-count">عرض <?php echo count($questions); ?> سؤال</div>
            
            <?php if (empty($questions)): ?>
                <div class="empty-state">
                    <p style="font-size: 18px; margin-bottom: 20px;">❌ لا توجد أسئلة مطابقة للفلتر</p>
                    <?php if ($totalQuestions['total'] == 0): ?>
                        <p style="margin-bottom: 20px;">💡 قاعدة البيانات فارغة. يرجى استيراد الأسئلة أو إضافة أسئلة جديدة.</p>
                        <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
                            <strong>خيار 1:</strong> استورد ملف <code>data_annotation_platform.sql</code> من phpMyAdmin<br>
                            <strong>خيار 2:</strong> استخدم زر "استيراد أسئلة" أدناه<br>
                            <strong>خيار 3:</strong> استورد ملف <code>add_sample_questions.sql</code> لإضافة أسئلة تجريبية
                        </p>
                    <?php else: ?>
                        <p style="margin-bottom: 20px;">💡 يوجد <?php echo $totalQuestions['total']; ?> سؤال في البنك، لكن لا شيء يطابق الفلتر المحدد.</p>
                        <p style="font-size: 14px; color: #666; margin-bottom: 20px;">
                            جرب فلتر مختلف أو قم بإضافة أسئلة جديدة من نوع "<?php echo $typeLabels[$filterType] ?? $filterType; ?>"
                        </p>
                    <?php endif; ?>
                    <a href="import_questions.php" class="btn btn-primary">استيراد أسئلة</a>
                    <?php if ($debugMode && !empty($debugMessages)): ?>
                        <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-radius: 8px; text-align: right;">
                            <strong>معلومات التصحيح:</strong>
                            <ul style="list-style: none; padding: 0; margin-top: 10px;">
                                <?php foreach ($debugMessages as $msg): ?>
                                    <li style="padding: 5px 0; font-size: 13px;"><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table>
                    <thead><tr><th>#</th><th>السؤال</th><th>النوع</th><th>اللهجة</th></tr></thead>
                    <tbody>
                        <?php foreach ($questions as $i => $q): 
                            $dialect = $q['dialect_type'] ?? 'General';
                            $dialectLabel = $dialectLabels[$dialect] ?? $dialect;
                        ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td style="max-width:400px;"><?php echo htmlspecialchars(mb_substr($q['question_text'], 0, 80)); ?>...</td>
                            <td><span class="badge badge-<?php echo $q['question_type']; ?>"><?php echo $typeLabels[$q['question_type']] ?? $q['question_type']; ?></span></td>
                            <td><span class="badge badge-dialect badge-<?php echo $dialect; ?>"><?php echo $dialectLabel; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>
</body>
</html>
