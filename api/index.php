<?php
/**
 * ==============================================================================
 * روتینگ مرکزی API (Front Controller Router) : api/index.php
 * ==============================================================================
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^/api/?#i', '', $uri);
$uri = trim($uri, '/');
$parts = explode('/', $uri);
$endpoint = isset($parts[0]) ? strtolower($parts[0]) : '';

switch ($endpoint) {
    case 'campaigns':
    case 'campaigns.php':
        require __DIR__ . '/campaigns.php';
        break;

    case 'payments':
    case 'payments.php':
        require __DIR__ . '/payments.php';
        break;

    case 'auth':
    case 'auth.php':
        require __DIR__ . '/auth.php';
        break;

    case 'settings':
    case 'settings.php':
        require __DIR__ . '/settings.php';
        break;

    case 'notifications':
    case 'notifications.php':
        require __DIR__ . '/notifications.php';
        break;

    case 'upload':
    case 'upload.php':
        require __DIR__ . '/upload.php';
        break;

    case 'payment':
        $sub = isset($parts[1]) ? strtolower($parts[1]) : '';
        if ($sub === 'initiate') {
            require __DIR__ . '/payment-initiate.php';
        } elseif ($sub === 'verify') {
            require __DIR__ . '/payment-verify.php';
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'اندپوینت یافت نشد.']);
        }
        break;

    default:
        require_once __DIR__ . '/db.php';
        sendResponse([
            'status'  => 'online',
            'system'  => 'Campaign Management PHP API',
            'version' => '2.0.0',
            'db'      => 'MySQL PDO'
        ]);
        break;
}
