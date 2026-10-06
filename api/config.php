<?php
/**
 * ==============================================================================
 * پیکربندی اتصال به پایگاه داده MySQL (مخصوص هاست cPanel / DirectAdmin)
 * فایل: api/config.php
 * ==============================================================================
 */

// اطلاعات دیتابیس MySQL هاست شما
define('DB_HOST', 'localhost');          // در اکثر هاست‌های لینوکسی localhost است
define('DB_NAME', 'campaign_db');        // نام دیتابیس ساخته شده در cPanel
define('DB_USER', 'campaign_user');      // نام کاربری دیتابیس
define('DB_PASS', 'your_password_here'); // رمز عبور دیتابیس
define('DB_CHARSET', 'utf8mb4');         // یونیکد فارسی استاندارد

// مسیر و تنظیمات آپلود تصاویر
define('UPLOAD_DIR', __DIR__ . '/../uploads/campaigns/');
define('UPLOAD_URL_PREFIX', '/uploads/campaigns/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // حداکثر ۵ مگابایت
define('JWT_SECRET_KEY', 'campaign_management_secret_key_2024_secure');
