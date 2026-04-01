<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();

if (!isset($_GET['id'])) { header('Location: projects.php'); exit; }

$projectId = (int)$_GET['id'];

try {
    $project = fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if (!$project) { header('Location: projects.php?error=not_found'); exit; }
    
    $result = fetchAll("SELECT * FROM qbank ORDER BY id DESC");
    $allQuestions = is_array($result) ? $result : [];
    
    $pqResult = fetchAll("SELECT question_id FROM project_questions WHERE project_id = ? ORDER BY question_order ASC", [$projectId]);
    $projectQuestions = is_array($pqResult) ? $pqResult : [];
    $selectedQuestionIds = array_column($projectQuestions, 'question_id');
} catch (Exception $e) {
    header('Location: projects.php?error=system_error'); exit;
}

$currentPage = 'projects';
$typeLabels = ['multiple_choice' => 'اختيار متعدد', 'true_false' => 'صح/خطأ', 'open_ended' => 'مفتوح', 'list_selection' => 'قائمة'];

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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المشروع - الإدارة</title>
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
        .main-content { max-width: 900px; margin: 0 auto; padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-family: 'Amiri', serif; font-size: 32px; color: var(--dark-green); }
        .btn { display: inline-block; padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; cursor: pointer; border: none; font-family: inherit; }
        .btn-primary { background: var(--accent-magenta); color: white; }
        .btn-primary:disabled { background: #ccc; cursor: not-allowed; }
        .btn-outline { background: transparent; border: 2px solid var(--primary-green); color: var(--primary-green); }
        .btn-outline:hover { background: var(--primary-green); color: white; }
        .btn-sm { padding: 8px 15px; font-size: 13px; }
        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-title { font-family: 'Amiri', serif; font-size: 22px; color: var(--dark-green); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--dark-green); }
        .form-input { width: 100%; padding: 12px 15px; border: 2px solid #e5e5e5; border-radius: 10px; font-size: 15px; font-family: inherit; }
        .form-input:focus { outline: none; border-color: var(--primary-green); }
        .form-checkbox { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .form-checkbox input { width: 20px; height: 20px; accent-color: var(--primary-green); }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 15px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .selection-toolbar { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .selection-info { margin-right: auto; color: var(--text-gray); }
        .questions-container { max-height: 400px; overflow-y: auto; border: 1px solid #e5e5e5; border-radius: 10px; }
        .question-item { display: flex; align-items: flex-start; gap: 15px; padding: 15px; border-bottom: 1px solid #f0f0f0; }
        .question-item:hover { background: var(--cream-light); }
        .question-checkbox { width: 20px; height: 20px; accent-color: var(--primary-green); margin-top: 5px; flex-shrink: 0; }
        .question-content { flex: 1; cursor: pointer; }
        .question-text { font-size: 14px; margin-bottom: 5px; }
        .badge { padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; background: #f3e8ff; color: #7c3aed; }
        .badge-dialect { padding: 3px 8px; border-radius: 15px; font-size: 10px; font-weight: 600; color: white; margin-right: 5px; }
        .badge-Central { background: #8b5cf6; }
        .badge-Western { background: #ec4899; }
        .badge-Eastern { background: #06b6d4; }
        .badge-Southern { background: #10b981; }
        .badge-Northern { background: #f59e0b; }
        .badge-General { background: #6b7280; }
        .question-badges { display: flex; gap: 5px; flex-wrap: wrap; }
        .question-item.hidden { display: none; }
        .filter-bar { background: var(--cream-light); border-radius: 10px; padding: 15px; margin-bottom: 15px; }
        .filter-label { font-weight: 600; font-size: 13px; color: var(--dark-green); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .filter-label svg { width: 16px; height: 16px; }
        .filter-buttons { display: flex; flex-wrap: wrap; gap: 6px; }
        .filter-btn { padding: 6px 12px; border-radius: 6px; border: 1px solid #ddd; background: white; cursor: pointer; font-family: inherit; font-size: 12px; font-weight: 600; transition: all 0.3s; }
        .filter-btn:hover { border-color: var(--accent-magenta); color: var(--accent-magenta); }
        .filter-btn.active { background: var(--accent-magenta); color: white; border-color: var(--accent-magenta); }
        .filter-btn.dialect-btn { position: relative; padding-right: 22px; }
        .filter-btn.dialect-btn::before { content: ''; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 8px; height: 8px; border-radius: 50%; }
        .dialect-Central::before { background: #8b5cf6; }
        .dialect-Western::before { background: #ec4899; }
        .dialect-Eastern::before { background: #06b6d4; }
        .dialect-Southern::before { background: #10b981; }
        .dialect-Northern::before { background: #f59e0b; }
        .dialect-General::before { background: #6b7280; }
        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid white; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-left: 8px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 768px) { nav { flex-wrap: wrap; gap: 15px; } .nav-links { order: 3; width: 100%; justify-content: center; gap: 15px; flex-wrap: wrap; } .pattern-strip { margin-top: 140px; } .main-content { padding: 20px; } }
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
            <h1 class="page-title">تعديل المشروع</h1>
            <a href="projects.php" class="btn btn-outline">رجوع</a>
        </div>

        <div id="error-container"></div>
        <div id="success-container"></div>

        <form id="projectForm">
            <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
            
            <div class="card">
                <h3 class="card-title">معلومات المشروع</h3>
                <div class="form-group">
                    <label class="form-label" for="title">عنوان المشروع *</label>
                    <input type="text" id="title" name="title" class="form-input" value="<?php echo htmlspecialchars($project['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="description">الوصف</label>
                    <textarea id="description" name="description" class="form-input" rows="3"><?php echo htmlspecialchars($project['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">حالة المشروع</label>
                    <div class="form-checkbox">
                        <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo $project['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active">تفعيل المشروع (يظهر للمستخدمين)</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">إعدادات إضافية</label>
                    <div class="form-checkbox">
                        <input type="checkbox" id="allow_retake" name="allow_retake" value="1" <?php echo ($project['allow_retake'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="allow_retake">السماح بإعادة المحاولة بعد الإكمال</label>
                    </div>
                    <div class="form-checkbox">
                        <input type="checkbox" id="show_results" name="show_results" value="1" <?php echo ($project['show_results'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="show_results">عرض النتائج للمستخدم بعد الانتهاء</label>
                    </div>
                    <div class="form-checkbox">
                        <input type="checkbox" id="shuffle_questions" name="shuffle_questions" value="1" <?php echo ($project['shuffle_questions'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="shuffle_questions">خلط ترتيب الأسئلة عشوائياً</label>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">الأسئلة</h3>
                
                <!-- فلتر اللهجة -->
                <div class="filter-bar">
                    <div class="filter-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        فلترة حسب اللهجة
                    </div>
                    <div class="filter-buttons">
                        <?php foreach ($dialectFilters as $key => $label): ?>
                        <button type="button" class="filter-btn dialect-btn <?php echo $key !== 'all' ? 'dialect-' . $key : ''; ?> <?php echo $key === 'all' ? 'active' : ''; ?>" onclick="filterByDialect('<?php echo $key; ?>', this)"><?php echo $label; ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="selection-toolbar">
                    <button type="button" onclick="selectAll()" class="btn btn-outline btn-sm">تحديد الكل</button>
                    <button type="button" onclick="deselectAll()" class="btn btn-outline btn-sm">إلغاء التحديد</button>
                    <div class="selection-info">تم تحديد <strong id="selectedCount"><?php echo count($selectedQuestionIds); ?></strong> سؤال</div>
                </div>
                <div class="questions-container">
                    <?php foreach ($allQuestions as $question): 
                        $isSelected = in_array($question['id'], $selectedQuestionIds);
                        $typeLabel = $typeLabels[$question['question_type']] ?? $question['question_type'];
                        $dialect = $question['dialect_type'] ?? 'General';
                        $dialectLabel = $dialectLabels[$dialect] ?? $dialect;
                    ?>
                    <div class="question-item" data-dialect="<?php echo $dialect; ?>">
                        <input type="checkbox" name="questions[]" value="<?php echo $question['id']; ?>" class="question-checkbox" id="q_<?php echo $question['id']; ?>" <?php echo $isSelected ? 'checked' : ''; ?>>
                        <label for="q_<?php echo $question['id']; ?>" class="question-content">
                            <div class="question-text"><?php echo htmlspecialchars(mb_substr($question['question_text'], 0, 80)); ?>...</div>
                            <div class="question-badges">
                                <span class="badge-dialect badge-<?php echo $dialect; ?>"><?php echo $dialectLabel; ?></span>
                                <span class="badge"><?php echo $typeLabel; ?></span>
                            </div>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card" style="text-align: center;">
                <button type="submit" class="btn btn-primary" id="submitBtn" style="padding: 15px 50px; font-size: 16px;">حفظ التغييرات</button>
            </div>
        </form>
    </div>

    <footer><p>© <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>

    <script>
        function filterByDialect(dialect, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            document.querySelectorAll('.question-item').forEach(item => {
                if (dialect === 'all' || item.dataset.dialect === dialect) {
                    item.classList.remove('hidden');
                } else {
                    item.classList.add('hidden');
                }
            });
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            document.getElementById('selectedCount').textContent = document.querySelectorAll('.question-checkbox:checked').length;
        }
        function selectAll() { document.querySelectorAll('.question-item:not(.hidden) .question-checkbox').forEach(cb => cb.checked = true); updateSelectedCount(); }
        function deselectAll() { document.querySelectorAll('.question-checkbox').forEach(cb => cb.checked = false); updateSelectedCount(); }
        document.querySelectorAll('.question-checkbox').forEach(cb => cb.addEventListener('change', updateSelectedCount));

        document.getElementById('projectForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const errorContainer = document.getElementById('error-container');
            const successContainer = document.getElementById('success-container');
            
            errorContainer.innerHTML = '';
            successContainer.innerHTML = '';
            
            if (document.querySelectorAll('.question-checkbox:checked').length === 0) {
                errorContainer.innerHTML = '<div class="alert alert-error">يرجى اختيار سؤال واحد على الأقل</div>';
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = 'جاري الحفظ... <span class="spinner"></span>';
            
            try {
                const response = await fetch('<?php echo API_URL; ?>/admin/projects/update.php', { method: 'POST', body: new FormData(this) });
                const data = await response.json();
                
                if (data.success) {
                    successContainer.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                    setTimeout(() => window.location.href = 'projects.php?success=updated', 1500);
                } else {
                    errorContainer.innerHTML = '<div class="alert alert-error">' + data.message + '</div>';
                    btn.disabled = false;
                    btn.innerHTML = 'حفظ التغييرات';
                }
            } catch (error) {
                errorContainer.innerHTML = '<div class="alert alert-error">حدث خطأ. يرجى المحاولة مرة أخرى.</div>';
                btn.disabled = false;
                btn.innerHTML = 'حفظ التغييرات';
            }
        });
    </script>
</body>
</html>
