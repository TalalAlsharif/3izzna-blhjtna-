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
    $totalSubmissions = fetchOne("SELECT COUNT(*) as total FROM user_project_progress WHERE status = 'completed'");
    $totalQuestions = fetchOne("SELECT COUNT(*) as total FROM qbank");
    
    $recentUsers = fetchAll("SELECT id, full_name, email, created_at FROM users WHERE role = 'user' ORDER BY created_at DESC LIMIT 5");
    $recentProjects = fetchAll("SELECT p.id, p.title, p.total_questions, p.created_at FROM projects p ORDER BY p.created_at DESC LIMIT 5");
} catch (Exception $e) {
    $totalUsers = $totalProjects = $totalSubmissions = $totalQuestions = ['total' => 0];
    $recentUsers = $recentProjects = [];
}

$currentPage = 'dashboard';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - الإدارة</title>
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
            --text-dark: #1a1a1a;
            --text-gray: #5a5a5a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tajawal', sans-serif; background: var(--cream-bg); color: var(--text-dark); line-height: 1.7; direction: rtl; }

        header { background: var(--cream-light); border-bottom: 1px solid rgba(0,0,0,0.05); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; transition: all 0.3s ease; }
        header.scrolled { background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        nav { max-width: 1300px; margin: 0 auto; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { display: flex; align-items: center; gap: 15px; }
        .nav-logo { width: 50px; height: 50px; border-radius: 10px; overflow: hidden; }
        .nav-logo img { width: 100%; height: 100%; object-fit: cover; }
        .nav-title { font-family: 'Amiri', serif; font-size: 22px; font-weight: 700; color: var(--accent-magenta); }
        .nav-badge { background: var(--dark-green); color: white; font-size: 11px; padding: 3px 10px; border-radius: 20px; margin-right: 10px; }
        .nav-links { display: flex; align-items: center; gap: 35px; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--text-dark); font-weight: 500; font-size: 14px; transition: color 0.3s; position: relative; padding-bottom: 5px; display: flex; align-items: center; gap: 8px; }
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

        .welcome-card { background: linear-gradient(135deg, var(--dark-green) 0%, var(--deep-green) 100%); border-radius: 20px; padding: 40px; color: white; margin-bottom: 40px; position: relative; overflow: hidden; }
        .welcome-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png'); background-repeat: repeat; background-size: 200px; opacity: 0.05; }
        .welcome-card h1 { font-family: 'Amiri', serif; font-size: 28px; margin-bottom: 10px; position: relative; }
        .welcome-card p { font-size: 16px; opacity: 0.9; position: relative; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; border-radius: 16px; padding: 25px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; right: 0; width: 4px; height: 100%; background: var(--accent-magenta); }
        .stat-icon { width: 50px; height: 50px; background: linear-gradient(135deg, var(--primary-green), var(--dark-green)); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; }
        .stat-icon svg { width: 24px; height: 24px; stroke: white; }
        .stat-value { font-size: 32px; font-weight: 800; color: var(--accent-magenta); margin-bottom: 5px; }
        .stat-label { font-size: 13px; color: var(--text-gray); font-weight: 500; }

        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 30px; }
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.05); }
        .card-title { font-family: 'Amiri', serif; font-size: 20px; color: var(--dark-green); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 14px; }
        th { background: var(--cream-bg); font-weight: 600; color: var(--dark-green); }
        tr:hover { background: var(--cream-light); }
        .empty-state { text-align: center; padding: 30px; color: var(--text-gray); }

        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 13px; text-decoration: none; text-align: center; transition: all 0.3s; }
        .btn-primary { background: var(--accent-magenta); color: white; }
        .btn-primary:hover { background: var(--light-magenta); }
        .btn-secondary { background: var(--primary-green); color: white; }
        .btn-outline { background: transparent; border: 2px solid var(--primary-green); color: var(--primary-green); }
        .btn-outline:hover { background: var(--primary-green); color: white; }
        .btn-sm { padding: 8px 15px; font-size: 12px; }

        .quick-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
        .quick-actions .btn { padding: 15px; }

        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
        footer p { opacity: 0.8; font-size: 14px; }

        @media (max-width: 1024px) { 
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .grid-2 { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            nav { padding: 15px 20px; flex-wrap: wrap; gap: 15px; }
            .nav-links { order: 3; width: 100%; justify-content: center; gap: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); flex-wrap: wrap; }
            .pattern-strip { margin-top: 140px; }
            .main-content { padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header id="header">
        <nav>
            <a href="dashboard.php" class="nav-brand" style="text-decoration: none;">
                <div class="nav-logo"><img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا"></div>
                <span class="nav-title">عِزّنا بلهجتنا</span>
                <span class="nav-badge">لوحة الإدارة</span>
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>الرئيسية</a></li>
                <li><a href="projects.php" class="<?php echo $currentPage === 'projects' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>المشاريع</a></li>
                <li><a href="question_bank.php" class="<?php echo $currentPage === 'questions' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>بنك الأسئلة</a></li>
                <li><a href="users.php" class="<?php echo $currentPage === 'users' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>المستخدمين</a></li>
                <li><a href="statistics.php" class="<?php echo $currentPage === 'statistics' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>الإحصائيات</a></li>
            </ul>
            <div class="nav-user">
                <span class="user-name">مرحباً، <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
            </div>
        </nav>
    </header>

    <div class="pattern-strip"></div>

    <div class="main-content">
        <div class="welcome-card">
            <h1>مرحباً بك في لوحة الإدارة</h1>
            <p>إليك نظرة عامة على نشاط المنصة</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                <div class="stat-value"><?php echo $totalUsers['total']; ?></div>
                <div class="stat-label">المستخدمين</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
                <div class="stat-value"><?php echo $totalProjects['total']; ?></div>
                <div class="stat-label">المشاريع</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                <div class="stat-value"><?php echo $totalQuestions['total']; ?></div>
                <div class="stat-label">الأسئلة</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                <div class="stat-value"><?php echo $totalSubmissions['total']; ?></div>
                <div class="stat-label">مكتملة</div>
            </div>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">آخر المستخدمين</h3>
                    <a href="users.php" class="btn btn-outline btn-sm">عرض الكل</a>
                </div>
                <?php if (empty($recentUsers)): ?>
                    <div class="empty-state"><p>لا يوجد مستخدمون بعد</p></div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>الاسم</th><th>البريد</th><th>التاريخ</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></td>
                                <td style="color: var(--text-gray);"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td style="color: var(--text-gray);"><?php echo timeAgo($user['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">آخر المشاريع</h3>
                    <a href="projects.php" class="btn btn-outline btn-sm">عرض الكل</a>
                </div>
                <?php if (empty($recentProjects)): ?>
                    <div class="empty-state"><p>لا توجد مشاريع بعد</p></div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>المشروع</th><th>الأسئلة</th><th>التاريخ</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentProjects as $project): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($project['title']); ?></strong></td>
                                <td><?php echo $project['total_questions']; ?> سؤال</td>
                                <td style="color: var(--text-gray);"><?php echo timeAgo($project['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">إجراءات سريعة</h3>
            </div>
            <div class="quick-actions">
                <a href="project_builder.php" class="btn btn-primary">مشروع جديد</a>
                <a href="import_questions.php" class="btn btn-secondary">استيراد أسئلة</a>
                <a href="users.php" class="btn btn-secondary">إدارة المستخدمين</a>
                <a href="statistics.php" class="btn btn-secondary">الإحصائيات</a>
            </div>
        </div>
    </div>

    <footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>
    <script>window.addEventListener('scroll', function() { document.getElementById('header').classList.toggle('scrolled', window.scrollY > 50); });</script>
</body>
</html>
