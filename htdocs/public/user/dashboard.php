<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireLogin();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

try {
    $completedProjects = fetchOne("SELECT COUNT(*) as total FROM user_project_progress WHERE user_id = ? AND status = 'completed'", [$userId]);
    $inProgressProjects = fetchOne("SELECT COUNT(*) as total FROM user_project_progress WHERE user_id = ? AND status = 'in_progress'", [$userId]);
    $avgScore = fetchOne("SELECT AVG(score) as average FROM user_project_progress WHERE user_id = ? AND status = 'completed'", [$userId]);
    $totalProjects = fetchOne("SELECT COUNT(*) as total FROM projects WHERE is_active = 1", []);
    
    // جلب كل المشاريع
    $availableProjects = fetchAll("SELECT * FROM projects WHERE is_active = 1 ORDER BY created_at DESC LIMIT 6");
    
    // إذا فشل، جرب بدون شرط
    if (!$availableProjects || empty($availableProjects)) {
        $availableProjects = fetchAll("SELECT * FROM projects ORDER BY created_at DESC LIMIT 6");
    }
    
    // إضافة حالة المستخدم لكل مشروع
    if (is_array($availableProjects)) {
        foreach ($availableProjects as &$proj) {
            $progress = fetchOne("SELECT status, current_question, score FROM user_project_progress WHERE project_id = ? AND user_id = ?", [$proj['id'], $userId]);
            $proj['status'] = $progress['status'] ?? null;
            $proj['current_question'] = $progress['current_question'] ?? 0;
            $proj['score'] = $progress['score'] ?? null;
        }
    }
} catch (Exception $e) {
    $completedProjects = $inProgressProjects = $totalProjects = ['total' => 0];
    $avgScore = ['average' => 0];
    $availableProjects = [];
}

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - عزنا بلهجتنا</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #1a5c4b;
            --dark-green: #0d3d32;
            --deep-green: #0a2e26;
            --accent-magenta: #9c2d5a;
            --light-magenta: #b33d6a;
            --cream-bg: #f8f5f0;
            --cream-light: #fcfaf7;
            --card-bg: #ffffff;
            --text-dark: #1a1a1a;
            --text-gray: #5a5a5a;
            --success: #22c55e;
            --warning: #f59e0b;
            --error: #ef4444;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--cream-bg);
            color: var(--text-dark);
            line-height: 1.7;
            direction: rtl;
        }

        /* ===== Header ===== */
        header {
            background: var(--cream-light);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        header.scrolled {
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        nav {
            max-width: 1300px;
            margin: 0 auto;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-logo {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            overflow: hidden;
        }

        .nav-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-title {
            font-family: 'Amiri', serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--accent-magenta);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 40px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            font-size: 15px;
            transition: color 0.3s;
            position: relative;
            padding-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a svg {
            width: 18px;
            height: 18px;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0;
            height: 2px;
            background: var(--accent-magenta);
            transition: width 0.3s ease;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: var(--accent-magenta);
        }

        .nav-links a:hover::after,
        .nav-links a.active::after {
            width: 100%;
        }

        .nav-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-dark);
        }

        .btn-logout {
            background: transparent;
            border: 2px solid var(--accent-magenta);
            color: var(--accent-magenta);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: var(--accent-magenta);
            color: white;
        }

        /* ===== Pattern Strip ===== */
        .pattern-strip {
            height: 45px;
            background-color: var(--deep-green);
            background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png');
            background-repeat: repeat-x;
            background-size: auto 100%;
            background-position: center;
            margin-top: 80px;
        }

        /* ===== Main Content ===== */
        .main-content {
            max-width: 1300px;
            margin: 0 auto;
            padding: 40px;
        }

        /* ===== Welcome Card ===== */
        .welcome-card {
            background: linear-gradient(135deg, var(--dark-green) 0%, var(--deep-green) 100%);
            border-radius: 20px;
            padding: 50px;
            color: white;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png');
            background-repeat: repeat;
            background-size: 200px;
            opacity: 0.05;
        }

        .welcome-card h1 {
            font-family: 'Amiri', serif;
            font-size: 32px;
            margin-bottom: 10px;
            position: relative;
        }

        .welcome-card p {
            font-size: 18px;
            opacity: 0.9;
            position: relative;
        }

        /* ===== Stats Grid ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 4px;
            height: 100%;
            background: var(--accent-magenta);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            background: linear-gradient(135deg, var(--primary-green), var(--dark-green));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .stat-icon svg {
            width: 28px;
            height: 28px;
            stroke: white;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: var(--accent-magenta);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-gray);
            font-weight: 500;
        }

        /* ===== Section Title ===== */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-family: 'Amiri', serif;
            font-size: 26px;
            color: var(--dark-green);
        }

        .section-subtitle {
            font-size: 14px;
            color: var(--text-gray);
            margin-top: 5px;
        }

        .btn-view-all {
            color: var(--accent-magenta);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view-all:hover {
            text-decoration: underline;
        }

        /* ===== Projects Grid ===== */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .project-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--accent-magenta);
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .project-card h4 {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark-green);
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-primary { background: #f3e8ff; color: #7c3aed; }

        .project-card p {
            font-size: 14px;
            color: var(--text-gray);
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .project-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            font-size: 13px;
            color: var(--text-gray);
        }

        .progress-bar {
            height: 6px;
            background: var(--cream-bg);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-magenta), var(--light-magenta));
            border-radius: 3px;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            width: 100%;
        }

        .btn-primary {
            background: var(--accent-magenta);
            color: white;
        }

        .btn-primary:hover {
            background: var(--light-magenta);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
        }

        .btn-outline:hover {
            background: var(--primary-green);
            color: white;
        }

        /* ===== Empty State ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-gray);
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        /* ===== Footer ===== */
        footer {
            background: var(--deep-green);
            color: white;
            text-align: center;
            padding: 25px;
            margin-top: 60px;
        }

        footer p {
            opacity: 0.8;
            font-size: 14px;
        }

        /* ===== Responsive ===== */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            nav {
                padding: 15px 20px;
                flex-wrap: wrap;
                gap: 15px;
            }

            .nav-links {
                order: 3;
                width: 100%;
                justify-content: center;
                gap: 20px;
                padding-top: 15px;
                border-top: 1px solid rgba(0,0,0,0.1);
            }

            .pattern-strip {
                margin-top: 130px;
            }

            .main-content {
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .projects-grid {
                grid-template-columns: 1fr;
            }

            .welcome-card {
                padding: 30px;
            }

            .welcome-card h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header id="header">
        <nav>
            <a href="dashboard.php" class="nav-brand" style="text-decoration: none;">
                <div class="nav-logo">
                    <img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا">
                </div>
                <span class="nav-title">عِزّنا بلهجتنا</span>
            </a>

            <ul class="nav-links">
                <li><a href="dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    لوحة التحكم
                </a></li>
                <li><a href="projects.php" class="<?php echo $currentPage === 'projects' ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    المشاريع
                </a></li>
                <li><a href="statistics.php" class="<?php echo $currentPage === 'statistics' ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    الإحصائيات
                </a></li>
            </ul>

            <div class="nav-user">
                <span class="user-name">مرحباً، <?php echo htmlspecialchars($userName); ?></span>
                <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
            </div>
        </nav>
    </header>

    <!-- Pattern Strip -->
    <div class="pattern-strip"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h1>ابدأ رحلتك في توثيق اللهجات</h1>
            <p>اختر مشروعاً من المشاريع المتاحة وساهم في الحفاظ على تراثنا اللغوي</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <div class="stat-value"><?php echo $completedProjects['total']; ?></div>
                <div class="stat-label">مشاريع مكتملة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <div class="stat-value"><?php echo $inProgressProjects['total']; ?></div>
                <div class="stat-label">قيد التقدم</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                </div>
                <div class="stat-value"><?php echo number_format($avgScore['average'] ?? 0, 1); ?>%</div>
                <div class="stat-label">متوسط النتيجة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <div class="stat-value"><?php echo ($userTotalProjects['total'] ?? 0); ?></div>
                <div class="stat-label">مجمل مشاريعي</div>
            </div>
        </div>

        <!-- Available Projects -->
        <div class="section-header">
            <div>
                <h2 class="section-title">المشاريع المتاحة</h2>
                <p class="section-subtitle">اختر مشروعاً للبدء أو متابعة العمل</p>
            </div>
            <a href="projects.php" class="btn-view-all">عرض الكل ←</a>
        </div>

        <?php if (empty($availableProjects)): ?>
            <div class="empty-state">
                <p>لا توجد مشاريع متاحة حالياً</p>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($availableProjects as $project): ?>
                <div class="project-card">
                    <div class="project-header">
                        <h4><?php echo htmlspecialchars($project['title']); ?></h4>
                        <?php if ($project['status'] === 'completed'): ?>
                            <span class="badge badge-success">مكتمل</span>
                        <?php elseif ($project['status'] === 'in_progress'): ?>
                            <span class="badge badge-warning">قيد التقدم</span>
                        <?php else: ?>
                            <span class="badge badge-primary">جديد</span>
                        <?php endif; ?>
                    </div>
                    
                    <p><?php echo htmlspecialchars(substr($project['description'] ?? 'لا يوجد وصف', 0, 100)); ?></p>
                    
                    <div class="project-meta">
                        <span><?php echo $project['total_questions']; ?> سؤال</span>
                        <?php if ($project['status'] === 'completed' && $project['score']): ?>
                            <span>النتيجة: <?php echo number_format($project['score'], 1); ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($project['status'] === 'in_progress'): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo ($project['current_question'] / $project['total_questions']) * 100; ?>%"></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($project['status'] === 'completed'): ?>
                        <a href="review.php?project_id=<?php echo $project['id']; ?>" class="btn btn-outline">مراجعة الإجابات</a>
                    <?php elseif ($project['status'] === 'in_progress'): ?>
                        <a href="solve.php?project_id=<?php echo $project['id']; ?>" class="btn btn-primary">متابعة الحل</a>
                    <?php else: ?>
                        <a href="solve.php?project_id=<?php echo $project['id']; ?>" class="btn btn-primary">ابدأ الآن</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p>
    </footer>

    <script>
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
