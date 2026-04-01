<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();
$currentPage = 'questions';

$uploadResult = null;

/*
    ملاحظة مهمة:
    هذا الملف يستورد أسئلة JSON إلى جدول qbank وفق البنية الحالية:
    - question_text
    - question_type (ENUM)
    - dialect_type
    - meta (JSON نصي)
    - created_by
*/

function extractOptionsAndCorrectAnswer($fullText) {
    $options = [];
    $correct = null;

    $lines = preg_split('/\R/u', (string)$fullText);

    foreach ($lines as $line) {
        $lineTrimmed = trim($line);
        if ($lineTrimmed === '') {
            continue;
        }

        if (mb_strpos($lineTrimmed, 'الإجابة الصحيحة') !== false) {
            $pos = mb_strpos($lineTrimmed, ':');
            if ($pos !== false) {
                $correct = trim(mb_substr($lineTrimmed, $pos + 1));
            } else {
                $correct = trim(str_replace('الإجابة الصحيحة', '', $lineTrimmed));
            }
            continue;
        }

        /*
            التقاط الخيارات الشائعة:
            - أ) ...
            - ب) ...
            - A) ...
            - B) ...
        */
        if (preg_match('/^([A-Za-zأ-ي])\)?\s*[)\.\-]\s+/u', $lineTrimmed)) {
            $options[] = $lineTrimmed;
        }
    }

    return [$options, $correct];
}

function getAllowedQuestionTypes(PDO $pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM qbank LIKE 'question_type'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$col || empty($col['Type'])) {
        return ['open_ended', 'multiple_choice', 'true_false'];
    }

    /*
        Type مثال:
        enum('open_ended','multiple_choice','true_false','list_selection')
    */
    $type = $col['Type'];
    if (preg_match("/^enum\((.*)\)$/i", $type, $m)) {
        $inside = $m[1];
        $parts = array_map(function($v) {
            return trim($v, " '\"");
        }, explode(',', $inside));

        $parts = array_filter($parts);
        return array_values($parts);
    }

    return ['open_ended', 'multiple_choice', 'true_false'];
}

// معالجة رفع ملف JSON مباشرة من هذه الصفحة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadResult = [
            'success' => false,
            'message' => 'الرجاء اختيار ملف JSON صحيح.'
        ];
    } else {

        $fileTmpPath = $_FILES['json_file']['tmp_name'];
        $jsonContent = file_get_contents($fileTmpPath);

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $uploadResult = [
                'success' => false,
                'message' => 'تنسيق JSON غير صالح، تأكد من أن الملف يبدأ بـ [ وينتهي بـ ].'
            ];
        } else {

            try {
                // التأكد من اتصال قاعدة البيانات
                if (!isset($pdo) || !($pdo instanceof PDO)) {
                    throw new Exception('تعذر الاتصال بقاعدة البيانات.');
                }

                // التأكد من وجود معرف المستخدم في الجلسة
                if (!isset($_SESSION['user_id'])) {
                    throw new Exception('تعذر تحديد المستخدم الحالي.');
                }

                $createdBy = (int)$_SESSION['user_id'];

                // استخراج أنواع question_type المسموحة فعلياً من الجدول
                $allowedTypes = getAllowedQuestionTypes($pdo);

                // ✅ التوزيع المحدّث: تقليل open_ended
                $questionFieldsMap = [
                    'Location_Recognition_question'    => 'multiple_choice',  // اختيار متعدد
                    'Cultural_Interpretation_question' => 'list_selection',   // اختيار من قائمة
                    'Contextual_Usage_question'        => 'multiple_choice',  // اختيار متعدد
                    'Fill_in_Blank_question'           => 'list_selection',   // اختيار من قائمة
                    'True_False_question'              => 'true_false',       // صح/خطأ
                    'Meaning_question'                 => 'multiple_choice',  // اختيار متعدد
                ];

                // تجهيز أمر الإدخال وفق سكيمـا qbank
                $insertSql = "
                    INSERT INTO qbank
                        (question_text, question_type, dialect_type, meta, created_by)
                    VALUES
                        (:question_text, :question_type, :dialect_type, :meta, :created_by)
                ";
                $stmt = $pdo->prepare($insertSql);

                $insertedCount = 0;

                foreach ($data as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $term    = isset($item['Term']) ? trim($item['Term']) : '';
                    $meaning = isset($item['Meaning_of_term']) ? trim($item['Meaning_of_term']) : '';
                    $dialect = isset($item['Dialect type']) ? trim($item['Dialect type']) : '';

                    // لا نجعل dialect شرطاً صارماً لأن العمود يسمح بـ NULL
                    if ($term === '' && $meaning === '') {
                        // إذا كان العنصر لا يحتوي أي معلومات أساسية نتجاوزه
                        continue;
                    }

                    foreach ($questionFieldsMap as $fieldName => $mappedType) {
                        if (empty($item[$fieldName])) {
                            continue;
                        }

                        // ضمان أن النوع موجود فعلاً في ENUM
                        $questionType = in_array($mappedType, $allowedTypes, true)
                            ? $mappedType
                            : (in_array('multiple_choice', $allowedTypes, true) ? 'multiple_choice' : $allowedTypes[0]);

                        $fullText = (string)$item[$fieldName];

                        // استخراج الخيارات والإجابة الصحيحة من نص السؤال نفسه
                        [$options, $correctAnswer] = extractOptionsAndCorrectAnswer($fullText);

                        // بناء meta كـ JSON نصي
                        $metaArr = [
                            'term'           => $term,
                            'meaning'        => $meaning,
                            'source_field'   => $fieldName,
                            'options'        => $options,
                            'correct_answer' => $correctAnswer,
                            'raw'            => $fullText
                        ];

                        $metaJson = json_encode($metaArr, JSON_UNESCAPED_UNICODE);

                        $stmt->execute([
                            ':question_text' => $fullText,
                            ':question_type' => $questionType,
                            ':dialect_type'  => ($dialect !== '' ? $dialect : null),
                            ':meta'          => $metaJson,
                            ':created_by'    => $createdBy,
                        ]);

                        $insertedCount++;
                    }
                }

                if ($insertedCount > 0) {
                    $uploadResult = [
                        'success' => true,
                        'message' => 'تم استيراد ' . $insertedCount . ' سؤالاً بنجاح إلى بنك الأسئلة.'
                    ];
                } else {
                    $uploadResult = [
                        'success' => false,
                        'message' => 'تم قراءة الملف لكن لم يتم العثور على أسئلة صالحة للتخزين.'
                    ];
                }

            } catch (PDOException $e) {
                $uploadResult = [
                    'success' => false,
                    'message' => 'خطأ قاعدة البيانات: ' . $e->getMessage()
                ];
            } catch (Exception $e) {
                $uploadResult = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد أسئلة - الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-green: #1a5c4b;
            --dark-green: #0d3d32;
            --deep-green: #0a2e26;
            --accent-magenta: #9c2d5a;
            --cream-bg: #f8f5f0;
            --cream-light: #fcfaf7;
            --text-dark: #1a1a1a;
            --text-gray: #5a5a5a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--cream-bg);
            color: var(--text-dark);
            line-height: 1.7;
            direction: rtl;
        }
        header {
            background: var(--cream-light);
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
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
            text-decoration: none;
            align-items: center;
            gap: 15px;
        }
        .nav-logo {
            width: 50px; height: 50px;
            border-radius: 10px;
            overflow: hidden;
        }
        .nav-logo img { width: 100%; height: 100%; object-fit: cover; }
        .nav-title {
            font-family: 'Amiri', serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--accent-magenta);
        }
        .nav-badge {
            background: var(--dark-green);
            color: white;
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 35px;
            list-style: none;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links a svg { width: 18px; height: 18px; }
        .nav-links a:hover, .nav-links a.active { color: var(--accent-magenta); }
        .nav-user { display: flex; align-items: center; gap: 15px; }
        .user-name { font-weight: 600; }
        .btn-logout {
            background: transparent;
            border: 2px solid var(--accent-magenta);
            color: var(--accent-magenta);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
        }
        .btn-logout:hover { background: var(--accent-magenta); color: white; }
        .pattern-strip {
            height: 45px;
            background-color: var(--deep-green);
            background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png');
            background-repeat: repeat-x;
            background-size: auto 100%;
            margin-top: 80px;
        }
        .main-content { max-width: 800px; margin: 0 auto; padding: 40px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-family: 'Amiri', serif; font-size: 32px; color: var(--dark-green); }
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            font-family: inherit;
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
        }
        .btn-outline:hover { background: var(--primary-green); color: white; }
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .card-title {
            font-family: 'Amiri', serif;
            font-size: 22px;
            color: var(--dark-green);
            margin-bottom: 20px;
            text-align: center;
        }
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 16px;
            padding: 50px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover { border-color: var(--accent-magenta); background: #fdf8fa; }
        .upload-icon {
            width: 70px; height: 70px;
            background: var(--dark-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .upload-icon svg { width: 35px; height: 35px; stroke: white; }
        .upload-text { color: var(--text-dark); font-size: 18px; margin-bottom: 10px; }
        .upload-hint { font-size: 14px; color: #999; }
        #fileInput { display: none; }
        .result-box {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            font-size: 15px;
        }
        .result-success { background: #dcfce7; color: #166534; }
        .result-error { background: #fee2e2; color: #dc2626; }
        footer {
            background: var(--deep-green);
            color: white;
            text-align: center;
            padding: 25px;
            margin-top: 60px;
        }
        @media (max-width: 768px) {
            nav { flex-wrap: wrap; gap: 15px; }
            .nav-links {
                order: 3;
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
                gap: 15px;
            }
            .pattern-strip { margin-top: 140px; }
            .main-content { padding: 20px; }
        }
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
            <li><a href="dashboard.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>الرئيسية</a></li>
            <li><a href="projects.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>المشاريع</a></li>
            <li><a href="question_bank.php" class="active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>بنك الأسئلة</a></li>
            <li><a href="users.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                </svg>المستخدمين</a></li>
            <li><a href="statistics.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>الإحصائيات</a></li>
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
        <h1 class="page-title">استيراد أسئلة</h1>
        <a href="question_bank.php" class="btn btn-outline">رجوع</a>
    </div>

    <div class="card">
        <h3 class="card-title">رفع ملف JSON</h3>

        <form id="uploadForm" method="post" enctype="multipart/form-data">
            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                </div>
                <p class="upload-text">اضغط هنا أو اسحب ملف JSON</p>
                <p class="upload-hint">الحد الأقصى: 10 ميجابايت</p>
            </div>

            <input type="file" id="fileInput" name="json_file" accept=".json">
        </form>

        <?php if ($uploadResult !== null): ?>
            <div class="result-box <?php echo $uploadResult['success'] ? 'result-success' : 'result-error'; ?>">
                <?php echo htmlspecialchars($uploadResult['message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- قسم شرح الصيغة المطلوبة -->
        <div style="margin-top: 30px; padding: 25px; background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 12px;">
            <h3 style="color: #0c4a6e; margin-bottom: 15px; font-size: 18px;">📝 صيغة ملف الاستيراد المطلوب</h3>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                <h4 style="color: #334155; margin-bottom: 10px; font-size: 16px;">الهيكل الأساسي:</h4>
                <pre style="background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; direction: ltr; text-align: left;"><code>[
  {
    "Term": "المصطلح",
    "Meaning_of_term": "معنى المصطلح",
    "Dialect type": "General",
    "Location_Recognition_question": "نص السؤال",
    "Cultural_Interpretation_question": "نص السؤال",
    "Contextual_Usage_question": "نص السؤال",
    "Fill_in_Blank_question": "نص السؤال",
    "True_False_question": "نص السؤال",
    "Meaning_question": "نص السؤال"
  }
]</code></pre>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                <h4 style="color: #334155; margin-bottom: 10px; font-size: 16px;">مثال عملي:</h4>
                <pre style="background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; direction: ltr; text-align: left;"><code>[
  {
    "Term": "أبكّ",
    "Meaning_of_term": "كلمة لجذب الانتباه",
    "Dialect type": "Central",
    "Location_Recognition_question": "حدد المدينة التي تستخدم فيها كلمة 'أبكّ'\nأ) الرياض\nب) جدة\nج) الدمام\nالإجابة الصحيحة: الرياض",
    "Meaning_question": "ما معنى 'أبكّ'؟\nأ) جذب الانتباه\nب) التحية\nج) الاستفهام\nالإجابة الصحيحة: جذب الانتباه"
  }
]</code></pre>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px;">
                <h4 style="color: #334155; margin-bottom: 10px; font-size: 16px;">📌 ملاحظات مهمة:</h4>
                <ul style="margin: 0; padding-right: 20px; line-height: 1.8; color: #475569;">
                    <li><strong>الملف يجب أن يبدأ بـ <code>[</code> وينتهي بـ <code>]</code></strong></li>
                    <li><strong>اللهجات المدعومة:</strong> Central, Western, Eastern, Southern, Northern, General</li>
                    <li><strong>توزيع أنواع الأسئلة:</strong>
                        <ul style="margin-top: 5px;">
                            <li>Location_Recognition_question → اختيار متعدد (50%)</li>
                            <li>Cultural_Interpretation_question → اختيار من قائمة (33%)</li>
                            <li>Contextual_Usage_question → اختيار متعدد (50%)</li>
                            <li>Fill_in_Blank_question → اختيار من قائمة (33%)</li>
                            <li>True_False_question → صح/خطأ (17%)</li>
                            <li>Meaning_question → اختيار متعدد (50%)</li>
                        </ul>
                    </li>
                    <li><strong>صيغة الخيارات:</strong> استخدم أ)، ب)، ج) أو A)، B)، C)</li>
                    <li><strong>الإجابة الصحيحة:</strong> يجب كتابة "الإجابة الصحيحة: " متبوعاً بالإجابة</li>
                    <li>الملف يجب أن يكون بصيغة UTF-8</li>
                    <li>الحد الأقصى لحجم الملف: 10 ميجابايت</li>
                </ul>
            </div>
            
            <!-- زر تحميل ملف مثال -->
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="downloadSampleJSON()" style="background: #0ea5e9; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                    📥 تحميل ملف JSON نموذجي
                </button>
            </div>
        </div>
    </div>
</div>

<footer>
    <p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p>
</footer>

<script>
    const uploadArea = document.getElementById('uploadArea');
    const fileInput  = document.getElementById('fileInput');
    const uploadForm = document.getElementById('uploadForm');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function (eventName) {
        uploadArea.addEventListener(eventName, function (e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    uploadArea.addEventListener('dragover', function () {
        uploadArea.style.borderColor = 'var(--primary-green)';
    });

    uploadArea.addEventListener('dragleave', function () {
        uploadArea.style.borderColor = '#ccc';
    });

    uploadArea.addEventListener('click', function () {
        fileInput.click();
    });

    uploadArea.addEventListener('drop', function (e) {
        uploadArea.style.borderColor = '#ccc';
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            uploadForm.submit();
        }
    });

    fileInput.addEventListener('change', function () {
        if (fileInput.files.length > 0) {
            uploadForm.submit();
        }
    });
    
    // دالة لتحميل ملف JSON نموذجي
    function downloadSampleJSON() {
        const sampleData = [
            {
                "Term": "أبكّ",
                "Meaning_of_term": "كلمة تستخدم لجذب الانتباه أو التنبيه",
                "Dialect type": "Central",
                "Location_Recognition_question": "حدد المدينة التي تستخدم فيها كلمة 'أبكّ' بشكل شائع:\nأ) الرياض\nب) جدة\nج) الدمام\nد) الأحساء\nالإجابة الصحيحة: الرياض",
                "Cultural_Interpretation_question": "ما التفسير الثقافي لكلمة 'أبكّ'؟\nأ) تستخدم عادة لجذب الانتباه\nب) للدلالة على الشجاعة\nج) تشير للتعاطف\nد) تعبر عن الحزن\nالإجابة الصحيحة: تستخدم عادة لجذب الانتباه",
                "Contextual_Usage_question": "اختر الجملة التي تستخدم فيها 'أبكّ' بشكل صحيح:\nأ) أبكّ انت خلك قريب\nب) أبكّ أنا أريد الذهاب\nج) أبكّ الجو جميل اليوم\nد) كلنا نحب القهوة\nالإجابة الصحيحة: أبكّ انت خلك قريب",
                "Fill_in_Blank_question": "املأ الفراغ: _____ يا محمد تعال هنا\nأ) أبكّ\nب) مرحبا\nج) هلا\nد) تفضل\nالإجابة الصحيحة: أبكّ",
                "True_False_question": "كلمة 'أبكّ' تستخدم للتنبيه وجذب الانتباه\nأ) صح\nب) خطأ\nالإجابة الصحيحة: صح",
                "Meaning_question": "حدد المعنى الصحيح لكلمة 'أبكّ':\nأ) تستخدم لجلب الإنتباه لجملة تتلوها\nب) تعني الاستحسان\nج) تستخدم للدلالة على النشاط\nد) تشير إلى المكانة الاجتماعية\nالإجابة الصحيحة: تستخدم لجلب الإنتباه لجملة تتلوها"
            },
            {
                "Term": "يا هلا",
                "Meaning_of_term": "عبارة ترحيبية",
                "Dialect type": "General",
                "Location_Recognition_question": "في أي منطقة تُستخدم عبارة 'يا هلا' بشكل واسع؟\nأ) جميع مناطق المملكة\nب) الرياض فقط\nج) المنطقة الشرقية فقط\nد) المنطقة الغربية فقط\nالإجابة الصحيحة: جميع مناطق المملكة",
                "Cultural_Interpretation_question": "ما الدلالة الثقافية لعبارة 'يا هلا'؟\nأ) التعبير عن الترحيب والسرور\nب) التعبير عن الاستغراب\nج) طلب المساعدة\nد) الرفض المهذب\nالإجابة الصحيحة: التعبير عن الترحيب والسرور",
                "Contextual_Usage_question": "متى تستخدم عبارة 'يا هلا'؟\nأ) عند استقبال الضيوف\nب) عند الوداع\nج) عند الاعتذار\nد) عند الشكر\nالإجابة الصحيحة: عند استقبال الضيوف",
                "Fill_in_Blank_question": "_____ بك يا صديقي\nأ) يا هلا\nب) مع السلامة\nج) شكراً\nد) آسف\nالإجابة الصحيحة: يا هلا",
                "True_False_question": "عبارة 'يا هلا' تُستخدم للترحيب\nأ) صح\nب) خطأ\nالإجابة الصحيحة: صح",
                "Meaning_question": "ما معنى 'يا هلا'؟\nأ) أهلاً وسهلاً\nب) وداعاً\nج) شكراً\nد) عذراً\nالإجابة الصحيحة: أهلاً وسهلاً"
            }
        ];
        
        // تحويل البيانات إلى JSON
        const jsonString = JSON.stringify(sampleData, null, 2);
        
        // إنشاء blob وتحميله
        const blob = new Blob([jsonString], { type: 'application/json;charset=utf-8' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'sample-questions.json';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }
</script>
</body>
</html>