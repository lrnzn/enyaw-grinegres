-- ============================================================================
-- SGVMS — Student Security & Guardian Verification Management System
-- Database schema (MySQL 5.7+ / MariaDB 10.3+)
--
-- Import with phpMyAdmin:  Import tab -> choose this file -> Go
-- Or from a terminal:      mysql -u root -p < schema.sql
-- This file creates the database AND the tables. It contains NO users and NO
-- student data: load sample_data.sql for a demo, or create_admin.php for a real
-- administrator account.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `sgvms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sgvms`;
SET NAMES utf8mb4;

-- System users (administrators and gate staff)
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120) NOT NULL,
  `username`      VARCHAR(60)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('admin','guard') NOT NULL DEFAULT 'guard',
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students (each has a unique QR token printed on the ID card)
CREATE TABLE IF NOT EXISTS `students` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lrn`        VARCHAR(20)  NULL,
  `first_name` VARCHAR(80)  NOT NULL,
  `last_name`  VARCHAR(80)  NOT NULL,
  `grade`      VARCHAR(20)  NOT NULL,
  `section`    VARCHAR(60)  NOT NULL,
  `birthdate`  DATE NULL,
  `address`    VARCHAR(255) NULL,
  `photo`      VARCHAR(120) NULL,
  `qr_token`   VARCHAR(40)  NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_qr` (`qr_token`),
  KEY `idx_students_name` (`last_name`, `first_name`),
  KEY `idx_students_lrn` (`lrn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parents / guardians (one person can be linked to several students: siblings)
CREATE TABLE IF NOT EXISTS `guardians` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(120) NOT NULL,
  `contact`    VARCHAR(40)  NULL,
  `occupation` VARCHAR(100) NULL,
  `photo`      VARCHAR(120) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_guardians_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which guardians may pick up which student (is_authorized = 0 means pickup revoked)
CREATE TABLE IF NOT EXISTS `student_guardians` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`    INT UNSIGNED NOT NULL,
  `guardian_id`   INT UNSIGNED NOT NULL,
  `relationship`  VARCHAR(40) NOT NULL,
  `is_authorized` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_guardian` (`student_id`, `guardian_id`),
  KEY `idx_sg_guardian` (`guardian_id`),
  CONSTRAINT `fk_sg_student`  FOREIGN KEY (`student_id`)  REFERENCES `students`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sg_guardian` FOREIGN KEY (`guardian_id`) REFERENCES `guardians` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit trail: every time-in, release and denial. The app never edits or deletes these rows.
-- (No foreign keys on purpose: the history must survive even if a student is removed.)
CREATE TABLE IF NOT EXISTS `logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id`  INT UNSIGNED NOT NULL,
  `action`      ENUM('TIME_IN','RELEASED','RELEASED_OVERRIDE','DENIED') NOT NULL,
  `guardian_id` INT UNSIGNED NULL,
  `person_name` VARCHAR(120) NULL,
  `note`        VARCHAR(255) NULL,
  `staff_id`    INT UNSIGNED NULL,
  `logged_at`   DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_logs_time` (`logged_at`),
  KEY `idx_logs_student` (`student_id`, `logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed sign-ins (used to slow down password guessing)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`           VARCHAR(45) NOT NULL,
  `username`     VARCHAR(60) NOT NULL,
  `attempted_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attempts` (`ip`, `username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
