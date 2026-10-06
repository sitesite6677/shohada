<?php
/**
 * ==============================================================================
 * شروع فرآیند پرداخت و اتصال به درگاه: api/payment-initiate.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('صرفاً درخواست POST مجاز است.', 405);
}

$input = getJsonInput();
$amount       = isset($input['amount']) ? (int)$input['amount'] : 0;
$trackingCode = isset($input['tracking_code']) ? trim($input['tracking_code']) : '';
$callbackUrl  = isset($input['callback_url']) ? trim($input['callback_url']) : '/';
$gateway      = isset($input['gateway']) ? trim($input['gateway']) : 'test_gateway';
$sandbox      = isset($input['sandbox']) ? (bool)$input['sandbox'] : true;

if ($amount <= 0) {
    sendError('مبلغ پرداخت نامعتبر است.', 400);
}

if (empty($trackingCode)) {
    $trackingCode = 'POY-' . mt_rand(100000, 999999);
}

$gwStmt = $pdo->query("SELECT * FROM payment_settings WHERE id = 'default' LIMIT 1");
$gwConfig = $gwStmt->fetch();
if ($gwConfig && !$gwConfig['is_active']) {
    sendError('درگاه پرداخت در حال حاضر غیرفعال است.', 400);
}

$activeGateway = $gwConfig ? $gwConfig['active_gateway'] : $gateway;
$isSandbox = $gwConfig ? (bool)$gwConfig['sandbox'] : $sandbox;
$merchantId = $gwConfig ? $gwConfig['merchant_id'] : '';

$authority = 'AUTH_' . time() . '_' . mt_rand(1000, 9999);

// ثبت اولیه تراکنش در payments
$checkStmt = $pdo->prepare("SELECT id, status, payer_name FROM payments WHERE tracking_code = :track LIMIT 1");
$checkStmt->execute([':track' => $trackingCode]);
$existing = $checkStmt->fetch();

if ($existing) {
    $upStmt = $pdo->prepare("
        UPDATE payments SET 
            authority_token = :auth,
            gateway         = :gw,
            status          = 'pending'
        WHERE id = :id
    ");
    $upStmt->execute([
        ':auth' => $authority,
        ':gw'   => $activeGateway,
        ':id'   => $existing['id']
    ]);
} else {
    $campaignId = isset($input['campaign_id']) ? trim($input['campaign_id']) : '';
    $payerName  = isset($input['payer_name']) ? trim($input['payer_name']) : '';
    if (empty($payerName)) {
        sendError('لطفاً نام و نام خانوادگی خود را وارد کنید.', 400);
    }
    $isAnonymous = !empty($input['is_anonymous']) ? 1 : 0;
    $phone       = isset($input['phone']) ? trim($input['phone']) : '';
    $shares      = isset($input['shares']) ? max(1, (int)$input['shares']) : 1;
    $description = isset($input['description']) ? trim($input['description']) : '';

    $insStmt = $pdo->prepare("
        INSERT INTO payments (
            id, campaign_id, payer_name, phone, shares, amount,
            tracking_code, description, is_anonymous, is_approved, status, gateway, authority_token
        ) VALUES (
            :id, :cid, :payer, :phone, :shares, :amount,
            :track, :desc, :anon, 1, 'pending', :gw, :auth
        )
    ");
    $insStmt->execute([
        ':id'     => 'pay-' . bin2hex(random_bytes(8)),
        ':cid'    => $campaignId,
        ':payer'  => $payerName,
        ':phone'  => $phone,
        ':shares' => $shares,
        ':amount' => $amount,
        ':track'  => $trackingCode,
        ':desc'   => $description,
        ':anon'   => $isAnonymous,
        ':gw'     => $activeGateway,
        ':auth'   => $authority
    ]);
}

// شبیه‌ساز پرداخت محلی یا درگاه واقعی
$redirectUrl = '/gateway-sim.html?' . http_build_query([
    'track'     => $trackingCode,
    'amount'    => $amount,
    'authority' => $authority,
    'gateway'   => $activeGateway,
    'callback'  => $callbackUrl
]);

// در صورتی که زرین‌پال غیرسندباکس تنظیم شده باشد
if ($activeGateway === 'zarinpal' && !$isSandbox && !empty($merchantId)) {
    $zpData = [
        'merchant_id'  => $merchantId,
        'amount'       => $amount * 10, // تبدیل تومان به ریال
        'description'  => 'مشارکت در پویش نذورات کد ' . $trackingCode,
        'callback_url' => $callbackUrl . (strpos($callbackUrl, '?') !== false ? '&' : '?') . 'track=' . $trackingCode,
    ];
    $ch = curl_init('https://api.zarinpal.com/pg/v4/payment/request.json');
    curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($zpData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen(json_encode($zpData))
    ]);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if (!$err && $result) {
        $zpRes = json_decode($result, true);
        if (isset($zpRes['data']['code']) && $zpRes['data']['code'] == 100) {
            $authority = $zpRes['data']['authority'];
            $redirectUrl = 'https://www.zarinpal.com/pg/StartPay/' . $authority;
            
            $pdo->prepare("UPDATE payments SET authority_token = :auth WHERE tracking_code = :track")
                ->execute([':auth' => $authority, ':track' => $trackingCode]);
        }
    }
}

sendResponse([
    'success'      => true,
    'authority'    => $authority,
    'redirect_url' => $redirectUrl,
    'gateway'      => $activeGateway
]);
