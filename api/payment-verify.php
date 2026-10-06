<?php
/**
 * ==============================================================================
 * اعتبارسنجی و تایید تراکنش درگاه بانکی: api/payment-verify.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('صرفاً درخواست POST مجاز است.', 405);
}

$input = getJsonInput();
$trackingCode = isset($input['tracking_code']) ? trim($input['tracking_code']) : '';
$authority    = isset($input['authority']) ? trim($input['authority']) : '';
$statusParam  = isset($input['status_param']) ? strtoupper(trim($input['status_param'])) : '';
$refId        = isset($input['ref_id']) ? trim($input['ref_id']) : '';

if (empty($trackingCode)) {
    sendError('کد پیگیری الزامی است.', 400);
}

$stmt = $pdo->prepare("SELECT * FROM payments WHERE tracking_code = :track LIMIT 1");
$stmt->execute([':track' => $trackingCode]);
$payment = $stmt->fetch();

if (!$payment) {
    sendError('اطلاعات تراکنش یافت نشد.', 404);
}

// جلوگیری از اعتبارسنجی مجدد
if (in_array($payment['status'], ['successful', 'success'])) {
    sendResponse([
        'success'          => true,
        'status'           => 'successful',
        'already_verified' => true,
        'payment'          => $payment,
        'message'          => 'پرداخت قبلاً تایید گردیده است.'
    ]);
}

if ($statusParam === 'CANCELLED') {
    $upStmt = $pdo->prepare("UPDATE payments SET status = 'cancelled' WHERE id = :id");
    $upStmt->execute([':id' => $payment['id']]);
    $payment['status'] = 'cancelled';
    sendResponse([
        'success' => false,
        'status'  => 'cancelled',
        'payment' => $payment,
        'message' => 'پرداخت توسط کاربر لغو گردید.'
    ]);
}

if ($statusParam === 'NOK' || empty($statusParam)) {
    $upStmt = $pdo->prepare("UPDATE payments SET status = 'failed' WHERE id = :id");
    $upStmt->execute([':id' => $payment['id']]);
    $payment['status'] = 'failed';
    sendResponse([
        'success' => false,
        'status'  => 'failed',
        'payment' => $payment,
        'message' => 'تراکنش ناموفق بود.'
    ]);
}

if ($statusParam !== 'OK') {
    $upStmt = $pdo->prepare("UPDATE payments SET status = 'verification_failed' WHERE id = :id");
    $upStmt->execute([':id' => $payment['id']]);
    $payment['status'] = 'verification_failed';
    sendResponse([
        'success' => false,
        'status'  => 'verification_failed',
        'payment' => $payment,
        'message' => 'تراکنش توسط درگاه تایید نشد.'
    ]);
}

$transactionId = !empty($refId) ? $refId : ('TXN-' . mt_rand(10000000, 99999999));
$now = date('Y-m-d H:i:s');

$finalPayerName = trim($payment['payer_name']);
if (empty($finalPayerName) || $finalPayerName === 'مشارکت‌کننده ناشناس') {
    $finalPayerName = 'ناشناس';
}

$upSuccess = $pdo->prepare("
    UPDATE payments SET
        status         = 'successful',
        payer_name     = :payer,
        transaction_id = :txid,
        verified_at    = :now1,
        paid_at        = :now2
    WHERE id = :id
");
$upSuccess->execute([
    ':payer' => $finalPayerName,
    ':txid'  => $transactionId,
    ':now1'  => $now,
    ':now2'  => $now,
    ':id'    => $payment['id']
]);

$getStmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
$getStmt->execute([':id' => $payment['id']]);
$updatedPayment = $getStmt->fetch();

sendResponse([
    'success'        => true,
    'status'         => 'successful',
    'transaction_id' => $transactionId,
    'tracking_code'  => $trackingCode,
    'amount'         => (int)$updatedPayment['amount'],
    'verified_at'    => $now,
    'payment'        => $updatedPayment,
    'message'        => 'پرداخت با موفقیت تایید و سهم شما ثبت گردید.'
]);
