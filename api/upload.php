<?php
/**
 * ==============================================================================
 * آپلود تصاویر پویش: api/upload.php
 * ==============================================================================
 */

require_once __DIR__ . '/db.php';

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('صرفاً درخواست POST مجاز است.', 405);
}

requireAdminAuth($pdo);

if (!isset($_FILES['image']) || empty($_FILES['image']['name'])) {
    sendError('هیچ فایلی ارسال نشده است.', 400);
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendError('خطا در دریافت فایل سرور (کد: ' . $file['error'] . ')', 400);
}

if ($file['size'] > MAX_UPLOAD_SIZE) {
    sendError('حجم تصویر نباید بیشتر از ۵ مگابایت باشد.', 400);
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    sendError('فرمت فایل مجاز نیست. فقط فایل‌های JPG, PNG و WEBP پذیرفته می‌شوند.', 400);
}

// بررسی MIME type با finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowedMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp', 'image/gif'];

if (!in_array($mimeType, $allowedMimes)) {
    sendError('نوع محتوای فایل نامعتبر است.', 400);
}

$targetDir = rtrim(UPLOAD_DIR, '/') . '/';
if (!is_dir($targetDir)) {
    if (!mkdir($targetDir, 0755, true)) {
        sendError('ایجاد پوشه آپلود uploads/campaigns با خطا مواجه شد.', 500);
    }
}

$newFileName = 'poster_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $fileExtension;
$targetFilePath = $targetDir . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $targetFilePath)) {
    sendError('خطا در ذخیره فایل در پوشه uploads.', 500);
}

$publicUrl = rtrim(UPLOAD_URL_PREFIX, '/') . '/' . $newFileName;

sendResponse([
    'success'  => true,
    'message'  => 'تصویر با موفقیت آپلود شد.',
    'url'      => $publicUrl,
    'filename' => $newFileName
]);
