<?php
/**
 * ==============================================================================
 * تنظیمات درگاه پرداخت: api/settings.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM payment_settings WHERE id = 'default' LIMIT 1");
    $settings = $stmt->fetch();
    if (!$settings) {
        $settings = [
            'id'             => 'default',
            'active_gateway' => 'test_gateway',
            'is_active'      => 1,
            'sandbox'        => 1,
            'merchant_id'    => '',
            'api_key'        => '',
            'terminal_id'    => ''
        ];
    } else {
        $settings['is_active'] = (bool)$settings['is_active'];
        $settings['sandbox']   = (bool)$settings['sandbox'];
    }
    sendResponse(['success' => true, 'data' => $settings]);
}

if ($method === 'POST') {
    requireAdminAuth($pdo);
    $input = getJsonInput();

    $activeGateway = isset($input['active_gateway']) ? trim($input['active_gateway']) : 'test_gateway';
    $isActive = isset($input['is_active']) ? ((bool)$input['is_active'] ? 1 : 0) : 1;
    $sandbox = isset($input['sandbox']) ? ((bool)$input['sandbox'] ? 1 : 0) : 1;
    $merchantId = isset($input['merchant_id']) ? trim($input['merchant_id']) : '';
    $apiKey = isset($input['api_key']) ? trim($input['api_key']) : '';
    $terminalId = isset($input['terminal_id']) ? trim($input['terminal_id']) : '';

    $stmt = $pdo->prepare("
        INSERT INTO payment_settings (id, active_gateway, is_active, sandbox, merchant_id, api_key, terminal_id)
        VALUES ('default', :gw, :active, :sb, :merchant, :key, :terminal)
        ON DUPLICATE KEY UPDATE
            active_gateway = VALUES(active_gateway),
            is_active      = VALUES(is_active),
            sandbox        = VALUES(sandbox),
            merchant_id    = VALUES(merchant_id),
            api_key        = VALUES(api_key),
            terminal_id    = VALUES(terminal_id),
            updated_at     = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':gw'       => $activeGateway,
        ':active'   => $isActive,
        ':sb'       => $sandbox,
        ':merchant' => $merchantId,
        ':key'      => $apiKey,
        ':terminal' => $terminalId
    ]);

    sendResponse([
        'success' => true,
        'message' => 'تنظیمات درگاه بانکی با موفقیت ذخیره شد.'
    ]);
}

sendError('متد مجاز نیست.', 405);
