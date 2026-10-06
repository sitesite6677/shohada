<?php
/**
 * ==============================================================================
 * مدیریت قوانین و مقررات: api/terms.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$defaultTerms = '<h4>مقدمه و اهداف پویش</h4>
<p>این سامانه جهت تسهیل در جمع‌آوری نذورات و مشارکت‌های مردمی به صورت شفاف، سهم‌بندی شده و دقیق راه‌اندازی شده است. تمامی مبالغ واریزی منحصراً صرف اهداف اعلام‌شده در عنوان و توضیحات پویش می‌گردد.</p>
<h4>نکات مهم واریز وجه</h4>
<ul>
  <li>واریز وجه صرفاً از طریق شبکه رسمی شاپرک و درگاه‌های مجاز بانکی انجام می‌پذیرد.</li>
  <li>پس از تکمیل پرداخت، کد رهگیری یکتا نمایش داده شده و سهم شما در سامانه ثبت می‌شود.</li>
  <li>در صورت تمایل می‌توانید گزینه «میخواهم گمنام باشم» را فعال نمایید؛ در این حالت نام واقعی شما در امور مالی و سیستمی ثبت شده اما در سایت عمومی عنوان «گمنام» درج می‌گردد.</li>
  <li>در صورت بروز هرگونه قطعی شبکه، وجه کسر شده ظرف ۷۲ ساعت توسط شاپرک عودت داده می‌شود.</li>
</ul>
<div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 14px 16px; margin-top: 18px; margin-bottom: 18px; font-size: 0.85rem; color: #64748b;">
  <div style="font-weight: 700; color: #334155; margin-bottom: 4px;">پشتیبانی و ارتباط با مسئول پویش</div>
  <div>شماره تماس ثبت‌شده در پویش آماده پاسخگویی به سوالات مشارکت‌کنندگان محترم است.</div>
</div>';

if ($method === 'GET') {
    try {
        $stmt = $pdo->query("SELECT terms_content, updated_at FROM payment_settings WHERE id = 'default' LIMIT 1");
        $row = $stmt->fetch();
        $content = ($row && !empty($row['terms_content'])) ? $row['terms_content'] : $defaultTerms;
        $updatedAt = ($row && !empty($row['updated_at'])) ? $row['updated_at'] : date('Y-m-d H:i:s');
        sendResponse(['success' => true, 'content' => $content, 'updated_at' => $updatedAt]);
    } catch (Exception $e) {
        sendResponse(['success' => true, 'content' => $defaultTerms, 'updated_at' => date('Y-m-d H:i:s')]);
    }
}

if ($method === 'POST') {
    $admin = requireAdminAuth($pdo);
    $input = getJsonInput();
    $newContent = isset($input['content']) ? trim($input['content']) : '';

    if (empty($newContent)) {
        sendResponse(['success' => false, 'message' => 'متن قوانین و مقررات نمی‌تواند خالی باشد.'], 400);
    }

    try {
        $stmtPrev = $pdo->query("SELECT terms_content FROM payment_settings WHERE id = 'default' LIMIT 1");
        $prevRow = $stmtPrev->fetch();
        $prevContent = ($prevRow && !empty($prevRow['terms_content'])) ? $prevRow['terms_content'] : $defaultTerms;

        $stmt = $pdo->prepare("
            INSERT INTO payment_settings (id, terms_content)
            VALUES ('default', :content)
            ON DUPLICATE KEY UPDATE
                terms_content = VALUES(terms_content),
                updated_at    = CURRENT_TIMESTAMP
        ");
        $stmt->execute([':content' => $newContent]);

        // ثبت اعلان لغو‌پذیر
        $notifId = 'notif-' . bin2hex(random_bytes(8));
        $undoData = json_encode([
            'type'         => 'terms_update',
            'prev_content' => $prevContent,
            'new_content'  => $newContent
        ], JSON_UNESCAPED_UNICODE);

        $pdo->prepare("
            INSERT INTO notifications (id, title, description, type, is_read, reversible, undone, undo_data, created_at)
            VALUES (:id, :title, :desc, 'terms_update', 0, 1, 0, :undo, CURRENT_TIMESTAMP)
        ")->execute([
            ':id'    => $notifId,
            ':title' => 'بروزرسانی قوانین و مقررات',
            ':desc'  => 'متن قوانین و مقررات پویش توسط مدیریت ویرایش و ذخیره گردید.',
            ':undo'  => $undoData
        ]);

        sendResponse([
            'success' => true,
            'message' => 'قوانین و مقررات با موفقیت بروزرسانی شد.',
            'content' => $newContent
        ]);
    } catch (Exception $e) {
        sendResponse(['success' => false, 'message' => 'خطا در ذخیره پایگاه‌داده: ' . $e->getMessage()], 500);
    }
}
