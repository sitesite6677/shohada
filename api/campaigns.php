<?php
/**
 * ==============================================================================
 * مدیریت پویش‌ها (Campaigns API) : api/campaigns.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

function attachCampaignStats($pdo, $campaign) {
    if (!$campaign) return null;
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(shares), 0) AS paid_shares,
            COALESCE(SUM(amount), 0) AS collected_amount,
            COUNT(id) AS total_payments_count,
            COUNT(DISTINCT NULLIF(TRIM(phone), '')) AS participants_count
        FROM payments 
        WHERE campaign_id = :cid AND status IN ('successful', 'success')
    ");
    $stmt->execute([':cid' => $campaign['id']]);
    $stats = $stmt->fetch();

    $totalShares = (int)$campaign['total_shares'];
    $sharePrice = (int)$campaign['share_price'];
    $targetAmount = $totalShares * $sharePrice;
    $paidShares = (int)$stats['paid_shares'];
    $collectedAmount = (int)$stats['collected_amount'];
    $remainingShares = max(0, $totalShares - $paidShares);
    $remainingAmount = max(0, $targetAmount - $collectedAmount);
    let: $progress = $totalShares > 0 ? round(($paidShares / $totalShares) * 100, 1) : 0;
    if ($progress > 100) $progress = 100;

    $campaign['target_amount'] = $targetAmount;
    $campaign['collected_amount'] = $collectedAmount;
    $campaign['paid_shares'] = $paidShares;
    $campaign['remaining_shares'] = $remainingShares;
    $campaign['remaining_amount'] = $remainingAmount;
    $campaign['progress'] = $progress;
    $campaign['total_payments_count'] = (int)$stats['total_payments_count'];
    $campaign['participants_count'] = (int)$stats['participants_count'];
    return $campaign;
}

if ($method === 'GET') {
    if ($action === 'active') {
        $preferredId = isset($_GET['preferred_id']) ? trim($_GET['preferred_id']) : '';
        $campaign = null;
        if (!empty($preferredId)) {
            $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $preferredId]);
            $campaign = $stmt->fetch();
        }
        if (!$campaign) {
            $stmt = $pdo->query("SELECT * FROM campaigns WHERE status = 'active' ORDER BY created_at DESC LIMIT 1");
            $campaign = $stmt->fetch();
        }
        if (!$campaign) {
            sendResponse(['success' => true, 'data' => null]);
        }
        sendResponse(['success' => true, 'data' => attachCampaignStats($pdo, $campaign)]);
    }

    if (!empty($id)) {
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $campaign = $stmt->fetch();
        if (!$campaign) {
            sendError('پویش یافت نشد.', 404);
        }
        sendResponse(['success' => true, 'data' => attachCampaignStats($pdo, $campaign)]);
    }

    $stmt = $pdo->query("SELECT * FROM campaigns ORDER BY created_at DESC");
    $campaigns = $stmt->fetchAll();
    $result = [];
    foreach ($campaigns as $camp) {
        $result[] = attachCampaignStats($pdo, $camp);
    }
    sendResponse(['success' => true, 'data' => $result]);
}

if ($method === 'POST') {
    requireAdminAuth($pdo);
    $input = getJsonInput();

    if ($action === 'delete' || (isset($input['action']) && $input['action'] === 'delete')) {
        $deleteId = !empty($id) ? $id : (isset($input['id']) ? trim($input['id']) : '');
        if (empty($deleteId)) {
            sendError('شناسه پویش الزامی است.', 400);
        }
        $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = :id");
        $stmt->execute([':id' => $deleteId]);
        sendResponse(['success' => true, 'message' => 'پویش با موفقیت حذف گردید.']);
    }

    if ($action === 'update' || (!empty($id) && empty($action)) || (isset($input['action']) && $input['action'] === 'update')) {
        $updateId = !empty($id) ? $id : (isset($input['id']) ? trim($input['id']) : '');
        if (empty($updateId)) {
            sendError('شناسه پویش الزامی است.', 400);
        }
        $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $updateId]);
        if (!$stmt->fetch()) {
            sendError('پویش یافت نشد.', 404);
        }

        $fields = [
            'title'            => isset($input['title']) ? trim($input['title']) : null,
            'description'      => isset($input['description']) ? trim($input['description']) : null,
            'image_url'        => isset($input['image_url']) ? trim($input['image_url']) : null,
            'total_shares'     => isset($input['total_shares']) ? (int)$input['total_shares'] : null,
            'share_price'      => isset($input['share_price']) ? (int)$input['share_price'] : null,
            'start_date'       => isset($input['start_date']) ? trim($input['start_date']) : null,
            'end_date'         => isset($input['end_date']) ? trim($input['end_date']) : null,
            'status'           => isset($input['status']) ? trim($input['status']) : null,
            'event_location'   => isset($input['event_location']) ? trim($input['event_location']) : null,
            'event_date'       => isset($input['event_date']) ? trim($input['event_date']) : null,
            'event_time'       => isset($input['event_time']) ? trim($input['event_time']) : null,
            'channel_link'     => isset($input['channel_link']) ? trim($input['channel_link']) : null,
            'social_link'      => isset($input['social_link']) ? trim($input['social_link']) : null,
            'contact_phone'    => isset($input['contact_phone']) ? trim($input['contact_phone']) : null,
            'additional_notes' => isset($input['additional_notes']) ? trim($input['additional_notes']) : null,
        ];

        $updateParts = [];
        $params = [':id' => $updateId];
        foreach ($fields as $col => $val) {
            if ($val !== null) {
                $updateParts[] = "`$col` = :$col";
                $params[":$col"] = $val;
            }
        }

        if (empty($updateParts)) {
            sendError('هیچ فیلدی برای بروزرسانی ارسال نشده است.', 400);
        }

        $sql = "UPDATE campaigns SET " . implode(', ', $updateParts) . " WHERE id = :id";
        $upStmt = $pdo->prepare($sql);
        $upStmt->execute($params);

        $getStmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id LIMIT 1");
        $getStmt->execute([':id' => $updateId]);
        $updatedCamp = $getStmt->fetch();

        sendResponse([
            'success' => true,
            'message' => 'پویش با موفقیت ویرایش شد.',
            'data'    => attachCampaignStats($pdo, $updatedCamp)
        ]);
    }

    $title = isset($input['title']) ? trim($input['title']) : '';
    if (empty($title)) {
        sendError('عنوان پویش الزامی است.', 400);
    }

    $newId = 'camp-' . bin2hex(random_bytes(8));
    $stmt = $pdo->prepare("
        INSERT INTO campaigns (
            id, title, description, image_url, total_shares, share_price,
            start_date, end_date, status, event_location, event_date, event_time,
            channel_link, social_link, contact_phone, additional_notes
        ) VALUES (
            :id, :title, :description, :image_url, :total_shares, :share_price,
            :start_date, :end_date, :status, :event_location, :event_date, :event_time,
            :channel_link, :social_link, :contact_phone, :additional_notes
        )
    ");
    $stmt->execute([
        ':id'               => $newId,
        ':title'            => $title,
        ':description'      => isset($input['description']) ? trim($input['description']) : '',
        ':image_url'        => isset($input['image_url']) ? trim($input['image_url']) : '',
        ':total_shares'     => !empty($input['total_shares']) ? (int)$input['total_shares'] : 100,
        ':share_price'      => !empty($input['share_price']) ? (int)$input['share_price'] : 50000,
        ':start_date'       => isset($input['start_date']) ? trim($input['start_date']) : '',
        ':end_date'         => isset($input['end_date']) ? trim($input['end_date']) : '',
        ':status'           => !empty($input['status']) ? trim($input['status']) : 'active',
        ':event_location'   => isset($input['event_location']) ? trim($input['event_location']) : '',
        ':event_date'       => isset($input['event_date']) ? trim($input['event_date']) : '',
        ':event_time'       => isset($input['event_time']) ? trim($input['event_time']) : '',
        ':channel_link'     => isset($input['channel_link']) ? trim($input['channel_link']) : '',
        ':social_link'      => isset($input['social_link']) ? trim($input['social_link']) : '',
        ':contact_phone'    => isset($input['contact_phone']) ? trim($input['contact_phone']) : '',
        ':additional_notes' => isset($input['additional_notes']) ? trim($input['additional_notes']) : ''
    ]);

    $getStmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = :id LIMIT 1");
    $getStmt->execute([':id' => $newId]);
    $createdCamp = $getStmt->fetch();

    sendResponse([
        'success' => true,
        'message' => 'پویش جدید ایجاد شد.',
        'data'    => attachCampaignStats($pdo, $createdCamp)
    ], 201);
}

if ($method === 'DELETE') {
    requireAdminAuth($pdo);
    if (empty($id)) {
        sendError('شناسه پویش الزامی است.', 400);
    }
    $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = :id");
    $stmt->execute([':id' => $id]);
    sendResponse(['success' => true, 'message' => 'پویش با موفقیت حذف گردید.']);
}

sendError('متد مجاز نیست.', 405);
