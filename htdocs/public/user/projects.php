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
    // جلب جميع المشاريع أولاً
    $allProjects = fetchAll("SELECT * FROM projects WHERE is_active = 1 ORDER BY created_at DESC");
    
    // إذا لم يوجد نتائج أو كان false، جرب بدون شرط
    if (!$allProjects || !is_array($allProjects) || empty($allProjects)) {
        $allProjects = fetchAll("SELECT * FROM projects ORDER BY created_at DESC");
    }
    
    // التأكد من أن لدينا مصفوفة
    if (!is_array($allProjects)) {
        $allProjects = [];
    }
    
    // جلب حالة المستخدم لكل مشروع
    $projects = [];
    foreach ($allProjects as $project) {
        $progress = fetchOne("SELECT status, score, current_question FROM user_project_progress WHERE project_id = ? AND user_id = ?", [$project['id'], $userId]);
        $answeredCount = fetchOne("SELECT COUNT(*) as cnt FROM answers WHERE project_id = ? AND user_id = ?", [$project['id'], $userId]);
        
        $project['status'] = (is_array($progress) && isset($progress['status'])) ? $progress['status'] : null;
        $project['score'] = (is_array($progress) && isset($progress['score'])) ? $progress['score'] : null;
        $project['current_question'] = (is_array($progress) && isset($progress['current_question'])) ? $progress['current_question'] : 0;
        $project['answered_count'] = (is_array($answeredCount) && isset($answeredCount['cnt'])) ? $answeredCount['cnt'] : 0;
        
        $projects[] = $project;
    }
    
    // ترتيب: قيد التقدم أولاً، ثم الجديدة، ثم المكتملة
    usort($projects, function($a, $b) {
        $order = ['in_progress' => 1, null => 2, 'completed' => 3];
        $orderA = $order[$a['status']] ?? 2;
        $orderB = $order[$b['status']] ?? 2;
        return $orderA - $orderB;
    });
    
} catch (Exception $e) {
    $projects = [];
    $error = $e->getMessage();
}

$currentPage = 'projects';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المشاريع - عزنا بلهجتنا</title>
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
        .page-header { margin-bottom: 30px; }
        .page-title { font-family: 'Amiri', serif; font-size: 32px; color: var(--dark-green); margin-bottom: 10px; }
        .page-subtitle { font-size: 16px; color: var(--text-gray); }

        .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .project-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: var(--accent-magenta); }
        .project-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .project-card h4 { font-size: 18px; font-weight: 700; color: var(--dark-green); }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-primary { background: #f3e8ff; color: #7c3aed; }
        .project-card p { font-size: 14px; color: var(--text-gray); margin-bottom: 15px; line-height: 1.6; }
        .project-meta { display: flex; gap: 20px; margin-bottom: 20px; font-size: 13px; color: var(--text-gray); }
        .progress-bar { height: 6px; background: var(--cream-bg); border-radius: 3px; overflow: hidden; margin-bottom: 20px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, var(--accent-magenta), var(--light-magenta)); border-radius: 3px; }
        .btn { display: inline-block; padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; text-align: center; transition: all 0.3s; width: 100%; }
        .btn-primary { background: var(--accent-magenta); color: white; }
        .btn-primary:hover { background: var(--light-magenta); }
        .btn-outline { background: transparent; border: 2px solid var(--primary-green); color: var(--primary-green); }
        .btn-outline:hover { background: var(--primary-green); color: white; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-gray); background: white; border-radius: 16px; }

        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
        footer p { opacity: 0.8; font-size: 14px; }

        @media (max-width: 768px) {
            nav { padding: 15px 20px; flex-wrap: wrap; gap: 15px; }
            .nav-links { order: 3; width: 100%; justify-content: center; gap: 20px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); }
            .pattern-strip { margin-top: 130px; }
            .main-content { padding: 20px; }
            .projects-grid { grid-template-columns: 1fr; }
        }
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
                <li><a href="dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>لوحة التحكم</a></li>
                <li><a href="projects.php" class="<?php echo $currentPage === 'projects' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>المشاريع</a></li>
                <li><a href="statistics.php" class="<?php echo $currentPage === 'statistics' ? 'active' : ''; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>الإحصائيات</a></li>
            </ul>
            <div class="nav-user">
                <span class="user-name">مرحباً، <?php echo htmlspecialchars($userName); ?></span>
                <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
            </div>
        </nav>
    </header>

    <div class="pattern-strip"></div>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">المشاريع المتاحة</h1>
            <p class="page-subtitle">اختر مشروعاً للبدء أو متابعة العمل</p>
        </div>

        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <p>لا توجد مشاريع متاحة حالياً</p>
                <?php if (isset($error)): ?>
                    <p style="color: red; font-size: 12px; margin-top: 10px;">خطأ: <?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <p style="color: #999; font-size: 11px; margin-top: 5px;">
                    تأكد من وجود مشاريع في قاعدة البيانات وأن الاتصال يعمل بشكل صحيح.
                </p>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
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
                        <div class="progress-bar"><div class="progress-fill" style="width: <?php echo ($project['current_question'] / $project['total_questions']) * 100; ?>%"></div></div>
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

    <footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>
    <script>window.addEventListener('scroll', function() { document.getElementById('header').classList.toggle('scrolled', window.scrollY > 50); });</script>
</body>
</html>
