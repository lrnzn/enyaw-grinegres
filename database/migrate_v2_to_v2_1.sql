-- ============================================================================
-- SGVMS — UPGRADE a version 2 database to VERSION 2.1 (keeps all your data)
--
-- Adds: parent notifications (a new `notifications` table and a per-guardian switch),
--       and a "not called" option for pickups confirmed by letter only.
-- BEFORE YOU RUN THIS: export a backup of your database (phpMyAdmin -> Export).
-- HOW: select your sgvms database in phpMyAdmin, open the Import tab, and import this file.
--       Run it ONCE. (Starting fresh? Use schema.sql instead.)
-- ============================================================================
USE `sgvms`;
SET NAMES utf8mb4;

ALTER TABLE `student_guardians`
  ADD COLUMN `receive_notifications` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_authorized`;

ALTER TABLE `guardian_confirmations`
  MODIFY `phone_call_status` ENUM('reached','no_answer','declined','not_called') NOT NULL;

-- Messages to parents (arrival and pickup). status 'simulated' means nothing was really sent.
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`      INT UNSIGNED NULL,
  `guardian_id`     INT UNSIGNED NULL,
  `log_id`          BIGINT UNSIGNED NULL,
  `event`           ENUM('arrival','pickup') NOT NULL,
  `channel`         ENUM('sms','email') NOT NULL DEFAULT 'sms',
  `recipient`       VARCHAR(120) NOT NULL,
  `recipient_name`  VARCHAR(160) NOT NULL,
  `message`         VARCHAR(480) NOT NULL,
  `status`          ENUM('queued','sent','simulated','failed') NOT NULL DEFAULT 'queued',
  `provider_note`   VARCHAR(255) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at`         DATETIME NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notif_time` (`created_at`),
  KEY `idx_notif_status` (`status`),
  CONSTRAINT `fk_nt_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`student_id`)   ON DELETE SET NULL,
  CONSTRAINT `fk_nt_guardian` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`guardian_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_nt_log`      FOREIGN KEY (`log_id`)      REFERENCES `gate_logs` (`log_id`)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
