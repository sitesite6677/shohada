<?php
/**
 * ==============================================================================
 * وب‌سرویس احراز هویت مدیران: api/auth.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

// بررسی نشست
if ($method === 'GET' || $action === 'check') {
    $token = getBearerToken();
    if (!$token) {
        sendResponse(['authenticated' => false]);
    }

    $stmt = $pdo->prepare("SELECT id, email, token_expiry FROM admins WHERE token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $admin = $stmt->fetch();

    if (!$admin || (!empty($admin['token_expiry']) && strtotime($admin['token_expiry']) < time())) {
        sendResponse(['authenticated' => false]);
    }

    sendResponse([
        'authenticated' => true,
        'user' => [
            'id'    => (int)$admin['id'],
            'email' => $admin['email']
        ]
    ]);
}

// ورود یا خروج
if ($method === 'POST') {
    $input = getJsonInput();

    // خروج از حساب
    if ($action === 'logout' || (isset($input['action']) && $input['action'] === 'logout')) {
        $token = getBearerToken();
        if ($token) {
            $stmt = $pdo->prepare("UPDATE admins SET token = NULL, token_expiry = NULL WHERE token = :token");
            $stmt->execute([':token' => $token]);
        }
        sendResponse(['success' => true, 'message' => 'خروج با موفقیت انجام شد.']);
    }

    // ورود
    $email = isset($input['email']) ? trim($input['email']) : '';
    $password = isset($input['password']) ? trim($input['password']) : '';

    if (empty($email) || empty($password)) {
        sendError('ایمیل و رمز عبور الزامی است.', 400);
    }

    $stmt = $pdo->prepare("SELECT id, email, password FROM admins WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

    if (!$admin) {
        sendError('حساب کاربری یافت نشد یا اطلاعات نادرست است.', 401);
    }

    $passwordValid = false;
    if (password_verify($password, $admin['password'])) {
        $passwordValid = true;
    } elseif ($admin['password'] === hash('sha256', $password) || $admin['password'] === md5($password)) {
        $passwordValid = true;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upStmt = $pdo->prepare("UPDATE admins SET password = :hash WHERE id = :id");
        $upStmt->execute([':hash' => $newHash, ':id' => $admin['id']]);
    }

    if (!$passwordValid) {
        sendError('ایمیل یا رمز عبور اشتباه است.', 401);
    }

    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + (30 * 24 * 3600));

    $updateStmt = $pdo->prepare("UPDATE admins SET token = :token, token_expiry = :expiry WHERE id = :id");
    $updateStmt->execute([
        ':token'  => $token,
        ':expiry' => $expiry,
        ':id'     => $admin['id']
    ]);

    sendResponse([
        'success' => true,
        'message' => 'ورود با موفقیت انجام شد.',
        'token'   => $token,
        'user'    => [
            'id'    => (int)$admin['id'],
            'email' => $admin['email']
        ]
    ]);
}

sendError('متد مجاز نیست.', 405);
