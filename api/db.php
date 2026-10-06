<?php
/**
 * ==============================================================================
 * مدیریت اتصال PDO به MySQL و توابع کمکی API: api/db.php
 * ==============================================================================
 */

require_once __DIR__ . '/config.php';

// هدرهای استاندارد JSON و CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// هندل درخواست‌های OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * دریافت اتصال PDO به MySQL
 * @return PDO
 */
function getDBConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'خطا در اتصال به پایگاه‌داده MySQL: ' . $e->getMessage(),
                'hint'    => 'لطفاً اطلاعات دیتابیس را در فایل api/config.php بررسی نمایید.'
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
    }
    return $pdo;
}

/**
 * دریافت داده‌های ارسالی JSON
 * @return array
 */
function getJsonInput() {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $statusCode = 400, $extra = []) {
    http_response_code($statusCode);
    $response = array_merge([
        'success' => false,
        'message' => $message
    ], $extra);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function getBearerToken() {
    $headers = '';
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    if (!empty($headers) && preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
        return trim($matches[1]);
    }
    if (!empty($_GET['token'])) {
        return trim($_GET['token']);
    }
    return null;
}

function requireAdminAuth($pdo) {
    $token = getBearerToken();
    if (!$token) {
        sendError('دسترسی غیرمجاز. لطفاً مجدداً وارد شوید.', 401);
    }
    $stmt = $pdo->prepare("SELECT id, email, token_expiry FROM admins WHERE token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $admin = $stmt->fetch();
    if (!$admin) {
        sendError('توکن نشست نامعتبر است.', 401);
    }
    if (!empty($admin['token_expiry']) && strtotime($admin['token_expiry']) < time()) {
        sendError('نشست شما منقضی شده است. لطفاً دوباره وارد شوید.', 401);
    }
    return $admin;
}
