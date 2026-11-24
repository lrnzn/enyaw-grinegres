-- ============================================================================
-- SGVMS - Student Security & Guardian Verification Management System
-- FRESH DATABASE SCHEMA, version 2.3. Works on MySQL 5.7+ and MariaDB 10.3+.
--
-- HOW TO INSTALL (phpMyAdmin, including InfinityFree):
--   1. Create an empty database in your hosting panel (or use the one you were given).
--   2. In phpMyAdmin, CLICK THAT DATABASE'S NAME in the left list, so it is the selected one.
--   3. Open the Import tab, choose this file, and click Import.
--   4. Optional demo only: import sample_data.sql the same way.
--
-- This file does NOT create a database and does NOT say which one to use, so it works with any
-- database name (hosting providers choose names like if0_12345_sgvms). It is for a NEW, EMPTY database.
-- It has no users and no students: use create_admin.php for a real administrator, or sample_data.sql for a demo.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

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
  `status`        ENUM('enrolled','withdrawn','graduated','archived') NOT NULL DEFAULT 'enrolled',
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

-- Bell schedule: the time slots of the school day (edited in the app, nothing is fixed)
CREATE TABLE IF NOT EXISTS `periods` (
  `period_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(40) NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time`   TIME NOT NULL,
  PRIMARY KEY (`period_id`),
  UNIQUE KEY `uq_period_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80) NOT NULL,
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `uq_subject_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who teaches what to which section, on which day and period (day_of_week: 1 = Monday ... 7 = Sunday)
CREATE TABLE IF NOT EXISTS `class_schedules` (
  `schedule_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id`  INT UNSIGNED NOT NULL,
  `subject_id`  INT UNSIGNED NOT NULL,
  `teacher_id`  INT UNSIGNED NOT NULL,
  `period_id`   INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT UNSIGNED NOT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  UNIQUE KEY `uq_sched_section` (`section_id`, `day_of_week`, `period_id`),
  UNIQUE KEY `uq_sched_teacher` (`teacher_id`, `day_of_week`, `period_id`),
  CONSTRAINT `fk_cs_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cs_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cs_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_cs_period`  FOREIGN KEY (`period_id`)  REFERENCES `periods` (`period_id`)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance per class period. A schedule that has records can never be deleted (RESTRICT), so history is safe.
CREATE TABLE IF NOT EXISTS `period_attendance` (
  `pa_id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id`     INT UNSIGNED NOT NULL,
  `student_id`      INT UNSIGNED NOT NULL,
  `attendance_date` DATE NOT NULL,
  `status`          ENUM('present','late','absent','excused','no_class') NOT NULL,
  `remarks`         VARCHAR(255) NULL,
  `recorded_by`     INT UNSIGNED NULL,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pa_id`),
  UNIQUE KEY `uq_period_att` (`schedule_id`, `student_id`, `attendance_date`),
  KEY `idx_pa_date` (`attendance_date`),
  CONSTRAINT `fk_pa_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `class_schedules` (`schedule_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_pa_student`  FOREIGN KEY (`student_id`)  REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_user`     FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
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
