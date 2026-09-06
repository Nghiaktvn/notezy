-- =============================================================
-- migrations.sql — Notezy
-- Idempotent: safe to run multiple times on any MySQL 8 / MariaDB 10.6+
-- Run order: after note.sql (initial schema)
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. notes.archived ────────────────────────────────────────────────────
-- Add archived column if not already present
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'archived'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE notes ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0',
    'SELECT 1 -- column already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. labels.user_id ────────────────────────────────────────────────────
-- Allow labels to be scoped per user
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'labels' AND COLUMN_NAME = 'user_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE labels ADD COLUMN user_id INT(11) NULL DEFAULT NULL',
    'SELECT 1 -- column already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- Add FK only if it doesn't already exist
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME         = 'labels'
      AND CONSTRAINT_NAME    = 'labels_ibfk_user'
      AND CONSTRAINT_TYPE    = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE labels ADD CONSTRAINT labels_ibfk_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL',
    'SELECT 1 -- FK already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. Drop old UNIQUE KEY name (labels.name was globally unique) ─────────
SET @idx_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'labels'
      AND INDEX_NAME   = 'name'
);
SET @sql = IF(@idx_exists > 0,
    'ALTER TABLE labels DROP INDEX `name`',
    'SELECT 1 -- index already removed'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Add UNIQUE(user_id, name) — per-user uniqueness ───────────────────
SET @idx2_exists = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'labels'
      AND INDEX_NAME   = 'uq_user_label_name'
);
SET @sql = IF(@idx2_exists = 0,
    'ALTER TABLE labels ADD UNIQUE KEY uq_user_label_name (user_id, name)',
    'SELECT 1 -- unique key already exists'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 5. note_shares: ensure permission default is 'read' ──────────────────
ALTER TABLE `note_shares`
    MODIFY COLUMN `permission` ENUM('read','write') NOT NULL DEFAULT 'read';

-- ── 6. users.pass — nullable (no longer storing plaintext) ───────────────
ALTER TABLE `users`
    MODIFY COLUMN `pass` VARCHAR(255) NULL DEFAULT NULL;

-- ── 7. Migrate existing plaintext passwords ───────────────────────────────
-- For users who still have plaintext in `pass` but no password_hash,
-- we cannot auto-hash in SQL. Mark them so they get prompted on login.
-- (The PHP login() function handles migration on first login.)
-- This just clears obviously invalid short pass values safely.
UPDATE `users`
    SET `pass` = NULL
    WHERE `pass` IS NOT NULL
      AND `password_hash` IS NOT NULL
      AND `password_hash` != ''
      AND LENGTH(`password_hash`) >= 60;

-- ── 12. notes — reminder fields (time-based reminders) ────────────────────
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'reminder_at'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE notes
        ADD COLUMN reminder_at DATETIME NULL DEFAULT NULL,
        ADD COLUMN reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
        ADD INDEX idx_notes_reminder (user_id, reminder_at, reminder_sent)',
    'SELECT 1 -- columns already exist'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- ── 8. users — Activation OTP fields ──────────────────────────────────────
-- FIX: these were plain "ADD COLUMN" with no existence check. That's fine
-- the very first time the script runs, but Docker only executes files in
-- docker-entrypoint-initdb.d/ once, when the db_data volume is first created.
-- If a container/volume built before these columns existed is reused (or
-- this file is re-run manually to patch an existing DB), the bare ADD
-- COLUMN would throw "Duplicate column name" and abort the script — which
-- silently left `users` without the OTP columns register()/verify_activation.php
-- need, causing "registration error" even though the SQL below looks correct.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'activation_otp_hash'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users
        ADD COLUMN activation_otp_hash VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN activation_otp_expires_at DATETIME NULL DEFAULT NULL,
        ADD COLUMN activation_otp_attempts INT(11) NOT NULL DEFAULT 0',
    'SELECT 1 -- columns already exist'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 9. users — Reset Password OTP fields ──────────────────────────────────
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'reset_otp_hash'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users
        ADD COLUMN reset_otp_hash VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN reset_otp_expires_at DATETIME NULL DEFAULT NULL,
        ADD COLUMN reset_otp_attempts INT(11) NOT NULL DEFAULT 0',
    'SELECT 1 -- columns already exist'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 11. AI conversations ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ai_conversations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT 'Cuộc trò chuyện mới',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_conv_user` (`user_id`),
  CONSTRAINT `ai_conversations_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_messages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `role` ENUM('user','assistant','tool') NOT NULL,
  `content` MEDIUMTEXT NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_msg_conv` (`conversation_id`),
  KEY `idx_ai_msg_user` (`user_id`),
  CONSTRAINT `ai_messages_ibfk_conv` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_messages_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_pending_actions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `conversation_id` INT(11) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `risk` ENUM('read','write','destructive') NOT NULL,
  `payload` JSON NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_pending_token` (`token_hash`),
  KEY `idx_ai_pending_user` (`user_id`),
  CONSTRAINT `ai_pending_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_rate_limits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `window_start` DATETIME NOT NULL,
  `request_count` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_rate_user_window` (`user_id`, `window_start`),
  CONSTRAINT `ai_rate_ibfk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 16. notes — 6-digit PIN lock (separate from the free-text note password
--        in note_password.php / notes.password_hash) ─────────────────────
-- Setting/removing a PIN requires the user's ACCOUNT password (checked
-- against users.password_hash in api/note_pin.php) as a second factor.
-- Opening a PIN-locked note only asks for the 6-digit PIN.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'pin_hash'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE notes
        ADD COLUMN pin_hash VARCHAR(255) NULL DEFAULT NULL,
        ADD COLUMN pin_set_at TIMESTAMP NULL DEFAULT NULL',
    'SELECT 1 -- columns already exist'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Per-note-per-session PIN unlock state, so a correct PIN only has to be
-- entered once per browser session instead of on every page view.
CREATE TABLE IF NOT EXISTS `note_pin_unlocks` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `note_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `session_id` VARCHAR(128) NOT NULL,
  `unlocked_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note_pin_unlock` (`note_id`, `session_id`),
  KEY `idx_note_pin_unlock_user` (`user_id`),
  CONSTRAINT `note_pin_unlock_ibfk_note` FOREIGN KEY (`note_id`) REFERENCES `notes` (`note_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 17. timetable — thời khóa biểu, lịch học & báo giờ ─────────────────────
CREATE TABLE IF NOT EXISTS `timetable` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `day_of_week` TINYINT(4) NOT NULL DEFAULT 1,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `teacher` VARCHAR(255) DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT '#4f46e5',
  `note` TEXT DEFAULT NULL,
  `reminder_minutes` INT(11) DEFAULT 15,
  `specific_date` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_timetable_user` (`user_id`),
  CONSTRAINT `fk_timetable_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

