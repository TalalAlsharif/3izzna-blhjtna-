<?php
/**
 * ملف الاتصال بقاعدة البيانات
 * Data Annotation Platform - Database Connection
 */

define('DB_HOST', 'sql213.infinityfree.com');
define('DB_NAME', 'if0_40676010_data_annotation_platform');
define('DB_USER', 'if0_40676010');
define('DB_PASS', 'Web123KSA'); /* ضع كلمة المرور هنا */
define('DB_CHARSET', 'utf8mb4');

/**
 * تحديد هل الطلب API حتى نرجع JSON نظيف عند فشل الاتصال
 */
function isApiRequest() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return (strpos($uri, '/api/') !== false) || (strpos($script, '/api/') !== false) || (stripos($accept, 'application/json') !== false);
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

} catch (Throwable $e) {
    error_log("DB Connection Failed: " . $e->getMessage());

    /* لا نرمي Exception حتى لا ينكسر API قبل try/catch */
    $pdo = null;

    /* لو API: رجّع JSON نظيف بدل HTML/Warnings */
    if (isApiRequest()) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'فشل الاتصال بقاعدة البيانات',
            'data' => [
                'debug' => [
                    'message' => 'DB connection failed (check logs)'
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/* دوال قاعدة بيانات مساعدة */
function executeQuery($sql, $params = []) {
    global $pdo;

    if (!$pdo) {
        throw new Exception("No database connection");
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}
