-- ============================================================================
-- SGVMS — UPGRADE a version 1 database to VERSION 2 (keeps all your data)
--
-- BEFORE YOU RUN THIS:  export a backup of your database (phpMyAdmin -> Export).
-- HOW:  select your sgvms database in phpMyAdmin, open the SQL or Import tab, run this file.
--       (If your database is not called sgvms, change the USE line below.)
-- WHAT IT DOES:  renames the old tables to *_v1, creates the version 2 tables, and copies
--       everything across (ids are kept, so photos and history stay linked).
--       The *_v1 tables are left in place as a safety copy. After you have checked that
--       everything looks right, you can drop them (see the last lines of this file).
-- NOTES:  * guardians' and staff full names are split into first / last name at the LAST space
--           ("Maria Clara Santos" -> first "Maria Clara", last "Santos"); review them afterwards.
--         * each distinct grade + section becomes a row in sections for the current school year.
--         * role 'guard' becomes 'gate_staff'.
--         * old failed-login records are kept in login_logs.
-- ============================================================================
USE `sgvms`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

RENAME TABLE `users` TO `users_v1`, `students` TO `students_v1`, `guardians` TO `guardians_v1`,
             `student_guardians` TO `student_guardians_v1`, `logs` TO `logs_v1`,
             `login_attempts` TO `login_attempts_v1`;

-- System accounts (administrators, teachers, gate staff)
CREATE TABLE IF NOT EXISTS `users` (
  `user_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(60)  NOT NULL,
  `email`         VARCHAR(120) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `first_name`    VARCHAR(80)  NOT NULL,
  `middle_name`   VARCHAR(80)  NULL,
  `last_name`     VARCHAR(80)  NOT NULL,
  `name_suffix`   VARCHAR(10)  NULL,
  `role`          ENUM('admin','teacher','gate_staff') NOT NULL DEFAULT 'gate_staff',
  `status`        ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teacher profile of a user account (one to one)
CREATE TABLE IF NOT EXISTS `teachers` (
  `teacher_id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NOT NULL,
  `contact_number` VARCHAR(40)  NULL,
  `photo_path`     VARCHAR(120) NULL,
  `status`         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`teacher_id`),
  UNIQUE KEY `uq_teachers_user` (`user_id`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classes (a section in one school year, led by an adviser)
CREATE TABLE IF NOT EXISTS `sections` (
  `section_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `adviser_id`   INT UNSIGNED NULL,
  `section_name` VARCHAR(60) NOT NULL,
  `grade_level`  VARCHAR(20) NOT NULL,
  `school_year`  VARCHAR(9)  NOT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `uq_section` (`section_name`, `grade_level`, `school_year`),
  CONSTRAINT `fk_sections_adviser` FOREIGN KEY (`adviser_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students (qr_token is the code printed on the ID card)
CREATE TABLE IF NOT EXISTS `students` (
  `student_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id`    INT UNSIGNED NULL,
  `lrn`           VARCHAR(20)  NULL,
  `qr_token`      VARCHAR(40)  NOT NULL,
  `first_name`    VARCHAR(80)  NOT NULL,
  `middle_name`   VARCHAR(80)  NULL,
  `last_name`     VARCHAR(80)  NOT NULL,
  `name_suffix`   VARCHAR(10)  NULL,
  `gender`        ENUM('female','male') NULL,
  `date_of_birth` DATE NULL,
  `address`       VARCHAR(255) NULL,
  `photo_path`    VARCHAR(120) NULL,
  `status`        ENUM('enrolled','withdrawn','graduated') NOT NULL DEFAULT 'enrolled',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `uq_students_qr` (`qr_token`),
  UNIQUE KEY `uq_students_lrn` (`lrn`),
  KEY `idx_students_name` (`last_name`, `first_name`),
  KEY `idx_students_section` (`section_id`),
  CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parents / guardians (one person can be linked to several students: siblings)
CREATE TABLE IF NOT EXISTS `guardians` (
  `guardian_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name`     VARCHAR(80)  NOT NULL,
  `middle_name`    VARCHAR(80)  NULL,
  `last_name`      VARCHAR(80)  NOT NULL,
  `name_suffix`    VARCHAR(10)  NULL,
  `contact_number` VARCHAR(40)  NULL,
  `email`          VARCHAR(120) NULL,
  `occupation`     VARCHAR(100) NULL,
  `address`        VARCHAR(255) NULL,
  `photo_path`     VARCHAR(120) NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`guardian_id`),
  KEY `idx_guardians_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which guardians may pick up which student (is_authorized = 0 means pickup revoked)
CREATE TABLE IF NOT EXISTS `student_guardians` (
  `student_guardian_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`    INT UNSIGNED NOT NULL,
  `guardian_id`   INT UNSIGNED NOT NULL,
  `relationship`  VARCHAR(40) NOT NULL,
  `is_authorized` TINYINT(1) NOT NULL DEFAULT 1,
  `receive_notifications` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_guardian_id`),
  UNIQUE KEY `uq_student_guardian` (`student_id`, `guardian_id`),
  KEY `idx_sg_guardian` (`guardian_id`),
  CONSTRAINT `fk_stgd_student`  FOREIGN KEY (`student_id`)  REFERENCES `students`  (`student_id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_stgd_guardian` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`guardian_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- People who collect a child but are NOT registered guardians (need a confirmation)
CREATE TABLE IF NOT EXISTS `collectors` (
  `collector_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name`     VARCHAR(80) NOT NULL,
  `middle_name`    VARCHAR(80) NULL,
  `last_name`      VARCHAR(80) NOT NULL,
  `contact_number` VARCHAR(40) NULL,
  `photo_path`     VARCHAR(120) NULL,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`collector_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proof for a substitute pickup: letter, phone call to a registered guardian, approving admin
CREATE TABLE IF NOT EXISTS `guardian_confirmations` (
  `confirmation_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`        INT UNSIGNED NOT NULL,
  `guardian_id`       INT UNSIGNED NULL,
  `collector_id`      INT UNSIGNED NOT NULL,
  `approved_by`       INT UNSIGNED NOT NULL,
  `relationship`      VARCHAR(40) NULL,
  `letter_path`       VARCHAR(120) NULL,
  `phone_call_status` ENUM('reached','no_answer','declined','not_called') NOT NULL,
  `confirmed_at`      DATETIME NOT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`confirmation_id`),
  KEY `idx_gc_student` (`student_id`),
  CONSTRAINT `fk_gc_student`   FOREIGN KEY (`student_id`)   REFERENCES `students`   (`student_id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_gc_guardian`  FOREIGN KEY (`guardian_id`)  REFERENCES `guardians`  (`guardian_id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_gc_collector` FOREIGN KEY (`collector_id`) REFERENCES `collectors` (`collector_id`),
  CONSTRAINT `fk_gc_approver`  FOREIGN KEY (`approved_by`)  REFERENCES `users`      (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail: every time-in, release and denial. The app never edits or deletes these rows.
-- Names are copied into the row so the history stays readable even if a record is deleted later.
CREATE TABLE IF NOT EXISTS `gate_logs` (
  `log_id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`       INT UNSIGNED NULL,
  `guardian_id`      INT UNSIGNED NULL,
  `confirmation_id`  INT UNSIGNED NULL,
  `recorded_by`      INT UNSIGNED NULL,
  `action`           ENUM('TIME_IN','RELEASED','RELEASED_OVERRIDE','DENIED') NOT NULL,
  `student_name`     VARCHAR(160) NOT NULL,
  `released_to_name` VARCHAR(160) NULL,
  `note`             VARCHAR(255) NULL,
  `logged_at`        DATETIME NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_gate_time` (`logged_at`),
  KEY `idx_gate_student` (`student_id`, `logged_at`),
  CONSTRAINT `fk_gl_student`      FOREIGN KEY (`student_id`)      REFERENCES `students` (`student_id`)  ON DELETE SET NULL,
  CONSTRAINT `fk_gl_guardian`     FOREIGN KEY (`guardian_id`)     REFERENCES `guardians` (`guardian_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gl_confirmation` FOREIGN KEY (`confirmation_id`) REFERENCES `guardian_confirmations` (`confirmation_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gl_user`         FOREIGN KEY (`recorded_by`)     REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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

-- Daily attendance status (separate from gate events; teachers can mark absent / late / excused)
CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`      INT UNSIGNED NOT NULL,
  `recorded_by`     INT UNSIGNED NULL,
  `attendance_date` DATE NOT NULL,
  `status`          ENUM('present','absent','late','excused') NOT NULL,
  `remarks`         VARCHAR(255) NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `uq_attendance_day` (`student_id`, `attendance_date`),
  CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`)  REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_att_user`    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every sign-in attempt (also used to slow down password guessing)
CREATE TABLE IF NOT EXISTS `login_logs` (
  `login_log_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`        INT UNSIGNED NULL,
  `username_tried` VARCHAR(60) NOT NULL,
  `success`        TINYINT(1) NOT NULL,
  `ip_address`     VARCHAR(45) NOT NULL,
  `user_agent`     VARCHAR(255) NULL,
  `login_time`     DATETIME NOT NULL,
  `logout_time`    DATETIME NULL,
  PRIMARY KEY (`login_log_id`),
  KEY `idx_login_attempts` (`ip_address`, `username_tried`, `login_time`),
  CONSTRAINT `fk_ll_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What administrators changed (revoked a pickup, deleted a student, added staff, ...)
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `audit_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED NULL,
  `action`      VARCHAR(60) NOT NULL,
  `entity_type` VARCHAR(40) NOT NULL,
  `entity_id`   INT UNSIGNED NULL,
  `details`     VARCHAR(255) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `idx_audit_time` (`created_at`),
  CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- users (name is split; guard -> gate_staff)
INSERT INTO `users` (`user_id`,`username`,`password_hash`,`first_name`,`last_name`,`role`,`status`,`created_at`)
SELECT `id`, `username`, `password_hash`, TRIM(IF(LOCATE(' ', `name`)=0, `name`, LEFT(`name`, CHAR_LENGTH(`name`) - CHAR_LENGTH(SUBSTRING_INDEX(`name`, ' ', -1))))), IF(LOCATE(' ', `name`)=0, '', SUBSTRING_INDEX(`name`, ' ', -1)),
       IF(`role`='guard','gate_staff',`role`), 'active', `created_at`
FROM `users_v1`;

-- sections come from the distinct grade + section pairs on the old students table
INSERT INTO `sections` (`section_name`,`grade_level`,`school_year`)
SELECT DISTINCT `section`, `grade`,
       IF(MONTH(CURDATE())>=6, CONCAT(YEAR(CURDATE()),'-',YEAR(CURDATE())+1), CONCAT(YEAR(CURDATE())-1,'-',YEAR(CURDATE())))
FROM `students_v1`;

INSERT INTO `students` (`student_id`,`section_id`,`lrn`,`qr_token`,`first_name`,`last_name`,`date_of_birth`,`address`,`photo_path`,`status`,`created_at`)
SELECT s.`id`, sec.`section_id`, NULLIF(s.`lrn`,''), s.`qr_token`, s.`first_name`, s.`last_name`,
       s.`birthdate`, s.`address`, s.`photo`, 'enrolled', s.`created_at`
FROM `students_v1` s
LEFT JOIN `sections` sec ON sec.`section_name` = s.`section` AND sec.`grade_level` = s.`grade`;

INSERT INTO `guardians` (`guardian_id`,`first_name`,`last_name`,`contact_number`,`occupation`,`photo_path`,`created_at`)
SELECT `id`, TRIM(IF(LOCATE(' ', `full_name`)=0, `full_name`, LEFT(`full_name`, CHAR_LENGTH(`full_name`) - CHAR_LENGTH(SUBSTRING_INDEX(`full_name`, ' ', -1))))), IF(LOCATE(' ', `full_name`)=0, '', SUBSTRING_INDEX(`full_name`, ' ', -1)), `contact`, `occupation`, `photo`, `created_at`
FROM `guardians_v1`;

INSERT INTO `student_guardians` (`student_guardian_id`,`student_id`,`guardian_id`,`relationship`,`is_authorized`)
SELECT `id`, `student_id`, `guardian_id`, COALESCE(`relationship`,''), `is_authorized` FROM `student_guardians_v1`;

-- gate logs: names are copied into each row; links to deleted records become NULL
INSERT INTO `gate_logs` (`log_id`,`student_id`,`guardian_id`,`recorded_by`,`action`,`student_name`,`released_to_name`,`note`,`logged_at`)
SELECT l.`id`, st.`student_id`, g.`guardian_id`, u.`user_id`, l.`action`,
       COALESCE(NULLIF(TRIM(CONCAT_WS(' ', st.`first_name`, st.`last_name`)),''), '(removed student)'),
       COALESCE(NULLIF(TRIM(CONCAT_WS(' ', g.`first_name`, g.`last_name`)),''), l.`person_name`),
       l.`note`, l.`logged_at`
FROM `logs_v1` l
LEFT JOIN `students`  st ON st.`student_id` = l.`student_id`
LEFT JOIN `guardians` g  ON g.`guardian_id` = l.`guardian_id`
LEFT JOIN `users`     u  ON u.`user_id`     = l.`staff_id`;

INSERT INTO `login_logs` (`username_tried`,`success`,`ip_address`,`login_time`)
SELECT `username`, 0, `ip`, `attempted_at` FROM `login_attempts_v1`;

-- Attendance starts empty; time-ins from now on mark students present automatically.

-- ---------------------------------------------------------------------------
-- WHEN EVERYTHING LOOKS RIGHT, you may remove the safety copies:
--   DROP TABLE `login_attempts_v1`, `logs_v1`, `student_guardians_v1`, `guardians_v1`, `students_v1`, `users_v1`;
-- ---------------------------------------------------------------------------
