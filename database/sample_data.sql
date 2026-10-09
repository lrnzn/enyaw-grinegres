-- ============================================================================
-- SGVMS — DEMO DATA ONLY (fictional people). Do NOT import this on a live site.
-- Import AFTER schema.sql. Demo logins: admin / admin123  and  guard / guard123
-- ============================================================================
USE `sgvms`;
SET NAMES utf8mb4;

INSERT INTO `users` (`id`, `name`, `username`, `password_hash`, `role`) VALUES
(1, 'School Administrator', 'admin', '$2y$10$KQLq5DEkzmXhtsNVCDGxAegoKlnmVWBPFEGNeM7TbjT1PkJ19xl1u', 'admin'),
(2, 'Gate Staff (Teacher)', 'guard', '$2y$10$wxBpy0NKNjTRoYppFXiosOgVbGzSG1q.U8WQ9P6P8hYOqeLmvYyWq', 'guard');

INSERT INTO `students` (`id`, `lrn`, `first_name`, `last_name`, `grade`, `section`, `birthdate`, `address`, `qr_token`) VALUES
(1, '100000000001', 'Maria Clara', 'Santos',     'Grade 3', 'Sampaguita', '2017-03-14', 'Purok 3, Brgy. Sample', 'STU-DEMO0001'),
(2, '100000000002', 'Juan Miguel', 'Dela Cruz',  'Grade 1', 'Rizal',      '2019-07-02', 'Purok 1, Brgy. Sample', 'STU-DEMO0002'),
(3, '100000000003', 'Angela',      'Reyes',      'Grade 5', 'Mabini',     '2015-11-21', 'Purok 5, Brgy. Sample', 'STU-DEMO0003'),
(4, '100000000004', 'Carlo',       'Reyes',      'Grade 2', 'Bonifacio',  '2018-05-09', 'Purok 5, Brgy. Sample', 'STU-DEMO0004'),
(5, '100000000005', 'Sofia',       'Villanueva', 'Grade 4', 'Luna',       '2016-01-30', 'Purok 2, Brgy. Sample', 'STU-DEMO0005'),
(6, '100000000006', 'Paolo',       'Bautista',   'Grade 6', 'Aguinaldo',  '2014-09-17', 'Purok 4, Brgy. Sample', 'STU-DEMO0006');

INSERT INTO `guardians` (`id`, `full_name`, `contact`, `occupation`) VALUES
(1,  'Elena Santos',        '0917 000 1111', 'Teacher'),
(2,  'Roberto Santos',      '0917 000 1112', 'Driver'),
(3,  'Teresita Santos',     '0917 000 1113', 'Retired'),
(4,  'Carmela Dela Cruz',   '0918 000 2221', 'Nurse'),
(5,  'Danilo Dela Cruz',    '0918 000 2222', 'Electrician'),
(6,  'Rosario Reyes',       '0919 000 3331', 'Store owner'),
(7,  'Ernesto Reyes',       '0919 000 3332', 'Farmer'),
(8,  'Patricia Villanueva', '0920 000 4441', 'Accountant'),
(9,  'Manuel Villanueva',   '0920 000 4442', 'Engineer'),
(10, 'Cynthia Bautista',    '0921 000 5551', 'Vendor'),
(11, 'Ramon Bautista',      '0921 000 5552', 'Unknown');

-- Rosario and Ernesto Reyes are linked to BOTH Reyes siblings.
-- Ramon Bautista is registered but NOT authorized (is_authorized = 0).
INSERT INTO `student_guardians` (`student_id`, `guardian_id`, `relationship`, `is_authorized`) VALUES
(1, 1, 'Mother', 1), (1, 2, 'Father', 1), (1, 3, 'Grandmother', 1),
(2, 4, 'Mother', 1), (2, 5, 'Father', 1),
(3, 6, 'Mother', 1), (3, 7, 'Uncle', 1),
(4, 6, 'Mother', 1), (4, 7, 'Uncle', 1),
(5, 8, 'Mother', 1), (5, 9, 'Father', 1),
(6, 10, 'Mother', 1), (6, 11, 'Father', 0);

-- This morning's drop-offs (Paolo has no time-in, to show the warning).
INSERT INTO `logs` (`student_id`, `action`, `staff_id`, `logged_at`) VALUES
(1, 'TIME_IN', 2, TIMESTAMP(CURDATE(), '07:12:00')),
(2, 'TIME_IN', 2, TIMESTAMP(CURDATE(), '07:25:00')),
(3, 'TIME_IN', 2, TIMESTAMP(CURDATE(), '07:31:00')),
(4, 'TIME_IN', 2, TIMESTAMP(CURDATE(), '07:31:00')),
(5, 'TIME_IN', 2, TIMESTAMP(CURDATE(), '07:48:00'));
