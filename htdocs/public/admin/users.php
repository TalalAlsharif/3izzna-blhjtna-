<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/auth_check.php';
require_once '../../includes/functions.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    
    // منع المستخدم من تعديل/حذف نفسه
    if ($userId === $currentUserId) {
        header('Location: users.php?error=self_action');
        exit;
    }
    
    // منع حذف/تعديل المستخدم إذا كان ID غير صالح
    if ($userId <= 0) {
        header('Location: users.php?error=invalid_user');
        exit;
    }
    
    try {
        if ($action === 'toggle_status') {
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$userId]);
            
        } elseif ($action === 'make_admin') {
            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND role = 'user'");
            $stmt->execute([$userId]);
            
        } elseif ($action === 'remove_admin') {
            $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ? AND role = 'admin'");
            $stmt->execute([$userId]);
            
        } elseif ($action === 'delete_user') {
            // بداية transaction
            $pdo->beginTransaction();
            
            try {
                // 1. حذف الإجابات (الجدول الصحيح: answers وليس user_answers)
                $stmt = $pdo->prepare("DELETE FROM answers WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // 2. حذف سجلات التقدم
                $stmt = $pdo->prepare("DELETE FROM user_project_progress WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // 3. حذف الجلسات
                $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // 4. حذف رموز التحقق من البريد (إذا كان الجدول موجود)
                try {
                    $stmt = $pdo->prepare("DELETE FROM email_verifications WHERE user_id = ?");
                    $stmt->execute([$userId]);
                } catch (PDOException $e) {
                    // تجاهل إذا الجدول غير موجود
                }
                
                // 5. حذف رموز إعادة تعيين كلمة المرور (إذا كان الجدول موجود)
                try {
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                    $stmt->execute([$userId]);
                } catch (PDOException $e) {
                    // تجاهل إذا الجدول غير موجود
                }
                
                // 6. أخيراً، حذف المستخدم نفسه
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                
                // إتمام transaction
                $pdo->commit();
                
                header('Location: users.php?success=user_deleted');
                exit;
                
            } catch (PDOException $e) {
                // التراجع عن transaction في حالة الخطأ
                $pdo->rollBack();
                
                // تسجيل الخطأ
                error_log('Delete User Error: ' . $e->getMessage());
                
                header('Location: users.php?error=delete_failed');
                exit;
            }
        }
        
    } catch (PDOException $e) {
        error_log('User Action Error: ' . $e->getMessage());
        header('Location: users.php?error=action_failed');
        exit;
    }
    
    header('Location: users.php?success=action_completed');
    exit;
}

try {
    // جلب جميع المستخدمين
    $users = fetchAll("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM user_project_progress WHERE user_id = u.id AND status = 'completed') as completed_projects,
            (SELECT COUNT(*) FROM answers WHERE user_id = u.id) as total_answers
        FROM users u 
        ORDER BY u.role DESC, u.created_at DESC
    ");
} catch (Exception $e) {
    $users = [];
    error_log('Fetch Users Error: ' . $e->getMessage());
}

$currentPage = 'users';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المستخدمين - الإدارة</title>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary-green: #1a5c4b; --dark-green: #0d3d32; --deep-green: #0a2e26; --accent-magenta: #9c2d5a; --cream-bg: #f8f5f0; --cream-light: #fcfaf7; --text-dark: #1a1a1a; --text-gray: #5a5a5a; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tajawal', sans-serif; background: var(--cream-bg); color: var(--text-dark); line-height: 1.7; direction: rtl; }
        
        header { background: var(--cream-light); border-bottom: 1px solid rgba(0,0,0,0.05); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        nav { max-width: 1300px; margin: 0 auto; padding: 15px 40px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { display: flex; text-decoration: none; align-items: center; gap: 15px; }
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
        .nav-user { display: flex; align-items: center; gap: 15px; }
        .user-name { font-weight: 600; }
        .btn-logout { background: transparent; border: 2px solid var(--accent-magenta); color: var(--accent-magenta); padding: 8px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.3s; }
        .btn-logout:hover { background: var(--accent-magenta); color: white; }
        
        .pattern-strip { height: 45px; background-color: var(--deep-green); background-image: url('<?php echo ASSETS_URL; ?>/images/pattern.png'); background-repeat: repeat-x; background-size: auto 100%; margin-top: 80px; }
        .main-content { max-width: 1300px; margin: 0 auto; padding: 40px; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-family: 'Amiri', serif; font-size: 32px; color: var(--dark-green); }
        .user-count { background: var(--accent-magenta); color: white; padding: 8px 20px; border-radius: 20px; font-weight: 600; }
        
        .card { background: white; border-radius: 16px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th, td { padding: 15px; text-align: right; border-bottom: 1px solid rgba(0,0,0,0.05); }
        th { background: var(--cream-bg); font-weight: 600; color: var(--dark-green); }
        tr:hover { background: var(--cream-light); }
        
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-error { background: #fee2e2; color: #dc2626; }
        .badge-primary { background: #f3e8ff; color: #7c3aed; }
        .badge-admin { background: #fef3c7; color: #92400e; }
        
        .btn { padding: 8px 15px; border-radius: 6px; font-weight: 600; font-size: 12px; border: none; cursor: pointer; margin: 2px; display: inline-flex; align-items: center; gap: 5px; transition: all 0.2s; }
        .btn svg { width: 14px; height: 14px; }
        .btn-danger { background: #fee2e2; color: #dc2626; }
        .btn-danger:hover { background: #fecaca; }
        .btn-success { background: #dcfce7; color: #166534; }
        .btn-success:hover { background: #bbf7d0; }
        .btn-warning { background: #fef3c7; color: #92400e; }
        .btn-warning:hover { background: #fde68a; }
        .btn-primary { background: #dbeafe; color: #1e40af; }
        .btn-primary:hover { background: #bfdbfe; }
        
        footer { background: var(--deep-green); color: white; text-align: center; padding: 20px; margin-top: 40px; }
        footer p { opacity: 0.85; font-size: 14px; }
    </style>
</head>
<body>

<header>
    <nav>
        <a href="dashboard.php" class="nav-brand">
            <div class="nav-logo">
                <img src="<?php echo ASSETS_URL; ?>/images/hero-logo.png" alt="عزنا بلهجتنا" onerror="this.style.display='none';">
            </div>
            <span class="nav-title">عِزّنا بلهجتنا<span class="nav-badge">أدمن</span></span>
        </a>

        <ul class="nav-links">
            <li><a href="dashboard.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>الرئيسية</a></li>

            <li><a href="users.php" class="active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>المستخدمين</a></li>

            <li><a href="projects.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>المشاريع</a></li>

            <li><a href="qbank.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>بنك الأسئلة</a></li>

            <li><a href="statistics.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"/>
                    <line x1="12" y1="20" x2="12" y2="4"/>
                    <line x1="6" y1="20" x2="6" y2="14"/>
                </svg>الإحصائيات</a></li>
        </ul>

        <div class="nav-user">
            <span class="user-name">مرحباً، <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'أدمن'); ?></span>
            <a href="../logout.php" class="btn-logout">تسجيل الخروج</a>
        </div>
    </nav>
</header>

<div class="pattern-strip"></div>

<div class="main-content">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <?php 
            $successMessages = [
                'user_deleted' => 'تم حذف المستخدم بنجاح',
                'action_completed' => 'تم تنفيذ العملية بنجاح'
            ];
            echo $successMessages[$_GET['success']] ?? 'تم بنجاح';
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <?php 
            $errorMessages = [
                'self_action' => 'لا يمكنك تعديل أو حذف حسابك الخاص',
                'invalid_user' => 'معرف المستخدم غير صالح',
                'delete_failed' => 'فشل حذف المستخدم - حاول مرة أخرى',
                'action_failed' => 'فشل تنفيذ العملية'
            ];
            echo $errorMessages[$_GET['error']] ?? 'حدث خطأ ما';
            ?>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1 class="page-title">إدارة المستخدمين</h1>
        <span class="user-count"><?php echo count($users); ?> مستخدم</span>
    </div>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>الاسم</th>
                    <th>البريد</th>
                    <th>الصلاحية</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>المشاريع</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px;">لا يوجد مستخدمين</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $index => $user): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge badge-admin">أدمن</span>
                                <?php else: ?>
                                    <span class="badge badge-primary">مستخدم</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge badge-success">نشط</span>
                                <?php else: ?>
                                    <span class="badge badge-error">معطل</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y/m/d', strtotime($user['created_at'])); ?></td>
                            <td><?php echo (int)$user['completed_projects']; ?></td>
                            <td>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <!-- تبديل الحالة -->
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد؟');">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn <?php echo $user['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                            <?php echo $user['is_active'] ? 'تعطيل' : 'تفعيل'; ?>
                                        </button>
                                    </form>

                                    <!-- تغيير الصلاحية -->
                                    <?php if ($user['role'] === 'user'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('منح صلاحيات أدمن؟');">
                                            <input type="hidden" name="action" value="make_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-primary">جعله أدمن</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('إزالة صلاحيات الأدمن؟');">
                                            <input type="hidden" name="action" value="remove_admin">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-warning">إزالة أدمن</button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- حذف المستخدم -->
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ تحذير: سيتم حذف جميع بيانات المستخدم نهائياً!\n\nهل أنت متأكد من حذف: <?php echo htmlspecialchars($user['full_name']); ?>؟');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                            حذف
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge-primary">حسابك</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<footer>
    <p>جميع الحقوق محفوظة © <?php echo date('Y'); ?> - عزنا بلهجتنا</p>
</footer>

</body>
</html>