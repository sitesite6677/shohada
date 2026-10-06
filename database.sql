-- ==============================================================================
-- ساختار پایگاه‌داده MySQL برای هاست‌های cPanel / DirectAdmin / Apache
-- فایل: database.sql
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- جدول پویش‌ها (campaigns)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `campaigns`;
CREATE TABLE `campaigns` (
  `id` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL COMMENT 'عنوان پویش',
  `description` TEXT NULL COMMENT 'توضیحات و اهداف پویش',
  `image_url` VARCHAR(500) NULL COMMENT 'آدرس تصویر یا بنر پویش',
  `total_shares` INT NOT NULL DEFAULT 100 COMMENT 'کل سهم‌های هدف پویش',
  `share_price` BIGINT NOT NULL DEFAULT 50000 COMMENT 'مبلغ هر سهم به تومان',
  `start_date` VARCHAR(50) NULL COMMENT 'تاریخ شروع شمسی',
  `end_date` VARCHAR(50) NULL COMMENT 'تاریخ پایان شمسی',
  `status` ENUM('pending', 'active', 'completed') NOT NULL DEFAULT 'active' COMMENT 'وضعیت پویش',
  `event_location` VARCHAR(255) NULL COMMENT 'مکان برگزاری یا توزیع',
  `event_date` VARCHAR(100) NULL COMMENT 'تاریخ رویداد',
  `event_time` VARCHAR(100) NULL COMMENT 'ساعت رویداد',
  `channel_link` VARCHAR(500) NULL COMMENT 'لینک کانال ایتا/تلگرام',
  `social_link` VARCHAR(500) NULL COMMENT 'لینک بله یا گروه مجازی',
  `contact_phone` VARCHAR(50) NULL COMMENT 'شماره تماس مسئول پویش',
  `additional_notes` TEXT NULL COMMENT 'نکات تکمیلی و شیوه توزیع',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_campaigns_status` (`status`),
  INDEX `idx_campaigns_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- جدول کاربران مشارکت‌کننده (users)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL COMMENT 'نام و نام خانوادگی',
  `phone` VARCHAR(50) NOT NULL COMMENT 'شماره تلفن همراه',
  `payment_status` ENUM('successful', 'pending', 'failed') NOT NULL DEFAULT 'successful' COMMENT 'وضعیت پرداخت',
  `status` ENUM('pending_approval', 'approved', 'rejected') NOT NULL DEFAULT 'approved' COMMENT 'وضعیت تأیید مدیریت',
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'تمایل به گمنام بودن',
  `is_public_visible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'نمایش در سایت عمومی',
  `total_amount` BIGINT NOT NULL DEFAULT 0 COMMENT 'مجموع مبالغ واریزی',
  `payments_count` INT NOT NULL DEFAULT 1 COMMENT 'تعداد دفعات مشارکت',
  `last_activity` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاریخ آخرین مشارکت',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'تاریخ عضویت',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_phone` (`phone`),
  INDEX `idx_users_status` (`status`),
  INDEX `idx_users_name` (`name`),
  INDEX `idx_users_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- جدول پرداخت‌ها و مشارکت‌ها (payments)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` VARCHAR(64) NOT NULL,
  `campaign_id` VARCHAR(64) NOT NULL COMMENT 'شناسه پویش مربوطه',
  `user_id` VARCHAR(64) NULL COMMENT 'شناسه کاربر ثبت شده',
  `payer_name` VARCHAR(255) NOT NULL COMMENT 'نام و نام خانوادگی واقعی',
  `phone` VARCHAR(50) NOT NULL COMMENT 'شماره تلفن همراه',
  `shares` INT NOT NULL DEFAULT 1 COMMENT 'تعداد سهم مشارکت',
  `amount` BIGINT NOT NULL DEFAULT 0 COMMENT 'مبلغ پرداختی به تومان',
  `tracking_code` VARCHAR(64) NOT NULL COMMENT 'کد رهگیری سیستم',
  `description` TEXT NULL COMMENT 'نیت یا توضیحات واریزکننده',
  `is_anonymous` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'آیا کاربر گمنام است',
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'تایید نمایش عمومی توسط مدیر',
  `status` ENUM('pending', 'successful', 'failed', 'cancelled', 'verification_failed') NOT NULL DEFAULT 'pending' COMMENT 'وضعیت تراکنش',
  `gateway` VARCHAR(50) NOT NULL DEFAULT 'test_gateway' COMMENT 'درگاه پرداخت',
  `transaction_id` VARCHAR(100) NULL COMMENT 'شماره ارجاع بانک',
  `authority_token` VARCHAR(100) NULL COMMENT 'شناسه درگاه شاپرک',
  `verified_at` DATETIME NULL COMMENT 'زمان تایید تراکنش',
  `paid_at` DATETIME NULL COMMENT 'زمان واریز',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tracking_code` (`tracking_code`),
  INDEX `idx_payments_campaign` (`campaign_id`),
  INDEX `idx_payments_user` (`user_id`),
  INDEX `idx_payments_status` (`status`),
  INDEX `idx_payments_phone` (`phone`),
  INDEX `idx_payments_authority` (`authority_token`),
  CONSTRAINT `fk_payments_campaign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- جدول تنظیمات درگاه و قوانین (payment_settings)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `payment_settings`;
CREATE TABLE `payment_settings` (
  `id` VARCHAR(32) NOT NULL DEFAULT 'default',
  `active_gateway` VARCHAR(50) NOT NULL DEFAULT 'test_gateway' COMMENT 'نام درگاه فعال',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'وضعیت فعال/غیرفعال بودن درگاه',
  `sandbox` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'حالت تست سندباکس',
  `merchant_id` VARCHAR(255) NULL DEFAULT '' COMMENT 'کد مرچنت درگاه',
  `api_key` VARCHAR(255) NULL DEFAULT '' COMMENT 'کلید خصوصی API',
  `terminal_id` VARCHAR(100) NULL DEFAULT '' COMMENT 'کد ترمینال',
  `terms_content` LONGTEXT NULL COMMENT 'متن ویرایش‌پذیر قوانین و مقررات',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- جدول اعلان‌های مدیریت با قابلیت لغو عملیات (notifications)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL COMMENT 'عنوان اعلان',
  `description` TEXT NOT NULL COMMENT 'شرح اعلان',
  `type` VARCHAR(64) NOT NULL DEFAULT 'info' COMMENT 'نوع اعلان',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'خوانده شده یا نشده',
  `reversible` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'آیا این عملیات قابل بازگردانی است',
  `undone` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'آیا لغو تغییرات انجام شده است',
  `undo_data` JSON NULL COMMENT 'داده‌های لازم جهت بازگردانی وضعیت قبلی',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notifications_read` (`is_read`),
  INDEX `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- جدول لاگ رویدادها و فعالیت‌های سیستم (audit_logs)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` VARCHAR(64) NOT NULL,
  `action_type` VARCHAR(100) NOT NULL COMMENT 'نوع عملیات',
  `actor` VARCHAR(255) NOT NULL DEFAULT 'سیستم' COMMENT 'انجام‌دهنده عملیات',
  `target` VARCHAR(255) NULL COMMENT 'هدف یا کاربر مرتبط',
  `description` TEXT NOT NULL COMMENT 'شرح رویداد',
  `status` VARCHAR(50) NOT NULL DEFAULT 'موفق' COMMENT 'وضعیت عملیات',
  `details` JSON NULL COMMENT 'اطلاعات تکمیلی',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_logs_action` (`action_type`),
  INDEX `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- جدول مدیران سیستم (admins)
-- ------------------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id` INT AUTO_INCREMENT NOT NULL,
  `email` VARCHAR(255) NOT NULL COMMENT 'ایمیل مدیر',
  `password` VARCHAR(255) NOT NULL COMMENT 'هش رمز عبور bcrypt',
  `token` VARCHAR(255) NULL COMMENT 'توکن احراز هویت',
  `token_expiry` DATETIME NULL COMMENT 'زمان انقضای نشست',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- داده‌های پیش‌فرض اولیه
-- ------------------------------------------------------------------------------
INSERT INTO `payment_settings` (`id`, `active_gateway`, `is_active`, `sandbox`, `merchant_id`, `api_key`, `terminal_id`, `terms_content`) 
VALUES ('default', 'test_gateway', 1, 1, '', '', '', '<h4>مقدمه و اهداف پویش</h4><p>این سامانه جهت تسهیل در جمع‌آوری نذورات و مشارکت‌های مردمی به صورت شفاف، سهم‌بندی شده و دقیق راه‌اندازی شده است. تمامی مبالغ واریزی منحصراً صرف اهداف اعلام‌شده در عنوان و توضیحات پویش می‌گردد.</p><h4>نکات مهم واریز وجه</h4><ul><li>واریز وجه صرفاً از طریق شبکه رسمی شاپرک و درگاه‌های مجاز بانکی صورت می‌پذیرد.</li><li>پس از تکمیل پرداخت، کد رهگیری یکتا نمایش داده شده و سهم شما در سامانه ثبت می‌شود.</li><li>در صورت انتخاب گزینه گمنام، نام واقعی شما تنها برای امور مالی و مدیران محفوظ بوده و در سایت عمومی به عنوان گمنام درج می‌گردد.</li><li>در صورت بروز هرگونه قطعی شبکه، وجه کسر شده ظرف ۷۲ ساعت توسط شاپرک عودت داده می‌شود.</li></ul>') 
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `admins` (`id`, `email`, `password`) VALUES 
  (1, 'admin@example.com', '$2y$10$nOUIs54Hm64bw40aJpLhVu9Db5p0wN50hrAThf/PnstJW7V7ZPMwK'),
  (2, 'matinshariati1404@gmail.com', '$2y$10$nOUIs54Hm64bw40aJpLhVu9Db5p0wN50hrAThf/PnstJW7V7ZPMwK')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`), `password` = VALUES(`password`);

SET FOREIGN_KEY_CHECKS = 1;
