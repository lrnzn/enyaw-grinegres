-- ============================================================================
-- SGVMS — DEMO DATA ONLY (version 2, fictional people). Do NOT import on a live site.
-- Import AFTER schema.sql.
-- Demo logins:  admin / admin123   teacher / teacher123   guard / guard123
-- ============================================================================
USE `sgvms`;
SET NAMES utf8mb4;

INSERT INTO `users` (`user_id`,`username`,`email`,`password_hash`,`first_name`,`last_name`,`role`) VALUES
(1,'admin',  'admin@sample.school',  '$2y$10$TaeKD743FfA54ugfqGqdpOWFRpSPlKuwGpfCr0llzJC2Hki67Z4OK',  'School','Administrator','admin'),
(2,'guard',  NULL,                   '$2y$10$g4VerDhtBSbQZRMKuysYUOHzmTourD9NP7YKN0p6OnP2cCS0UrMaG',  'Gate','Staff','gate_staff'),
(3,'teacher','ana.lopez@sample.school','$2y$10$Zbv.1gOjmF/qdejoZoL3X.Mn02c3yr4PoHEtGz9ETvwLa/9CwzWqq','Ana','Lopez','teacher'),
(4,'teacher2','ben.ramos@sample.school','$2y$10$Zbv.1gOjmF/qdejoZoL3X.Mn02c3yr4PoHEtGz9ETvwLa/9CwzWqq','Ben','Ramos','teacher');

INSERT INTO `teachers` (`teacher_id`,`user_id`,`contact_number`) VALUES
(1,3,'0917 111 0003'),(2,4,'0917 111 0004');

INSERT INTO `sections` (`section_id`,`adviser_id`,`section_name`,`grade_level`,`school_year`) VALUES
(1,1,'Sampaguita','Grade 3','2026-2027'),
(2,2,'Rizal','Grade 1','2026-2027'),
(3,NULL,'Mabini','Grade 5','2026-2027'),
(4,NULL,'Bonifacio','Grade 2','2026-2027'),
(5,NULL,'Luna','Grade 4','2026-2027'),
(6,NULL,'Aguinaldo','Grade 6','2026-2027');

INSERT INTO `students` (`student_id`,`section_id`,`lrn`,`qr_token`,`first_name`,`last_name`,`gender`,`date_of_birth`,`address`) VALUES
(1,1,'100000000001','STU-DEMO0001','Maria Clara','Santos','female','2017-03-14','Purok 3, Brgy. Sample'),
(2,2,'100000000002','STU-DEMO0002','Juan Miguel','Dela Cruz','male','2019-07-02','Purok 1, Brgy. Sample'),
(3,3,'100000000003','STU-DEMO0003','Angela','Reyes','female','2015-11-21','Purok 5, Brgy. Sample'),
(4,4,'100000000004','STU-DEMO0004','Carlo','Reyes','male','2018-05-09','Purok 5, Brgy. Sample'),
(5,5,'100000000005','STU-DEMO0005','Sofia','Villanueva','female','2016-01-30','Purok 2, Brgy. Sample'),
(6,6,'100000000006','STU-DEMO0006','Paolo','Bautista','male','2014-09-17','Purok 4, Brgy. Sample');

INSERT INTO `guardians` (`guardian_id`,`first_name`,`last_name`,`contact_number`,`occupation`) VALUES
(1,'Elena','Santos','0917 000 1111','Teacher'),
(2,'Roberto','Santos','0917 000 1112','Driver'),
(3,'Teresita','Santos','0917 000 1113','Retired'),
(4,'Carmela','Dela Cruz','0918 000 2221','Nurse'),
(5,'Danilo','Dela Cruz','0918 000 2222','Electrician'),
(6,'Rosario','Reyes','0919 000 3331','Store owner'),
(7,'Ernesto','Reyes','0919 000 3332','Farmer'),
(8,'Patricia','Villanueva','0920 000 4441','Accountant'),
(9,'Manuel','Villanueva','0920 000 4442','Engineer'),
(10,'Cynthia','Bautista','0921 000 5551','Vendor'),
(11,'Ramon','Bautista','0921 000 5552','Unknown');

-- Rosario and Ernesto Reyes are linked to BOTH Reyes siblings.
-- Ramon Bautista is registered but NOT authorized (is_authorized = 0).
INSERT INTO `student_guardians` (`student_id`,`guardian_id`,`relationship`,`is_authorized`) VALUES
(1,1,'Mother',1),(1,2,'Father',1),(1,3,'Grandmother',1),
(2,4,'Mother',1),(2,5,'Father',1),
(3,6,'Mother',1),(3,7,'Uncle',1),
(4,6,'Mother',1),(4,7,'Uncle',1),
(5,8,'Mother',1),(5,9,'Father',1),
(6,10,'Mother',1),(6,11,'Father',0);

-- This morning's drop-offs (Paolo has no time-in, to show the warning).
INSERT INTO `gate_logs` (`student_id`,`action`,`recorded_by`,`student_name`,`logged_at`) VALUES
(1,'TIME_IN',2,'Maria Clara Santos',TIMESTAMP(CURDATE(),'07:12:00')),
(2,'TIME_IN',2,'Juan Miguel Dela Cruz',TIMESTAMP(CURDATE(),'07:25:00')),
(3,'TIME_IN',2,'Angela Reyes',TIMESTAMP(CURDATE(),'07:31:00')),
(4,'TIME_IN',2,'Carlo Reyes',TIMESTAMP(CURDATE(),'07:31:00')),
(5,'TIME_IN',2,'Sofia Villanueva',TIMESTAMP(CURDATE(),'07:48:00'));

INSERT INTO `attendance` (`student_id`,`recorded_by`,`attendance_date`,`status`) VALUES
(1,2,CURDATE(),'present'),(2,2,CURDATE(),'present'),(3,2,CURDATE(),'present'),
(4,2,CURDATE(),'present'),(5,2,CURDATE(),'present');
