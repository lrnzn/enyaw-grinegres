-- ============================================================================
-- SGVMS — Student Security & Guardian Verification Management System
-- Database schema, VERSION 2.1 (matches SGVMS-ERD-v2.1). MySQL 5.7+ / MariaDB 10.3+
--
-- NEW INSTALL:  import this file with phpMyAdmin (Import tab), or:  mysql -u root -p < schema.sql
-- UPGRADING? Version 1: use migrate_v1_to_v2.sql. Version 2: use migrate_v2_to_v2_1.sql. Both keep your data.
--
-- This file creates the database AND the tables. It has NO users and NO student data:
-- load sample_data.sql for a demo, or create_admin.php for a real administrator.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `sgvms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sgvms`;
SET NAMES utf8mb4;

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
