<?php
/**
 * ==============================================================================
 * مدیریت وب‌سرویس اعلان‌ها و بازگردانی تغییرات: api/notifications.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$id = isset($_GET['id']) ? trim($_GET['id']) : '';

// بررسی وجود جدول notifications و ایجاد در صورت عدم وجود
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `notifications` (
        `id` VARCHAR(64) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `type` VARCHAR(64) NOT NULL DEFAULT 'info',
        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
        `reversible` TINYINT(1) NOT NULL DEFAULT 0,
        `undone` TINYINT(1) NOT NULL DEFAULT 0,
        `undo_data` JSON NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50");
    $list = $stmt->fetchAll();

    foreach ($list as &$item) {
        $item['is_read'] = (bool)$item['is_read'];
        $item['reversible'] = (bool)$item['reversible'];
        $item['undone'] = (bool)$item['undone'];
        if (!empty($item['undo_data']) && is_string($item['undo_data'])) {
            $item['undo_data'] = json_decode($item['undo_data'], true);
        }
    }

    $countStmt = $pdo->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
    $unreadRow = $countStmt->fetch();
    $unreadCount = $unreadRow ? (int)$unreadRow['unread'] : 0;

    sendResponse([
        'success' => true,
        'data' => $list,
        'unread_count' => $unreadCount
    ]);
}

if ($method === 'POST') {
    if ($action === 'read_all') {
        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        sendResponse(['success' => true, 'message' => 'همه اعلان‌ها خوانده شدند.']);
    }

    if ($action === 'read' && !empty($id)) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
        sendResponse(['success' => true]);
    }

    if ($action === 'undo' && !empty($id)) {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $notif = $stmt->fetch();

        if (!$notif) {
            sendError('اعلان مورد نظر یافت نشد.', 404);
        }

        if (empty($notif['reversible']) || empty($notif['undo_data'])) {
            sendError('این عملیات قابل بازگردانی نیست.', 400);
        }

        if (!empty($notif['undone'])) {
            sendError('این عملیات قبلاً لغو شده است.', 400);
        }

        $undoData = is_string($notif['undo_data']) ? json_decode($notif['undo_data'], true) : $notif['undo_data'];

        if ($undoData && isset($undoData['type'])) {
            if ($undoData['type'] === 'campaign_status' && isset($undoData['campaign_id'], $undoData['prev_status'])) {
                $up = $pdo->prepare("UPDATE campaigns SET status = :st WHERE id = :cid");
                $up->execute([
                    ':st'  => $undoData['prev_status'],
                    ':cid' => $undoData['campaign_id']
                ]);
            } else if ($undoData['type'] === 'user_approval' && isset($undoData['user_id'], $undoData['prev_status'])) {
                $up = $pdo->prepare("UPDATE users SET status = :st, is_public_visible = :vis WHERE id = :uid");
                $up->execute([
                    ':st'  => $undoData['prev_status'],
                    ':vis' => ($undoData['prev_status'] === 'approved') ? 1 : 0,
                    ':uid' => $undoData['user_id']
                ]);
            } else if ($undoData['type'] === 'terms_update' && isset($undoData['prev_content'])) {
                $up = $pdo->prepare("UPDATE payment_settings SET terms_content = :tc WHERE id = 'default'");
                $up->execute([':tc' => $undoData['prev_content']]);
            }
        }

        $newTitle = $notif['title'] . ' (لغو شد)';
        $upNotif = $pdo->prepare("UPDATE notifications SET undone = 1, is_read = 1, title = :title WHERE id = :id");
        $upNotif->execute([
            ':title' => $newTitle,
            ':id'    => $id
        ]);

        sendResponse([
            'success' => true,
            'message' => 'عملیات با موفقیت به حالت قبلی بازگردانده شد.'
        ]);
    }

    sendError('درخواست نامعتبر است.', 400);
}
