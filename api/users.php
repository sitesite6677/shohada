<?php
/**
 * ==============================================================================
 * مدیریت کاربران: api/users.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if ($method === 'GET') {
    requireAdminAuth($pdo);

    if (!empty($id)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch();
        if (!$user) {
            sendResponse(['success' => false, 'message' => 'کاربر یافت نشد.'], 404);
        }
        $stmtPay = $pdo->prepare("SELECT * FROM payments WHERE phone = :phone ORDER BY created_at DESC");
        $stmtPay->execute([':phone' => $user['phone']]);
        $payments = $stmtPay->fetchAll();
        $user['payments'] = $payments;
        sendResponse(['success' => true, 'data' => $user]);
    }

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
    $anonymous = isset($_GET['anonymous']) ? trim($_GET['anonymous']) : 'all';
    $visibility = isset($_GET['visibility']) ? trim($_GET['visibility']) : 'all';
    $page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = max(1, isset($_GET['limit']) ? (int)$_GET['limit'] : 10);
    $offset = ($page - 1) * $limit;

    $sql = "SELECT * FROM users WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (name LIKE :s1 OR phone LIKE :s2)";
        $params[':s1'] = "%$search%";
        $params[':s2'] = "%$search%";
    }
    if ($status !== 'all') {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    if ($anonymous !== 'all') {
        $sql .= " AND is_anonymous = :anon";
        $params[':anon'] = ($anonymous === 'true' || $anonymous === '1' || $anonymous === 'anonymous') ? 1 : 0;
    }
    if ($visibility !== 'all') {
        $sql .= " AND is_public_visible = :vis";
        $params[':vis'] = ($visibility === 'true' || $visibility === '1' || $visibility === 'visible') ? 1 : 0;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM (" . $sql . ") AS t");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $dataStmt = $pdo->prepare($sql);
    $dataStmt->execute($params);
    $users = $dataStmt->fetchAll();

    // Stats
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $approvedUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'approved'")->fetchColumn();
    $pendingUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending_approval'")->fetchColumn();
    $activeParticipants = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE payments_count > 0")->fetchColumn();

    sendResponse([
        'success'    => true,
        'data'       => $users,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => ceil($total / $limit) ?: 1,
        'stats'      => [
            'total_users'         => $totalUsers,
            'approved_users'      => $approvedUsers,
            'pending_users'       => $pendingUsers,
            'active_participants' => $activeParticipants
        ]
    ]);
}

if ($method === 'POST') {
    $admin = requireAdminAuth($pdo);
    $input = getJsonInput();
    $targetId = !empty($id) ? $id : (isset($input['id']) ? trim($input['id']) : '');

    if (empty($targetId)) {
        sendResponse(['success' => false, 'message' => 'شناسه کاربر الزامی است.'], 400);
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $targetId]);
    $user = $stmt->fetch();
    if (!$user) {
        sendResponse(['success' => false, 'message' => 'کاربر یافت نشد.'], 404);
    }

    if ($action === 'approve') {
        $prevStatus = $user['status'];
        $pdo->prepare("UPDATE users SET status = 'approved', is_public_visible = 1 WHERE id = :id")->execute([':id' => $targetId]);
        $pdo->prepare("UPDATE payments SET is_approved = 1 WHERE phone = :phone")->execute([':phone' => $user['phone']]);

        // اعلان
        $notifId = 'notif-' . bin2hex(random_bytes(8));
        $undoData = json_encode([
            'type'        => 'user_approval',
            'user_id'     => $user['id'],
            'prev_status' => $prevStatus,
            'new_status'  => 'approved'
        ], JSON_UNESCAPED_UNICODE);

        $pdo->prepare("
            INSERT INTO notifications (id, title, description, type, is_read, reversible, undone, undo_data, created_at)
            VALUES (:id, :title, :desc, 'user_approval', 0, 1, 0, :undo, CURRENT_TIMESTAMP)
        ")->execute([
            ':id'    => $notifId,
            ':title' => "تأیید کاربر: {$user['name']}",
            ':desc'  => "کاربر «{$user['name']}» تأیید شد و مشارکت او در وب‌سایت عمومی نمایش داده می‌شود.",
            ':undo'  => $undoData
        ]);

        sendResponse(['success' => true, 'message' => 'کاربر با موفقیت تأیید شد.']);
    }

    if ($action === 'reject') {
        $prevStatus = $user['status'];
        $pdo->prepare("UPDATE users SET status = 'rejected', is_public_visible = 0 WHERE id = :id")->execute([':id' => $targetId]);
        $pdo->prepare("UPDATE payments SET is_approved = 0 WHERE phone = :phone")->execute([':phone' => $user['phone']]);

        $notifId = 'notif-' . bin2hex(random_bytes(8));
        $undoData = json_encode([
            'type'        => 'user_approval',
            'user_id'     => $user['id'],
            'prev_status' => $prevStatus,
            'new_status'  => 'rejected'
        ], JSON_UNESCAPED_UNICODE);

        $pdo->prepare("
            INSERT INTO notifications (id, title, description, type, is_read, reversible, undone, undo_data, created_at)
            VALUES (:id, :title, :desc, 'user_approval', 0, 1, 0, :undo, CURRENT_TIMESTAMP)
        ")->execute([
            ':id'    => $notifId,
            ':title' => "رد کاربر: {$user['name']}",
            ':desc'  => "وضعیت کاربر «{$user['name']}» به رد شده تغییر یافت.",
            ':undo'  => $undoData
        ]);

        sendResponse(['success' => true, 'message' => 'وضعیت کاربر به رد شده تغییر یافت.']);
    }

    if ($action === 'toggle_visibility') {
        $newVis = (int)$user['is_public_visible'] ? 0 : 1;
        $pdo->prepare("UPDATE users SET is_public_visible = :vis WHERE id = :id")->execute([':vis' => $newVis, ':id' => $targetId]);
        $pdo->prepare("UPDATE payments SET is_approved = :vis WHERE phone = :phone")->execute([':vis' => $newVis, ':phone' => $user['phone']]);

        sendResponse(['success' => true, 'message' => 'وضعیت نمایش عمومی بروزرسانی شد.', 'is_public_visible' => (bool)$newVis]);
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $targetId]);
        sendResponse(['success' => true, 'message' => 'کاربر با موفقیت حذف شد.']);
    }

    sendResponse(['success' => false, 'message' => 'عملیات نامعتبر است.'], 400);
}
