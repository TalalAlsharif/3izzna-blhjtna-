<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();

try {
    $projects = fetchAll("
        SELECT p.*, 
               (SELECT COUNT(*) FROM user_project_progress WHERE project_id = p.id) as participants,
               (SELECT COUNT(*) FROM user_project_progress WHERE project_id = p.id AND status = 'completed') as completed
        FROM projects p ORDER BY p.created_at DESC
    ");
} catch (Exception $e) {
    $projects = [];
}

$currentPage = 'projects';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المشاريع - الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary-green: #1a5c4b; --dark-green: #0d3d32; --deep-green: #0a2e26; --accent-magenta: #9c2d5a; --light-magenta: #b33d6a; --cream-bg: #f8f5f0; --cream-light: #fcfaf7; --text-dark: #1a1a1a; --text-gray: #5a5a5a; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tajawal', sans-serif; background: var(--cream-bg); color: var(--text-dark); line-height: 1.7; direction: rtl; }
        header { background: var(--cream-light); border-bottom: 1px solid rgba(0,0,0,0.05); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        header.scrolled { background: white; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        nav { max-width: 1300px; margin: 0 auto; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { display: flex; align-items: center; gap: 15px; text-decoration: none; }
        .nav-logo { width: 50px; height: 50px; border-radius: 10px; overflow: hidden; }
        .nav-logo img { width: 100%; height: 100%; object-fit: cover; }
        .nav-title { font-family: 'Amiri', serif; font-size: 22px; font-weight: 700; color: var(--accent-magenta); }
        .nav-badge { background: var(--dark-green); color: white; font-size: 11px; padding: 3px 10px; border-radius: 20px; margin-right: 10px; }
        .nav-links { display: flex; align-items: center; gap: 35px; list-style: none; }
        .nav-links a { text-decoration: none; color: var(--text-dark); font-weight: 500; font-size: 14px; position: relative; padding-bottom: 5px; display: flex; align-items: center; gap: 8px; }
        .nav-links a svg { width: 18px; height: 18px; }
        .nav-links a::after { content: ''; position: absolute; bottom: 0; right: 0; width: 0; height: 2px; background: var(--accent-magenta); transition: width 0.3s; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-magenta); }
        .nav-links a:hover::after, .nav-links a.active::after { width: 100%; }
        .nav-user { display: flex; align-items: center; gap: 20px; }
        .user-name { font-weight: 600; }
        .btn-logout { background: transparent; border: 2px solid var(--accent-magenta); color: var(--accent-magenta); padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.3s; }
        .btn-logout:hover { background: var(--accent-magenta); color: white; }
        .pattern-strip { height: 45px; background-color: var(--deep-green); background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png'); background-repeat: repeat-x; background-size: auto 100%; margin-top: 80px; }
        .main-content { max-width: 1300px; margin: 0 auto; padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-family: 'Amiri', serif; font-size: 28px; color: var(--dark-green); }
        .btn { padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.3s; border: none; cursor: pointer; }
        .btn-primary { background: var(--accent-magenta); color: white; }
        .btn-primary:hover { background: var(--light-magenta); }
        .btn-sm { padding: 10px 20px; font-size: 13px; }
        .btn-outline { background: transparent; border: 2px solid var(--primary-green); color: var(--primary-green); }
        .btn-outline:hover { background: var(--primary-green); color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-danger:hover { background: #b91c1c; }
        .projects-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .project-card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.05); transition: all 0.3s; }
        .project-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .project-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
        .project-title { font-size: 18px; font-weight: 700; color: var(--dark-green); }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .project-stats { display: flex; gap: 20px; margin: 15px 0; font-size: 13px; color: var(--text-gray); }
        .project-actions { display: flex; gap: 10px; margin-top: 20px; }
        .project-actions .btn { flex: 1; text-align: center; padding: 10px; font-size: 13px; }
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 16px; }
        .empty-state p { color: var(--text-gray); margin-bottom: 20px; }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: 16px; padding: 30px; max-width: 400px; width: 90%; text-align: center; }
        .modal h3 { color: #dc2626; margin-bottom: 15px; }
        .modal p { color: var(--text-gray); margin-bottom: 25px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions .btn { flex: 1; }
        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
        @media (max-width: 768px) { nav { flex-wrap: wrap; gap: 15px; padding: 15px 20px; } .nav-links { order: 3; width: 100%; justify-content: center; gap: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1); flex-wrap: wrap; } .pattern-strip { margin-top: 140px; } .main-content { padding: 20px; } .projects-grid { grid-template-columns: 1fr; } }
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
                <li><a href="projects.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>المشاريع</a></li>
                <li><a href="question_bank.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>بنك الأسئلة</a></li>
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
            <h1 class="page-title">إدارة المشاريع</h1>
            <a href="project_builder.php" class="btn btn-primary btn-sm"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-left:5px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>مشروع جديد</a>
        </div>

        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <p>لا توجد مشاريع بعد</p>
                <a href="project_builder.php" class="btn btn-primary">إنشاء مشروع جديد</a>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $project): ?>
                <div class="project-card">
                    <div class="project-header">
                        <h3 class="project-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                        <span class="badge <?php echo $project['is_active'] ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $project['is_active'] ? 'نشط' : 'متوقف'; ?>
                        </span>
                    </div>
                    <div class="project-stats">
                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-left:3px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg> <?php echo $project['total_questions']; ?> سؤال</span>
                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-left:3px;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> <?php echo $project['participants'] ?? 0; ?> مشارك</span>
                        <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;vertical-align:middle;margin-left:3px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> <?php echo $project['completed'] ?? 0; ?> مكتمل</span>
                    </div>
                    <div class="project-actions">
                        <a href="project_edit.php?id=<?php echo $project['id']; ?>" class="btn btn-outline">تعديل</a>
                        <button onclick="confirmDelete(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['title']); ?>')" class="btn btn-danger">حذف</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>تأكيد الحذف</h3>
            <p>هل أنت متأكد من حذف المشروع "<span id="projectName"></span>"؟</p>
            <div class="modal-actions">
                <button onclick="closeModal()" class="btn btn-outline">إلغاء</button>
                <button onclick="deleteProject()" class="btn btn-danger">حذف</button>
            </div>
        </div>
    </div>

    <footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>
    <script>
        let projectToDelete = null;
        window.addEventListener('scroll', () => document.getElementById('header').classList.toggle('scrolled', window.scrollY > 50));
        function confirmDelete(id, name) { projectToDelete = id; document.getElementById('projectName').textContent = name; document.getElementById('deleteModal').classList.add('active'); }
        function closeModal() { document.getElementById('deleteModal').classList.remove('active'); projectToDelete = null; }
        async function deleteProject() {
            if (!projectToDelete) return;
            try {
                const res = await fetch('<?php echo API_URL; ?>/admin/projects/delete.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({project_id: projectToDelete}) });
                const data = await res.json();
                if (data.success) { location.reload(); } else { alert(data.message); }
            } catch (e) { alert('حدث خطأ'); }
            closeModal();
        }
    </script>
</body>
</html>
