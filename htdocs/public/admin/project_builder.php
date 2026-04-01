<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();

/*
    ملاحظة تنظيمية:
    - تم إزالة جميع معالجات الأحداث inline (مثل onclick / onchange) لتفادي أخطاء "function is not defined"
    - تم ربط الأحداث بالكامل داخل JavaScript بعد DOMContentLoaded
    - تم إزالة الرموز التعبيرية من الرسائل داخل الواجهة
*/

/* تعريفات اللهجات */
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

/* جلب الأسئلة من بنك الأسئلة + إحصاءات الأنواع واللهجات */
$questions = [];
$typeCounts = [
    'multiple_choice' => 0,
    'true_false'      => 0,
    'open_ended'      => 0,
    'list_selection'  => 0
];
$dialectCounts = [];

try {
    $questions = fetchAll("SELECT * FROM qbank ORDER BY id DESC");
    foreach ($questions as $q) {
        $type = $q['question_type'] ?? 'multiple_choice';
        if (isset($typeCounts[$type])) {
            $typeCounts[$type]++;
        }

        $dialect = $q['dialect_type'] ?? 'General';
        if (!isset($dialectCounts[$dialect])) {
            $dialectCounts[$dialect] = 0;
        }
        $dialectCounts[$dialect]++;
    }
} catch (Exception $e) {
    /* تجاهل */
}

$typeLabels = [
    'multiple_choice' => 'اختيار متعدد',
    'true_false'      => 'صح / خطأ',
    'open_ended'      => 'إجابة مفتوحة',
    'list_selection'  => 'اختيار من قائمة'
];

$typeColors = [
    'multiple_choice' => '#9c2d5a',
    'true_false'      => '#1a5c4b',
    'open_ended'      => '#d97706',
    'list_selection'  => '#2563eb'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء مشروع جديد - الإدارة</title>
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
        .main-content { max-width: 1000px; margin: 0 auto; padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-family: 'Amiri', serif; font-size: 32px; color: var(--dark-green); }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 25px; border-radius: 10px; font-weight: 600; font-size: 14px; text-decoration: none; cursor: pointer; border: none; font-family: inherit; }
        .btn svg { width: 18px; height: 18px; }
        .btn-outline { background: transparent; border: 2px solid var(--primary-green); color: var(--primary-green); }
        .btn-outline:hover { background: var(--primary-green); color: white; }
        .btn-primary { background: var(--accent-magenta); color: white; }
        .btn-primary:hover { background: #b33d6a; }
        .btn-secondary { background: var(--primary-green); color: white; }
        .card { background: white; border-radius: 16px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .card-title { font-family: 'Amiri', serif; font-size: 20px; color: var(--dark-green); margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .card-title svg { width: 24px; height: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: var(--text-dark); }
        .form-input { width: 100%; padding: 14px 18px; border: 2px solid #e5e5e5; border-radius: 10px; font-size: 15px; font-family: inherit; transition: border-color 0.3s; }
        .form-input:focus { outline: none; border-color: var(--primary-green); }
        textarea.form-input { min-height: 100px; resize: vertical; }

        .random-section { background: var(--cream-light); border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .random-title { font-weight: 600; margin-bottom: 15px; color: var(--dark-green); }
        .type-inputs { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
        .type-input-group { display: flex; align-items: center; gap: 10px; padding: 12px; background: white; border-radius: 8px; border: 1px solid #e5e5e5; }
        .type-input-group label { flex: 1; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .type-badge { width: 12px; height: 12px; border-radius: 50%; }
        .type-input-group input[type="number"] { width: 70px; padding: 8px; border: 1px solid #ddd; border-radius: 6px; text-align: center; font-family: inherit; }
        .type-available { font-size: 12px; color: var(--text-gray); }
        .total-row { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--dark-green); color: white; border-radius: 10px; margin-top: 15px; }
        .total-row .total-label { font-weight: 600; }
        .total-row .total-value { font-size: 24px; font-weight: 800; }
        .generate-btn { width: 100%; margin-top: 15px; padding: 15px; }

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

        .selection-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 15px; }
        .selection-btn { padding: 8px 16px; border-radius: 8px; border: 1px solid #ddd; background: white; cursor: pointer; font-family: inherit; font-size: 13px; }
        .selection-btn:hover { border-color: var(--primary-green); color: var(--primary-green); }
        .selection-count { background: var(--cream-light); padding: 8px 16px; border-radius: 8px; font-size: 14px; }
        .questions-list { max-height: 400px; overflow-y: auto; border: 1px solid #e5e5e5; border-radius: 10px; }
        .question-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; }
        .question-item:hover { background: var(--cream-light); }
        .question-item:last-child { border-bottom: none; }
        .question-item.hidden { display: none; }
        .question-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary-green); flex-shrink: 0; }
        .question-text { flex: 1; font-size: 13px; }
        .question-badges { display: flex; gap: 5px; flex-shrink: 0; }
        .question-type { padding: 3px 10px; border-radius: 15px; font-size: 10px; font-weight: 600; color: white; }
        .type-multiple_choice { background: #9c2d5a; }
        .type-true_false { background: #1a5c4b; }
        .type-open_ended { background: #d97706; }
        .type-list_selection { background: #2563eb; }

        .badge-dialect { padding: 3px 8px; border-radius: 15px; font-size: 9px; font-weight: 600; color: white; }
        .badge-Central { background: #8b5cf6; }
        .badge-Western { background: #ec4899; }
        .badge-Eastern { background: #06b6d4; }
        .badge-Southern { background: #10b981; }
        .badge-Northern { background: #f59e0b; }
        .badge-General { background: #6b7280; }

        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { padding: 12px 25px; border: 2px solid #e5e5e5; background: white; border-radius: 10px; cursor: pointer; font-family: inherit; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .tab-btn svg { width: 18px; height: 18px; }
        .tab-btn.active { border-color: var(--accent-magenta); background: var(--accent-magenta); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-info { background: #dbeafe; color: #1e40af; }

        footer { background: var(--deep-green); color: white; text-align: center; padding: 25px; margin-top: 60px; }
        @media (max-width: 768px) {
            nav { flex-wrap: wrap; gap: 15px; }
            .nav-links { order: 3; width: 100%; justify-content: center; flex-wrap: wrap; gap: 15px; }
            .pattern-strip { margin-top: 140px; }
            .main-content { padding: 20px; }
            .type-inputs { grid-template-columns: 1fr; }
        }

        .settings-section { margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #eee; }
        .settings-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .settings-subtitle { font-size: 15px; font-weight: 600; color: var(--dark-green); margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .settings-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .checkbox-label { display: flex; align-items: center; gap: 10px; padding: 12px 15px; background: var(--cream-bg); border-radius: 8px; cursor: pointer; transition: all 0.2s; }
        .checkbox-label:hover { background: #f0ebe3; }
        .checkbox-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary-green); cursor: pointer; }
        .checkbox-label span { font-size: 14px; color: var(--text-dark); }
        .date-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-hint { display: block; font-size: 12px; color: var(--text-gray); margin-top: 5px; }
        @media (max-width: 768px) { .settings-grid, .date-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<header id="header">
    <nav>
        <a href="dashboard.php" class="nav-brand">
            <div class="nav-logo">
                <img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا">
            </div>
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
        <h1 class="page-title">إنشاء مشروع جديد</h1>
        <a href="projects.php" class="btn btn-outline">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            رجوع
        </a>
    </div>

    <div id="alertContainer"></div>

    <form id="projectForm">
        <div class="card">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                معلومات المشروع
            </h3>

            <div class="form-group">
                <label class="form-label">عنوان المشروع *</label>
                <input type="text" name="title" class="form-input" placeholder="أدخل عنوان المشروع" required>
            </div>

            <div class="form-group">
                <label class="form-label">الوصف</label>
                <textarea name="description" class="form-input" placeholder="وصف مختصر للمشروع"></textarea>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                اختيار الأسئلة
            </h3>

            <div class="tabs">
                <button type="button" class="tab-btn active" data-tab="random">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    اختيار عشوائي
                </button>
                <button type="button" class="tab-btn" data-tab="manual">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/></svg>
                    اختيار يدوي
                </button>
            </div>

            <div id="randomTab" class="tab-content active">
                <div class="random-section">
                    <div class="random-title">حدد عدد الأسئلة من كل نوع:</div>
                    <div class="type-inputs">
                        <div class="type-input-group">
                            <label><span class="type-badge" style="background: #9c2d5a;"></span> اختيار متعدد</label>
                            <input type="number" class="js-count-input" id="count_multiple_choice" min="0" max="<?php echo (int)$typeCounts['multiple_choice']; ?>" value="0">
                            <span class="type-available">/ <?php echo (int)$typeCounts['multiple_choice']; ?></span>
                        </div>
                        <div class="type-input-group">
                            <label><span class="type-badge" style="background: #1a5c4b;"></span> صح / خطأ</label>
                            <input type="number" class="js-count-input" id="count_true_false" min="0" max="<?php echo (int)$typeCounts['true_false']; ?>" value="0">
                            <span class="type-available">/ <?php echo (int)$typeCounts['true_false']; ?></span>
                        </div>
                        <div class="type-input-group">
                            <label><span class="type-badge" style="background: #d97706;"></span> إجابة مفتوحة</label>
                            <input type="number" class="js-count-input" id="count_open_ended" min="0" max="<?php echo (int)$typeCounts['open_ended']; ?>" value="0">
                            <span class="type-available">/ <?php echo (int)$typeCounts['open_ended']; ?></span>
                        </div>
                        <div class="type-input-group">
                            <label><span class="type-badge" style="background: #2563eb;"></span> اختيار من قائمة</label>
                            <input type="number" class="js-count-input" id="count_list_selection" min="0" max="<?php echo (int)$typeCounts['list_selection']; ?>" value="0">
                            <span class="type-available">/ <?php echo (int)$typeCounts['list_selection']; ?></span>
                        </div>
                    </div>

                    <div class="total-row">
                        <span class="total-label">إجمالي الأسئلة المحددة:</span>
                        <span class="total-value" id="totalQuestions">0</span>
                    </div>

                    <button type="button" class="btn btn-secondary generate-btn" id="btnGenerateRandom">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        توليد الأسئلة العشوائية
                    </button>
                </div>
            </div>

            <div id="manualTab" class="tab-content">
                <div class="filter-bar">
                    <div class="filter-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        فلترة حسب اللهجة
                    </div>

                    <div class="filter-buttons" id="dialectFilterButtons">
                        <?php foreach ($dialectFilters as $key => $label): ?>
                            <button
                                type="button"
                                class="filter-btn dialect-btn <?php echo $key !== 'all' ? 'dialect-' . $key : ''; ?> <?php echo $key === 'all' ? 'active' : ''; ?>"
                                data-dialect="<?php echo htmlspecialchars($key); ?>"
                            >
                                <?php echo htmlspecialchars($label); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="selection-controls">
                    <button type="button" class="selection-btn" id="btnSelectAll">تحديد الكل</button>
                    <button type="button" class="selection-btn" id="btnDeselectAll">إلغاء التحديد</button>
                    <span class="selection-count">تم تحديد <strong id="selectedCount">0</strong> سؤال من أصل <?php echo count($questions); ?></span>
                </div>

                <div class="questions-list" id="questionsList">
                    <?php if (empty($questions)): ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                            لا توجد أسئلة في بنك الأسئلة. <a href="question_bank.php">أضف أسئلة أولاً</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($questions as $q):
                            $dialect = $q['dialect_type'] ?? 'General';
                            $dialectLabel = $dialectLabels[$dialect] ?? $dialect;
                            $qType = $q['question_type'] ?? 'multiple_choice';
                        ?>
                            <div class="question-item" data-type="<?php echo htmlspecialchars($qType); ?>" data-dialect="<?php echo htmlspecialchars($dialect); ?>">
                                <input type="checkbox" name="questions[]" value="<?php echo (int)$q['id']; ?>">
                                <span class="question-text"><?php echo htmlspecialchars(mb_substr($q['question_text'], 0, 60)); ?>...</span>
                                <div class="question-badges">
                                    <span class="badge-dialect badge-<?php echo htmlspecialchars($dialect); ?>"><?php echo htmlspecialchars($dialectLabel); ?></span>
                                    <span class="question-type type-<?php echo htmlspecialchars($qType); ?>"><?php echo htmlspecialchars($typeLabels[$qType] ?? $qType); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="card-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                إعدادات المشروع
            </h3>

            <div class="settings-section">
                <h4 class="settings-subtitle">حالة المشروع والإعدادات الإضافية</h4>
                <div class="settings-grid">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <span>تفعيل المشروع (يظهر للمستخدمين)</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="show_results" value="1" checked>
                        <span>عرض النتائج للمستخدم بعد الإنتهاء</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="allow_retry" value="1">
                        <span>السماح بإعادة المحاولة بعد الإكمال</span>
                    </label>
                    <label class="checkbox-label">
                        <input type="checkbox" name="shuffle_questions" value="1">
                        <span>خلط ترتيب الأسئلة عشوائياً</span>
                    </label>
                </div>
            </div>

            <div class="settings-section">
                <h4 class="settings-subtitle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    الجدولة والمدة
                </h4>
                <div class="date-grid">
                    <div class="form-group">
                        <label class="form-label">تاريخ البدء</label>
                        <input type="datetime-local" name="start_date" class="form-input">
                        <small class="form-hint">اتركه فارغاً للبدء فوراً</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ الانتهاء</label>
                        <input type="datetime-local" name="end_date" class="form-input">
                        <small class="form-hint">اتركه فارغاً لعدم تحديد نهاية</small>
                    </div>
                </div>
            </div>

            <div class="settings-section">
                <h4 class="settings-subtitle">الحد الأقصى للمشاركين</h4>
                <div class="form-group" style="max-width: 300px;">
                    <input type="number" name="participant_limit" class="form-input" placeholder="مثال: 100" min="0">
                    <small class="form-hint">اتركه فارغاً لعدم تحديد حد</small>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 16px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            إنشاء المشروع
        </button>
    </form>
</div>

<footer><p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p></footer>

<script>
/*
    ملاحظة تقنية:
    - التوليد العشوائي هنا يعتمد على عناصر DOM (.question-item) مباشرة
    - لا يعتمد على questionsByType القادمة من PHP
    - هذا يحل مشكلة اختلاف قيم question_type في قاعدة البيانات
*/

let currentDialectFilter = 'all';

function qs(selector, root = document) {
    return root.querySelector(selector);
}
function qsa(selector, root = document) {
    return Array.from(root.querySelectorAll(selector));
}

/* تبديل التبويبات */
function switchTab(tab) {
    const tabButtons = qsa('.tab-btn[data-tab]');
    const tabContents = qsa('.tab-content');

    tabButtons.forEach(btn => btn.classList.remove('active'));
    tabContents.forEach(content => content.classList.remove('active'));

    const activeBtn = qs(`.tab-btn[data-tab="${tab}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    if (tab === 'random') {
        qs('#randomTab')?.classList.add('active');
    } else {
        qs('#manualTab')?.classList.add('active');
    }
}

/* تحديث إجمالي العدد المطلوب */
function updateTotal() {
    const mc = parseInt(qs('#count_multiple_choice')?.value || '0', 10) || 0;
    const tf = parseInt(qs('#count_true_false')?.value || '0', 10) || 0;
    const oe = parseInt(qs('#count_open_ended')?.value || '0', 10) || 0;
    const ls = parseInt(qs('#count_list_selection')?.value || '0', 10) || 0;

    const total = mc + tf + oe + ls;
    const totalEl = qs('#totalQuestions');
    if (totalEl) totalEl.textContent = String(total);
    return total;
}

/* تحديث عدد المحدد */
function updateCount() {
    const checked = qsa('input[name="questions[]"]:checked').length;
    const countEl = qs('#selectedCount');
    if (countEl) countEl.textContent = String(checked);
    return checked;
}

/* تحديد/إلغاء تحديد الكل */
function selectAll() {
    qsa('.question-item:not(.hidden) input[name="questions[]"]').forEach(cb => { cb.checked = true; });
    updateCount();
}
function deselectAll() {
    qsa('input[name="questions[]"]').forEach(cb => { cb.checked = false; });
    updateCount();
}

/* فلترة حسب اللهجة */
function filterByDialect(dialect, button) {
    currentDialectFilter = dialect;

    qsa('.filter-btn[data-dialect]').forEach(btn => btn.classList.remove('active'));
    if (button) button.classList.add('active');

    qsa('.question-item').forEach(item => {
        const itemDialect = item.dataset.dialect || 'General';
        if (dialect === 'all' || itemDialect === dialect) {
            item.classList.remove('hidden');
            item.style.display = 'flex';
        } else {
            item.classList.add('hidden');
            item.style.display = 'none';
        }
    });

    updateCount();
}

/*
    مهم:
    هذه الدالة تقرأ الأنواع من DOM نفسه:
    - data-type في .question-item
    وتطبعها إلى صيغة قياسية:
    multiple_choice / true_false / open_ended / list_selection
*/
function normalizeType(raw) {
    const t = String(raw || '').trim().toLowerCase();

    if (t === 'multiple_choice' || t === 'multiple-choice' || t === 'mcq' || t === 'multiple' || t === 'choice') return 'multiple_choice';
    if (t === 'true_false' || t === 'true-false' || t === 'tf' || t === 'boolean') return 'true_false';
    if (t === 'open_ended' || t === 'open-ended' || t === 'text' || t === 'essay') return 'open_ended';
    if (t === 'list_selection' || t === 'list-selection' || t === 'list' || t === 'select') return 'list_selection';

    /* إن كان نوع غير معروف، نرجعه كما هو (لن يدخل في العدادات) */
    return t;
}

/* توليد عشوائي من DOM */
function generateRandom() {
    deselectAll();

    const counts = {
        multiple_choice: parseInt(qs('#count_multiple_choice')?.value || '0', 10) || 0,
        true_false: parseInt(qs('#count_true_false')?.value || '0', 10) || 0,
        open_ended: parseInt(qs('#count_open_ended')?.value || '0', 10) || 0,
        list_selection: parseInt(qs('#count_list_selection')?.value || '0', 10) || 0
    };

    const totalRequested = counts.multiple_choice + counts.true_false + counts.open_ended + counts.list_selection;
    if (totalRequested === 0) {
        window.alert('يرجى تحديد عدد الأسئلة المطلوبة من كل نوع');
        return;
    }

    /* بناء Pools من عناصر الصفحة نفسها */
    const pools = {
        multiple_choice: [],
        true_false: [],
        open_ended: [],
        list_selection: []
    };

    qsa('.question-item').forEach(item => {
        /* تجاهل المخفي بسبب فلترة لهجة */
        if (item.classList.contains('hidden')) return;

        const rawType = item.dataset.type || '';
        const type = normalizeType(rawType);

        const cb = item.querySelector('input[name="questions[]"]');
        if (!cb) return;

        if (pools[type]) {
            pools[type].push(cb);
        }
    });

    let totalSelected = 0;

    Object.entries(counts).forEach(([type, want]) => {
        if (want <= 0) return;

        const list = pools[type] || [];
        if (list.length === 0) return;

        const shuffled = [...list].sort(() => 0.5 - Math.random());
        const take = Math.min(want, shuffled.length);

        for (let i = 0; i < take; i++) {
            shuffled[i].checked = true;
            totalSelected++;
        }
    });

    updateCount();
    switchTab('manual');

    const alertContainer = qs('#alertContainer');
    if (totalSelected > 0) {
        if (alertContainer) {
            alertContainer.innerHTML = '<div class="alert alert-success">تم تحديد ' + totalSelected + ' سؤال عشوائياً</div>';
            window.setTimeout(() => { alertContainer.innerHTML = ''; }, 3000);
        }
        /* إنزال المستخدم للقائمة ليشوف التحديد */
        qs('#questionsList')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
        if (alertContainer) {
            alertContainer.innerHTML = '<div class="alert alert-error">لم يتم تحديد أي سؤال. تحقق من أنواع الأسئلة أو الفلاتر.</div>';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    /* تبويبات */
    qsa('.tab-btn[data-tab]').forEach(btn => {
        btn.addEventListener('click', function() {
            const tab = btn.getAttribute('data-tab');
            switchTab(tab);
        });
    });

    /* زر التوليد */
    qs('#btnGenerateRandom')?.addEventListener('click', generateRandom);

    /* فلتر اللهجات */
    qsa('.filter-btn[data-dialect]').forEach(btn => {
        btn.addEventListener('click', function() {
            const dialect = btn.getAttribute('data-dialect') || 'all';
            filterByDialect(dialect, btn);
        });
    });

    /* تحديد/إلغاء تحديد الكل */
    qs('#btnSelectAll')?.addEventListener('click', selectAll);
    qs('#btnDeselectAll')?.addEventListener('click', deselectAll);

    /* تحديث الإجمالي */
    qsa('.js-count-input').forEach(input => {
        input.addEventListener('input', updateTotal);
        input.addEventListener('change', updateTotal);
    });

    /* تحديث العد عند تغيير checkbox */
    qs('#questionsList')?.addEventListener('change', function(e) {
        if (e.target && e.target.matches('input[name="questions[]"]')) {
            updateCount();
        }
    });

    /* إرسال الفورم */
    const projectForm = qs('#projectForm');
    if (projectForm) {
        projectForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const alertContainer = qs('#alertContainer');
            const formData = new FormData(projectForm);

            const selected = qsa('input[name="questions[]"]:checked');
            if (selected.length === 0) {
                if (alertContainer) {
                    alertContainer.innerHTML = '<div class="alert alert-error">يرجى اختيار سؤال واحد على الأقل</div>';
                }
                switchTab('manual');
                qs('#questionsList')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            if (alertContainer) {
                alertContainer.innerHTML = '<div class="alert alert-info">جاري إنشاء المشروع...</div>';
            }

            try {
                const response = await fetch('<?php echo API_URL; ?>/admin/projects/create.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data && data.success) {
                    if (alertContainer) {
                        alertContainer.innerHTML = '<div class="alert alert-success">' + (data.message || 'تم إنشاء المشروع بنجاح') + '</div>';
                    }
                    window.setTimeout(() => { window.location.href = 'projects.php'; }, 1500);
                } else {
                    if (alertContainer) {
                        alertContainer.innerHTML = '<div class="alert alert-error">' + (data.message || 'حدث خطأ أثناء إنشاء المشروع') + '</div>';
                    }
                }
            } catch (error) {
                console.error('Create Project Error:', error);
                if (alertContainer) {
                    alertContainer.innerHTML = '<div class="alert alert-error">حدث خطأ في الاتصال</div>';
                }
            }
        });
    }

    updateCount();
    updateTotal();
});
</script>
</body>
</html>
