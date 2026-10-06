<?php
/**
 * ==============================================================================
 * مدیریت تراکنش‌ها و واریزی‌ها (Payments API) : api/payments.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if ($method === 'GET') {
    if ($action === 'track') {
        $query = isset($_GET['query']) ? trim($_GET['query']) : '';
        if (empty($query)) {
            sendResponse(['success' => true, 'data' => []]);
        }
        $stmt = $pdo->prepare("
            SELECT id, campaign_id, payer_name, phone, shares, amount, tracking_code, description, status, transaction_id, created_at, verified_at
            FROM payments
            WHERE tracking_code LIKE :q1 OR phone LIKE :q2
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([
            ':q1' => '%' . $query . '%',
            ':q2' => '%' . $query . '%'
        ]);
        $rows = $stmt->fetchAll();
        sendResponse(['success' => true, 'data' => $rows]);
    }

    $campaignId = isset($_GET['campaign_id']) ? trim($_GET['campaign_id']) : '';

    $isAdmin = false;
    $token = getBearerToken();
    if ($token) {
        $authStmt = $pdo->prepare("SELECT id FROM admins WHERE token = :token LIMIT 1");
        $authStmt->execute([':token' => $token]);
        if ($authStmt->fetch()) {
            $isAdmin = true;
        }
    }

    if (!$isAdmin) {
        $sql = "SELECT id, payer_name, is_anonymous, shares, amount, created_at FROM payments WHERE status IN ('successful', 'success') AND (is_approved = 1 OR is_approved IS NULL)";
        $params = [];
        if (!empty($campaignId)) {
            $sql .= " AND campaign_id = :cid";
            $params[':cid'] = $campaignId;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            if (!empty($r['is_anonymous']) || empty(trim($r['payer_name'])) || $r['payer_name'] === 'گمنام' || $r['payer_name'] === 'ناشناس') {
                $r['payer_name'] = 'گمنام';
            }
            unset($r['phone']);
        }
        sendResponse(['success' => true, 'data' => $rows]);
    }

    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
    $nameFilter = isset($_GET['name']) ? trim($_GET['name']) : '';
    $trackingFilter = isset($_GET['tracking']) ? trim($_GET['tracking']) : '';
    $phoneFilter = isset($_GET['phone']) ? trim($_GET['phone']) : '';

    $where = ["1=1"];
    $params = [];

    if (!empty($campaignId)) {
        $where[] = "p.campaign_id = :cid";
        $params[':cid'] = $campaignId;
    }
    if ($statusFilter !== 'all' && !empty($statusFilter)) {
        if ($statusFilter === 'successful' || $statusFilter === 'success') {
            $where[] = "p.status IN ('successful', 'success')";
        } else {
            $where[] = "p.status = :status";
            $params[':status'] = $statusFilter;
        }
    }
    if (!empty($nameFilter)) {
        $where[] = "p.payer_name LIKE :name";
        $params[':name'] = '%' . $nameFilter . '%';
    }
    if (!empty($trackingFilter)) {
        $where[] = "p.tracking_code LIKE :tracking";
        $params[':tracking'] = '%' . $trackingFilter . '%';
    }
    if (!empty($phoneFilter)) {
        $where[] = "p.phone LIKE :phone";
        $params[':phone'] = '%' . $phoneFilter . '%';
    }

    $sql = "
        SELECT p.*, c.title AS campaign_title 
        FROM payments p 
        LEFT JOIN campaigns c ON p.campaign_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    sendResponse(['success' => true, 'data' => $payments]);
}

if ($method === 'POST') {
    $input = getJsonInput();

    if ($action === 'delete' || (isset($input['action']) && $input['action'] === 'delete')) {
        requireAdminAuth($pdo);
        $deleteId = !empty($id) ? $id : (isset($input['id']) ? trim($input['id']) : '');
        if (empty($deleteId)) {
            sendError('شناسه پرداخت الزامی است.', 400);
        }
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = :id");
        $stmt->execute([':id' => $deleteId]);
        sendResponse(['success' => true, 'message' => 'پرداخت حذف شد.']);
    }

    if ($action === 'update' || (!empty($id) && empty($action)) || (isset($input['action']) && $input['action'] === 'update')) {
        requireAdminAuth($pdo);
        $updateId = !empty($id) ? $id : (isset($input['id']) ? trim($input['id']) : '');
        if (empty($updateId)) {
            sendError('شناسه پرداخت الزامی است.', 400);
        }
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $updateId]);
        $currentPay = $stmt->fetch();
        if (!$currentPay) {
            sendError('پرداخت یافت نشد.', 404);
        }

        $newStatus = isset($input['status']) ? trim($input['status']) : $currentPay['status'];
        $newPayer = isset($input['payer_name']) ? trim($input['payer_name']) : $currentPay['payer_name'];
        if (in_array($newStatus, ['successful', 'success']) && (empty($newPayer) || $newPayer === 'مشارکت‌کننده ناشناس')) {
            $newPayer = 'ناشناس';
        }

        $verifiedAt = $currentPay['verified_at'];
        $paidAt = $currentPay['paid_at'];
        if (in_array($newStatus, ['successful', 'success']) && empty($verifiedAt)) {
            $verifiedAt = date('Y-m-d H:i:s');
            $paidAt = date('Y-m-d H:i:s');
        }

        $upStmt = $pdo->prepare("
            UPDATE payments SET
                campaign_id     = :campaign_id,
                payer_name      = :payer_name,
                phone           = :phone,
                shares          = :shares,
                amount          = :amount,
                tracking_code   = :tracking_code,
                description     = :description,
                status          = :status,
                verified_at     = :verified_at,
                paid_at         = :paid_at
            WHERE id = :id
        ");
        $upStmt->execute([
            ':campaign_id'   => isset($input['campaign_id']) ? trim($input['campaign_id']) : $currentPay['campaign_id'],
            ':payer_name'    => $newPayer,
            ':phone'         => isset($input['phone']) ? trim($input['phone']) : $currentPay['phone'],
            ':shares'        => isset($input['shares']) ? (int)$input['shares'] : (int)$currentPay['shares'],
            ':amount'        => isset($input['amount']) ? (int)$input['amount'] : (int)$currentPay['amount'],
            ':tracking_code' => isset($input['tracking_code']) ? trim($input['tracking_code']) : $currentPay['tracking_code'],
            ':description'   => isset($input['description']) ? trim($input['description']) : $currentPay['description'],
            ':status'        => $newStatus,
            ':verified_at'   => $verifiedAt,
            ':paid_at'       => $paidAt,
            ':id'            => $updateId
        ]);

        sendResponse(['success' => true, 'message' => 'پرداخت با موفقیت ویرایش شد.']);
    }

    $campaignId = isset($input['campaign_id']) ? trim($input['campaign_id']) : '';
    if (empty($campaignId)) {
        sendError('شناسه پویش الزامی است.', 400);
    }

    $payerName = isset($input['payer_name']) ? trim($input['payer_name']) : '';
    if (empty($payerName) || $payerName === 'مشارکت‌کننده ناشناس') {
        $payerName = 'ناشناس';
    }

    $phone = isset($input['phone']) ? trim($input['phone']) : '';
    $shares = !empty($input['shares']) ? max(1, (int)$input['shares']) : 1;
    $amount = !empty($input['amount']) ? (int)$input['amount'] : 0;
    $description = isset($input['description']) ? trim($input['description']) : '';
    $status = !empty($input['status']) ? trim($input['status']) : 'pending';
    $gateway = !empty($input['gateway']) ? trim($input['gateway']) : 'test_gateway';
    $trackingCode = !empty($input['tracking_code']) ? trim($input['tracking_code']) : ('POY-' . mt_rand(100000, 999999));
    $newId = 'pay-' . bin2hex(random_bytes(8));

    $verifiedAt = null;
    $paidAt = null;
    if (in_array($status, ['successful', 'success'])) {
        $verifiedAt = date('Y-m-d H:i:s');
        $paidAt = date('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare("
        INSERT INTO payments (
            id, campaign_id, payer_name, phone, shares, amount,
            tracking_code, description, status, gateway, verified_at, paid_at
        ) VALUES (
            :id, :campaign_id, :payer_name, :phone, :shares, :amount,
            :tracking_code, :description, :status, :gateway, :verified_at, :paid_at
        )
    ");
    $stmt->execute([
        ':id'            => $newId,
        ':campaign_id'   => $campaignId,
        ':payer_name'    => $payerName,
        ':phone'         => $phone,
        ':shares'        => $shares,
        ':amount'        => $amount,
        ':tracking_code' => $trackingCode,
        ':description'   => $description,
        ':status'        => $status,
        ':gateway'       => $gateway,
        ':verified_at'   => $verifiedAt,
        ':paid_at'       => $paidAt
    ]);

    $getStmt = $pdo->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
    $getStmt->execute([':id' => $newId]);
    $createdPayment = $getStmt->fetch();

    sendResponse([
        'success'       => true,
        'message'       => 'پرداخت با موفقیت ثبت شد.',
        'tracking_code' => $trackingCode,
        'data'          => $createdPayment
    ], 201);
}

if ($method === 'DELETE') {
    requireAdminAuth($pdo);
    if (empty($id)) {
        sendError('شناسه پرداخت الزامی است.', 400);
    }
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = :id");
    $stmt->execute([':id' => $id]);
    sendResponse(['success' => true, 'message' => 'پرداخت با موفقیت حذف گردید.']);
}

sendError('متد مجاز نیست.', 405);
