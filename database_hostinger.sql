-- ========================================================
-- Hostinger-Compatible Database Dump: naap_org_system
-- Generated on: 2026-08-25 15:44:57
-- No DEFINER clauses (works seamlessly on shared hosts)
-- ========================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+08:00';

-- --------------------------------------------------------
-- Structure for table `admin`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin` (
  `AdminId` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `PasswordHash` varchar(255) DEFAULT NULL,
  `Role` varchar(50) NOT NULL DEFAULT 'SuperAdmin',
  `Status` varchar(20) NOT NULL DEFAULT 'active',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`AdminId`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `admin`
INSERT INTO `admin` (`AdminId`, `Name`, `Email`, `PasswordHash`, `Role`, `Status`, `CreatedAt`, `UpdatedAt`) VALUES ('1', 'System Administrator', 'admin@naap.edu.ph', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy', 'SuperAdmin', 'active', '2026-07-30 15:40:22', '2026-08-01 12:25:48');

-- --------------------------------------------------------
-- Structure for table `announcement`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `announcement`;
CREATE TABLE `announcement` (
  `AnnouncementId` int(11) NOT NULL AUTO_INCREMENT,
  `OrgId` int(11) DEFAULT NULL,
  `Title` varchar(255) DEFAULT NULL,
  `Body` text DEFAULT NULL,
  `Category` varchar(100) DEFAULT NULL,
  `Audience` varchar(100) DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `DatePosted` date DEFAULT NULL,
  `ExpirationDate` date DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`AnnouncementId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `assessment_answers`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assessment_answers`;
CREATE TABLE `assessment_answers` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` enum('A','B','C','D') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `score` int(11) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`answer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `assessment_questions`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assessment_questions`;
CREATE TABLE `assessment_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` enum('A','B','C','D') NOT NULL,
  `points` int(11) DEFAULT 1,
  PRIMARY KEY (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `assessment_responses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assessment_responses`;
CREATE TABLE `assessment_responses` (
  `response_id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 0,
  `total_points` int(11) DEFAULT 0,
  `answers_json` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`response_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `assessments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `assessments`;
CREATE TABLE `assessments` (
  `assessment_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('pretest','posttest') NOT NULL,
  `instructions` text DEFAULT NULL,
  `time_limit` int(11) DEFAULT 30,
  `status` enum('draft','published','closed') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `test_type` varchar(50) DEFAULT 'pretest',
  PRIMARY KEY (`assessment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `attendance`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `AttendanceId` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) DEFAULT NULL,
  `EventId` int(11) DEFAULT NULL,
  `ScanType` varchar(255) DEFAULT NULL,
  `Timestamp` datetime DEFAULT current_timestamp(),
  `AttendanceStatus` varchar(50) DEFAULT NULL,
  `LogType` varchar(20) DEFAULT 'Log In',
  `PresenceChecksPassed` int(11) NOT NULL DEFAULT 0,
  `PresenceChecksMissed` int(11) NOT NULL DEFAULT 0,
  `LastPresenceCheckAt` datetime DEFAULT NULL,
  PRIMARY KEY (`AttendanceId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `auditlog`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `auditlog`;
CREATE TABLE `auditlog` (
  `AuditId` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) DEFAULT NULL,
  `ActorType` varchar(20) DEFAULT 'student',
  `ActorId` int(11) DEFAULT NULL,
  `ActorName` varchar(255) DEFAULT NULL,
  `Action` varchar(255) DEFAULT NULL,
  `Details` text DEFAULT NULL,
  `Status` varchar(10) NOT NULL DEFAULT 'success',
  `IpAddress` varchar(45) DEFAULT NULL,
  `Date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`AuditId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `certificate_backup`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `certificate_backup`;
CREATE TABLE `certificate_backup` (
  `CertificateId` int(11) NOT NULL,
  `EventId` int(11) NOT NULL,
  `UserId` int(11) NOT NULL,
  `DateIssued` date DEFAULT NULL,
  `CertificateURL` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `certificate_templates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `certificate_templates`;
CREATE TABLE `certificate_templates` (
  `TemplateId` int(11) NOT NULL AUTO_INCREMENT,
  `OrgId` int(11) NOT NULL,
  `EventId` int(11) DEFAULT NULL,
  `TemplateName` varchar(200) NOT NULL,
  `TemplateImage` varchar(500) NOT NULL,
  `FieldConfig` longtext NOT NULL DEFAULT '[]',
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `IsDeleted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`TemplateId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `certificates`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
  `CertId` int(11) NOT NULL AUTO_INCREMENT,
  `TemplateId` int(11) DEFAULT NULL,
  `EventId` int(11) NOT NULL,
  `UserId` int(11) NOT NULL,
  `CertCode` varchar(64) NOT NULL,
  `GeneratedImage` varchar(500) DEFAULT NULL,
  `IssuedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `OrgId` int(11) DEFAULT NULL,
  `CertificateURL` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`CertId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `certificatetemplate`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `certificatetemplate`;
CREATE TABLE `certificatetemplate` (
  `TemplateId` int(11) NOT NULL,
  `OrgId` int(11) NOT NULL,
  `EventId` int(11) DEFAULT NULL,
  `TemplateName` varchar(255) NOT NULL,
  `TemplateImage` varchar(255) NOT NULL,
  `NameX` float DEFAULT 50,
  `NameY` float DEFAULT 50,
  `FontSize` int(11) DEFAULT 60,
  `FontColor` varchar(50) DEFAULT '#000000',
  `CreatedAt` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `event`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `event`;
CREATE TABLE `event` (
  `EventId` int(11) NOT NULL AUTO_INCREMENT,
  `OrgId` int(11) DEFAULT NULL,
  `EventName` varchar(255) DEFAULT NULL,
  `EventDescription` text DEFAULT NULL,
  `EventSpeaker` varchar(255) DEFAULT NULL,
  `EventCapacity` int(11) DEFAULT NULL,
  `EventPicture` varchar(500) DEFAULT NULL,
  `EventPlace` varchar(255) DEFAULT NULL,
  `EventDetails` text DEFAULT NULL,
  `EventDateTime` datetime DEFAULT NULL,
  `EndDateTime` datetime DEFAULT NULL,
  `EventLocation` varchar(255) DEFAULT NULL,
  `EventStatus` varchar(50) DEFAULT NULL,
  `AttendanceMethod` varchar(100) DEFAULT NULL,
  `EventType` varchar(100) DEFAULT 'General',
  `EventMode` varchar(50) DEFAULT 'On-site',
  `AttendanceEnabled` tinyint(1) DEFAULT 0,
  `AntiSpoofActive` tinyint(1) NOT NULL DEFAULT 0,
  `AntiSpoofTriggeredAt` datetime DEFAULT NULL,
  `AntiSpoofGraceMinutes` int(11) NOT NULL DEFAULT 15,
  `PresenceCheckActive` tinyint(1) NOT NULL DEFAULT 0,
  `PresenceCheckTriggeredAt` datetime DEFAULT NULL,
  `PresenceCheckDurationSec` int(11) NOT NULL DEFAULT 90,
  `NoFinancialReport` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`EventId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `event_posttest`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `event_posttest`;
CREATE TABLE `event_posttest` (
  `TestId` int(11) NOT NULL AUTO_INCREMENT,
  `EventId` int(11) NOT NULL,
  `UserId` int(11) NOT NULL,
  `Q1` varchar(10) DEFAULT NULL,
  `Q2` varchar(10) DEFAULT NULL,
  `Q3` varchar(10) DEFAULT NULL,
  `Q4` varchar(10) DEFAULT NULL,
  `Q5` varchar(10) DEFAULT NULL,
  `Score` int(11) DEFAULT 0,
  `tab_switches` int(11) DEFAULT 0,
  `engagement_score` int(11) DEFAULT 100,
  `monitoring_flagged` tinyint(1) DEFAULT 0,
  `SubmittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TestId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `event_pretest`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `event_pretest`;
CREATE TABLE `event_pretest` (
  `TestId` int(11) NOT NULL AUTO_INCREMENT,
  `EventId` int(11) NOT NULL,
  `UserId` int(11) NOT NULL,
  `Q1` varchar(10) DEFAULT NULL,
  `Q2` varchar(10) DEFAULT NULL,
  `Q3` varchar(10) DEFAULT NULL,
  `Q4` varchar(10) DEFAULT NULL,
  `Q5` varchar(10) DEFAULT NULL,
  `Score` int(11) DEFAULT 0,
  `tab_switches` int(11) DEFAULT 0,
  `engagement_score` int(11) DEFAULT 100,
  `monitoring_flagged` tinyint(1) DEFAULT 0,
  `SubmittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`TestId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `event_report`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `event_report`;
CREATE TABLE `event_report` (
  `ReportId` int(11) NOT NULL AUTO_INCREMENT,
  `EventId` int(11) NOT NULL,
  `OrgId` int(11) NOT NULL,
  `TotalAttendees` int(11) DEFAULT 0,
  `TotalMembers` int(11) DEFAULT 0,
  `AttendanceRate` decimal(5,2) DEFAULT 0.00,
  `Notes` text DEFAULT NULL,
  `SubmittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ReportId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `eventregistration`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `eventregistration`;
CREATE TABLE `eventregistration` (
  `RegistrationId` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) DEFAULT NULL,
  `EventId` int(11) DEFAULT NULL,
  `OrgId` int(11) DEFAULT NULL,
  `DateIssued` date DEFAULT NULL,
  `CertificateURL` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`RegistrationId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `face_data`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `face_data`;
CREATE TABLE `face_data` (
  `FaceId` int(11) NOT NULL AUTO_INCREMENT,
  `UserId` int(11) DEFAULT NULL,
  `FaceEmbedding` blob DEFAULT NULL,
  `CreatedOn` datetime DEFAULT current_timestamp(),
  `QRCode` blob DEFAULT NULL,
  PRIMARY KEY (`FaceId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `message`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `message`;
CREATE TABLE `message` (
  `MessageId` int(11) NOT NULL AUTO_INCREMENT,
  `SenderId` int(11) DEFAULT NULL,
  `ReceiverId` int(11) DEFAULT NULL,
  `SenderType` varchar(20) DEFAULT 'organization',
  `ReceiverType` varchar(20) DEFAULT 'osa',
  `Subject` varchar(255) DEFAULT NULL,
  `IsRead` tinyint(1) DEFAULT 0,
  `MessageBody` text DEFAULT NULL,
  `MessageDateTime` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`MessageId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `org_documents`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `org_documents`;
CREATE TABLE `org_documents` (
  `DocId` int(11) NOT NULL AUTO_INCREMENT,
  `OrgId` int(11) NOT NULL,
  `EventId` int(11) DEFAULT NULL,
  `Title` varchar(255) NOT NULL,
  `DocType` varchar(100) DEFAULT 'Other',
  `Description` text DEFAULT NULL,
  `FilePath` varchar(500) NOT NULL,
  `FileSize` varchar(50) DEFAULT NULL,
  `UploadedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`DocId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `org_messages`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `org_messages`;
CREATE TABLE `org_messages` (
  `MessageId` int(11) NOT NULL AUTO_INCREMENT,
  `OrgId` int(11) NOT NULL,
  `SenderType` enum('org','osa') NOT NULL,
  `SenderId` int(11) NOT NULL,
  `Subject` varchar(255) DEFAULT NULL,
  `Message` text NOT NULL,
  `IsRead` tinyint(1) DEFAULT 0,
  `SentAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`MessageId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `organization`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `organization`;
CREATE TABLE `organization` (
  `OrgId` int(11) NOT NULL AUTO_INCREMENT,
  `OrgName` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL COMMENT 'About the organization',
  `OrgPicture` varchar(255) DEFAULT NULL COMMENT 'Relative path to org logo/banner',
  `OrgBanner` varchar(255) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `PasswordHash` varchar(255) DEFAULT NULL COMMENT 'bcrypt hash of org login password',
  `email` varchar(255) DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `Adviser` varchar(255) DEFAULT NULL COMMENT 'Faculty adviser name',
  `DateRegistered` date DEFAULT NULL COMMENT 'Date org was officially registered',
  `OsaId` int(11) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`OrgId`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `organization`
INSERT INTO `organization` (`OrgId`, `OrgName`, `Description`, `OrgPicture`, `OrgBanner`, `username`, `PasswordHash`, `email`, `Status`, `Adviser`, `DateRegistered`, `OsaId`, `password_hash`) VALUES ('1', 'AISERS', 'The Aviation Institute Students\' Educational Research Society (AISERS) is the premier academic organization of the Institute of Aviation and Aerospace Studies. It fosters research, academic excellence, and intellectual growth among aviation students through seminars, workshops, and collaborative projects.', 'assets/img/aisers logo.jpg', 'assets/img/aisers.jpg', 'aisers_admin', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy', 'aisers@naap.edu.ph', 'Active', 'Prof. Maria Santos', '2019-06-15', NULL, NULL);
INSERT INTO `organization` (`OrgId`, `OrgName`, `Description`, `OrgPicture`, `OrgBanner`, `username`, `PasswordHash`, `email`, `Status`, `Adviser`, `DateRegistered`, `OsaId`, `password_hash`) VALUES ('2', 'AMTSO', 'The Aircraft Maintenance Technology Student Organization (AMTSO) is dedicated to the professional development of aircraft maintenance technology students. It organizes hands-on training, industry visits, and technical workshops to complement academic learning.', 'assets/img/amtso logo.jpg', 'assets/img/amtso.png', 'amtso_admin', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy', 'amtso@naap.edu.ph', 'Active', 'Engr. Roberto Cruz', '2018-08-20', NULL, NULL);
INSERT INTO `organization` (`OrgId`, `OrgName`, `Description`, `OrgPicture`, `OrgBanner`, `username`, `PasswordHash`, `email`, `Status`, `Adviser`, `DateRegistered`, `OsaId`, `password_hash`) VALUES ('3', 'AEROATSO', 'The Aerospace and Air Traffic Service Officers (AEROATSO) is an organization for students in the Air Traffic Service program. It aims to develop future air traffic controllers through simulations, drills, and industry exposure programs.', 'assets/img/aeroatso logo.jpg', 'assets/img/aeroatso.png', 'aeroatso_admin', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy', 'aeroatso@naap.edu.ph', 'Active', 'Capt. Josefino Reyes', '2020-03-10', NULL, NULL);
INSERT INTO `organization` (`OrgId`, `OrgName`, `Description`, `OrgPicture`, `OrgBanner`, `username`, `PasswordHash`, `email`, `Status`, `Adviser`, `DateRegistered`, `OsaId`, `password_hash`) VALUES ('4', 'AETSO', 'The Aviation Electronics Technology Student Organization (AETSO) supports students in aviation electronics technology. It bridges the gap between theoretical knowledge and industry practice through collaborative events and technical scholarships.', 'assets/img/aetso logo.jpg', 'assets/img/aetso.jpg', 'aetso_admin', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy', 'aetso@naap.edu.ph', 'Active', 'Engr. Liza Dela Cruz', '2021-07-05', NULL, NULL);
INSERT INTO `organization` (`OrgId`, `OrgName`, `Description`, `OrgPicture`, `OrgBanner`, `username`, `PasswordHash`, `email`, `Status`, `Adviser`, `DateRegistered`, `OsaId`, `password_hash`) VALUES ('5', 'ELITECH', 'ELITECH is the Electronics and Information Technology Community Hub, catering to students enrolled in electronics and ICT-related programs. It promotes innovation, digital literacy, and peer mentoring among its members.', 'assets/img/elitech logo.jpg', 'assets/img/elitech.png', 'elitech_admin', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy', 'elitech@naap.edu.ph', 'Active', 'Mr Richard Niel Peralta, MSIT', '2017-09-01', NULL, NULL);
INSERT INTO `organization` (`OrgId`, `OrgName`, `Description`, `OrgPicture`, `OrgBanner`, `username`, `PasswordHash`, `email`, `Status`, `Adviser`, `DateRegistered`, `OsaId`, `password_hash`) VALUES ('6', 'ILAS', 'The International Language and Arts Society (ILAS) is a cultural and academic organization dedicated to promoting multilingualism, creative arts, and cultural exchange. ILAS provides a platform for students to explore and celebrate diverse cultures.', 'assets/img/ilas logo.jpg', 'assets/img/ilas.jpg', 'ilas_admin', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy', 'ilas@naap.edu.ph', 'Active', 'Prof. Carla Villanueva', '2016-11-15', NULL, NULL);

-- --------------------------------------------------------
-- Structure for table `orgtype`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `orgtype`;
CREATE TABLE `orgtype` (
  `OrgTypeId` int(11) NOT NULL AUTO_INCREMENT,
  `OrgId` int(11) DEFAULT NULL,
  `Type` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`OrgTypeId`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `orgtype`
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('1', '1', 'Academic');
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('2', '2', 'Technical');
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('3', '3', 'Technical');
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('4', '3', 'Academic');
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('5', '4', 'Technical');
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('6', '5', 'Technology');
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('7', '6', 'Cultural');
INSERT INTO `orgtype` (`OrgTypeId`, `OrgId`, `Type`) VALUES ('8', '6', 'Arts');

-- --------------------------------------------------------
-- Structure for table `osa`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `osa`;
CREATE TABLE `osa` (
  `OsaId` int(11) NOT NULL AUTO_INCREMENT,
  `Name` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `PasswordHash` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`OsaId`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for table `osa`
INSERT INTO `osa` (`OsaId`, `Name`, `Email`, `PasswordHash`) VALUES ('1', 'OSA Administrator', 'osa@naap.edu.ph', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy');
INSERT INTO `osa` (`OsaId`, `Name`, `Email`, `PasswordHash`) VALUES ('2', 'OSA Test Admin', 'OsaTest@email.com', '$2y$10$n31MkedG6BLuDbNqniLpbOH8uAJsZ6tK7Y2qyU6c8h4qmzFS.tQoy');

-- --------------------------------------------------------
-- Structure for table `preposttest`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `preposttest`;
CREATE TABLE `preposttest` (
  `TestId` int(11) NOT NULL AUTO_INCREMENT,
  `EventId` int(11) NOT NULL,
  `StudentId` int(11) NOT NULL,
  `TestType` enum('pre','post') NOT NULL,
  `Score` int(11) DEFAULT 0,
  `CompletedAt` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`TestId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `student_question_responses`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `student_question_responses`;
CREATE TABLE `student_question_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `question_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL,
  `given_answer` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `student_verification_checks`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `student_verification_checks`;
CREATE TABLE `student_verification_checks` (
  `VerificationId` int(11) NOT NULL AUTO_INCREMENT,
  `EventId` int(11) NOT NULL,
  `UserId` int(11) NOT NULL,
  `CheckType` varchar(20) NOT NULL,
  `TriggeredAt` datetime NOT NULL,
  `CompletedAt` datetime NOT NULL,
  PRIMARY KEY (`VerificationId`),
  UNIQUE KEY `verification_once` (`EventId`,`UserId`,`CheckType`,`TriggeredAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Structure for table `user`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `UserId` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL COMMENT 'Student first name',
  `middle_name` varchar(100) DEFAULT NULL COMMENT 'Student middle name',
  `last_name` varchar(100) DEFAULT NULL COMMENT 'Student last name',
  `student_id` varchar(50) DEFAULT NULL COMMENT 'Official student ID number',
  `course` varchar(50) DEFAULT NULL COMMENT 'Course / program',
  `year_level` varchar(20) DEFAULT NULL COMMENT '1st Year … 4th Year',
  `section` varchar(10) DEFAULT NULL COMMENT 'Section number',
  `username` varchar(100) DEFAULT NULL COMMENT 'Chosen login username',
  `phone` varchar(30) DEFAULT NULL COMMENT 'Mobile number',
  `profile_photo` varchar(255) DEFAULT NULL COMMENT 'Relative path to photo',
  `Position` varchar(100) DEFAULT NULL COMMENT 'Officer position e.g. President, VP, Secretary',
  `cor_document` varchar(255) DEFAULT NULL COMMENT 'Relative path to COR file',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | active | disabled',
  `created_at` datetime DEFAULT current_timestamp(),
  `Address` varchar(255) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `PasswordHash` varchar(255) DEFAULT NULL,
  `Role` varchar(100) DEFAULT NULL,
  `OrgId` int(11) DEFAULT NULL,
  `officer_role` varchar(100) DEFAULT NULL,
  `is_officer` tinyint(1) DEFAULT 0,
  `ai_verification_score` int(11) DEFAULT NULL,
  `verification_status` enum('pending','ai_verified','needs_org_review','approved','rejected') DEFAULT 'pending',
  `ai_verification_details` text DEFAULT NULL,
  `Name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`UserId`),
  UNIQUE KEY `Email` (`Email`),
  UNIQUE KEY `uq_user_student_id` (`student_id`),
  UNIQUE KEY `uq_user_username` (`username`),
  KEY `OrgId` (`OrgId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- Stored Procedures (Hostinger-Ready)
-- ========================================================
DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_AddOfficer`$$
CREATE PROCEDURE `sp_AddOfficer`(
    IN p_OrgId INT,
    IN p_FirstName VARCHAR(100),
    IN p_LastName VARCHAR(100),
    IN p_StudentId VARCHAR(100),
    IN p_Email VARCHAR(255),
    IN p_YearLevel VARCHAR(50),
    IN p_Role VARCHAR(100),
    IN p_DefaultPass VARCHAR(255)
)
BEGIN
    DECLARE v_ExistingId INT DEFAULT 0;
    SELECT UserId INTO v_ExistingId FROM `user` WHERE LOWER(Email) = LOWER(p_Email) LIMIT 1;
    
    IF v_ExistingId > 0 THEN
        UPDATE `user` SET OrgId = p_OrgId, officer_role = p_Role, Position = p_Role, is_officer = 1, year_level = p_YearLevel WHERE UserId = v_ExistingId;
    ELSE
        INSERT INTO `user` (first_name, last_name, student_id, Email, PasswordHash, OrgId, officer_role, Position, is_officer, year_level, Status, created_at)
        VALUES (p_FirstName, p_LastName, p_StudentId, p_Email, p_DefaultPass, p_OrgId, p_Role, p_Role, 1, p_YearLevel, 'active', NOW());
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_AdminLogin`$$
CREATE PROCEDURE `sp_AdminLogin`(IN p_Email VARCHAR(255))
BEGIN
    SELECT AdminId, Name, Email, PasswordHash, Role, Status
    FROM `admin`
    WHERE LOWER(Email) = LOWER(p_Email)
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_DeleteAttendance`$$
CREATE PROCEDURE `sp_DeleteAttendance`(
    IN p_EventId INT,
    IN p_UserId INT
)
BEGIN
    DELETE FROM attendance WHERE EventId = p_EventId AND UserId = p_UserId;
END$$

DROP PROCEDURE IF EXISTS `sp_DeleteOrgEvent`$$
CREATE PROCEDURE `sp_DeleteOrgEvent`(IN p_EventId INT, IN p_OrgId INT)
BEGIN
    DELETE FROM attendance WHERE EventId = p_EventId;
    DELETE FROM eventregistration WHERE EventId = p_EventId;
    DELETE FROM certificates WHERE EventId = p_EventId;
    DELETE FROM event WHERE EventId = p_EventId AND OrgId = p_OrgId;
END$$

DROP PROCEDURE IF EXISTS `sp_GetActiveOrganizations`$$
CREATE PROCEDURE `sp_GetActiveOrganizations`()
BEGIN
    SELECT o.*,
           (SELECT COUNT(*) FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = o.OrgId) AS member_count,
           (SELECT COUNT(*) FROM event e WHERE e.OrgId = o.OrgId AND LOWER(e.EventStatus) = 'scheduled') AS event_count
    FROM organization o
    WHERE LOWER(o.Status) = 'active' OR o.Status IS NULL
    ORDER BY o.OrgName ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetAdminDashboard`$$
CREATE PROCEDURE `sp_GetAdminDashboard`()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM `user`) AS total_students,
        (SELECT COUNT(*) FROM `osa`) AS total_osa,
        (SELECT COUNT(*) FROM `organization`) AS total_orgs,
        (SELECT COUNT(*) FROM `admin`) AS total_admins,
        (SELECT COUNT(*) FROM `auditlog` WHERE DATE(`Date`) = CURDATE()) AS today_logs,
        (SELECT COUNT(*) FROM `event`) AS total_events;
END$$

DROP PROCEDURE IF EXISTS `sp_GetAssessmentQuestions`$$
CREATE PROCEDURE `sp_GetAssessmentQuestions`(IN p_AssessmentId INT)
BEGIN
    SELECT 
        q.question_id, 
        q.question_text, 
        q.correct_answer,
        COUNT(sqr.id) AS total_answered,
        SUM(sqr.is_correct) AS total_correct
    FROM assessment_questions q
    LEFT JOIN student_question_responses sqr ON q.question_id = sqr.question_id
    WHERE q.assessment_id = p_AssessmentId
    GROUP BY q.question_id
    ORDER BY q.question_id ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetAssessmentQuestionsDetail`$$
CREATE PROCEDURE `sp_GetAssessmentQuestionsDetail`(IN p_AssessmentId INT)
BEGIN
    SELECT question_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points
    FROM assessment_questions
    WHERE assessment_id = p_AssessmentId
    ORDER BY question_id ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetEventDetail`$$
CREATE PROCEDURE `sp_GetEventDetail`(IN p_EventId INT)
BEGIN
    SELECT e.*, o.OrgName, o.OrgLogo
    FROM event e
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE e.EventId = p_EventId
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgAnnouncements`$$
CREATE PROCEDURE `sp_GetOrgAnnouncements`(IN p_OrgId INT)
BEGIN
    SELECT a.*, o.OrgName
    FROM announcement a
    LEFT JOIN organization o ON o.OrgId = a.OrgId
    WHERE a.OrgId = p_OrgId
    ORDER BY a.DatePosted DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgAssessments`$$
CREATE PROCEDURE `sp_GetOrgAssessments`(IN p_OrgId INT)
BEGIN
    SELECT a.*, e.EventName, e.EventDateTime
    FROM assessments a
    LEFT JOIN event e ON e.EventId = a.event_id
    WHERE a.created_by = p_OrgId OR e.OrgId = p_OrgId
    ORDER BY a.assessment_id DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgAuditTrail`$$
CREATE PROCEDURE `sp_GetOrgAuditTrail`(IN p_OrgId INT)
BEGIN
    SELECT *
    FROM auditlog
    WHERE (ActorType='org' OR ActorType='organization') AND (ActorId = p_OrgId OR UserId = p_OrgId)
    ORDER BY Date DESC
    LIMIT 500;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgCertificates`$$
CREATE PROCEDURE `sp_GetOrgCertificates`(IN p_OrgId INT)
BEGIN
    SELECT c.CertId, c.CertCode, c.IssuedAt, c.GeneratedImage,
           e.EventName, e.EventDateTime,
           u.first_name, u.last_name, u.student_id, u.profile_photo,
           t.TemplateName, t.TemplateImage
    FROM certificates c
    JOIN event e ON e.EventId = c.EventId
    JOIN user u ON u.UserId = c.UserId
    JOIN certificate_templates t ON t.TemplateId = c.TemplateId
    WHERE c.OrgId = p_OrgId
    ORDER BY c.IssuedAt DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgDashboard`$$
CREATE PROCEDURE `sp_GetOrgDashboard`(IN p_OrgId INT)
BEGIN
    SELECT
        (SELECT COUNT(*) FROM `user` WHERE OrgId = p_OrgId) AS total_members,
        (SELECT COUNT(*) FROM event WHERE OrgId = p_OrgId AND EventDateTime >= NOW()) AS upcoming_events,
        (SELECT COUNT(*) FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = p_OrgId) AS total_attendance,
        (SELECT COUNT(*) FROM eventregistration er JOIN event e ON e.EventId = er.EventId WHERE e.OrgId = p_OrgId) AS pending_approvals;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgDocuments`$$
CREATE PROCEDURE `sp_GetOrgDocuments`(IN p_OrgId INT)
BEGIN
    SELECT d.DocId, d.OrgId, d.EventId, d.Title, d.DocType, d.Description, 
           d.FilePath, d.FileSize, d.UploadedAt,
           e.EventName
    FROM org_documents d
    LEFT JOIN event e ON e.EventId = d.EventId
    WHERE d.OrgId = p_OrgId
    ORDER BY d.UploadedAt DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgEvents`$$
CREATE PROCEDURE `sp_GetOrgEvents`(IN p_OrgId INT)
BEGIN
    -- 1. Revert future events back to Scheduled
    UPDATE event 
    SET EventStatus = 'Scheduled' 
    WHERE LOWER(TRIM(COALESCE(EventStatus, 'scheduled'))) IN ('ongoing', 'completed')
      AND EventDateTime > NOW();

    -- 2. Active events -> Ongoing
    UPDATE event 
    SET EventStatus = 'Ongoing' 
    WHERE LOWER(TRIM(COALESCE(EventStatus, 'scheduled'))) IN ('scheduled', 'upcoming')
      AND EventDateTime <= NOW() 
      AND (
          (EndDateTime IS NOT NULL AND EndDateTime > '2000-01-01' AND EndDateTime >= NOW())
          OR ((EndDateTime IS NULL OR EndDateTime <= '2000-01-01') AND EventDateTime >= NOW() - INTERVAL 3 HOUR)
      );

    -- 3. Completed events -> Completed
    UPDATE event 
    SET EventStatus = 'Completed' 
    WHERE LOWER(TRIM(COALESCE(EventStatus, 'ongoing'))) IN ('ongoing', 'scheduled', 'upcoming')
      AND EventDateTime <= NOW()
      AND (
          (EndDateTime IS NOT NULL AND EndDateTime > '2000-01-01' AND EndDateTime < NOW())
          OR ((EndDateTime IS NULL OR EndDateTime <= '2000-01-01') AND EventDateTime < NOW() - INTERVAL 3 HOUR)
      );

    SELECT e.*, o.OrgName,
           (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended_count
    FROM event e
    LEFT JOIN organization o ON e.OrgId = o.OrgId
    WHERE e.OrgId = p_OrgId
    ORDER BY e.EventDateTime DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgMembers`$$
CREATE PROCEDURE `sp_GetOrgMembers`(IN p_OrgId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.student_id, u.Email, 
           u.course, u.year_level, u.section, u.phone, u.profile_photo, 
           u.cor_document, u.cor_document AS CorDocumentUrl,
           u.Position, u.officer_role, u.is_officer, u.status,
           u.verification_status, u.ai_verification_score, u.ai_verification_details,
           u.created_at
    FROM `user` u 
    WHERE u.OrgId = p_OrgId 
    ORDER BY u.first_name ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgMessages`$$
CREATE PROCEDURE `sp_GetOrgMessages`(IN p_OrgId INT)
BEGIN
    SELECT MessageId, OrgId, SenderType, SenderId, Subject, Message, IsRead, SentAt
    FROM org_messages
    WHERE OrgId = p_OrgId
    ORDER BY SentAt ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgOfficers`$$
CREATE PROCEDURE `sp_GetOrgOfficers`(IN p_OrgId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id,
           u.course, u.year_level, u.section, u.phone,
           u.Position AS officer_role, u.profile_photo, u.is_officer
    FROM `user` u 
    WHERE u.OrgId = p_OrgId AND (u.is_officer = 1 OR u.Position IS NOT NULL AND u.Position != '')
    ORDER BY u.first_name ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgReports`$$
CREATE PROCEDURE `sp_GetOrgReports`(IN p_OrgId INT)
BEGIN
    SELECT
        (SELECT COUNT(*) FROM event WHERE OrgId = p_OrgId) AS total_events,
        (SELECT COUNT(*) FROM `user` WHERE OrgId = p_OrgId) AS total_members,
        (SELECT COUNT(*) FROM attendance a JOIN event e ON e.EventId = a.EventId WHERE e.OrgId = p_OrgId) AS total_attendance;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOrgSettings`$$
CREATE PROCEDURE `sp_GetOrgSettings`(IN p_OrgId INT)
BEGIN
    SELECT OrgId, OrgName, Description, OrgPicture, OrgBanner, username AS Username, email AS Email, Status, Adviser, DateRegistered
    FROM organization
    WHERE OrgId = p_OrgId
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOSAAuditTrail`$$
CREATE PROCEDURE `sp_GetOSAAuditTrail`()
BEGIN
    SELECT *
    FROM auditlog
    WHERE ActorType = 'osa' OR ActorType = 'admin'
    ORDER BY Date DESC
    LIMIT 500;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOSADashboard`$$
CREATE PROCEDURE `sp_GetOSADashboard`()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM `user`) AS total_students,
        (SELECT COUNT(*) FROM organization WHERE LOWER(COALESCE(Status, 'active')) = 'active') AS active_orgs,
        (SELECT COUNT(*) FROM organization) AS total_orgs,
        (SELECT COUNT(*) FROM event WHERE LOWER(COALESCE(EventStatus, 'scheduled')) IN ('scheduled','ongoing')) AS upcoming_events,
        (SELECT COUNT(*) FROM certificates) AS total_certs,
        ((SELECT COUNT(*) FROM org_messages WHERE SenderType = 'org' AND IsRead = 0) + (SELECT COUNT(*) FROM announcement WHERE LOWER(TRIM(COALESCE(Status, 'pending'))) = 'pending')) AS unread_count;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOSAEvents`$$
CREATE PROCEDURE `sp_GetOSAEvents`()
BEGIN
    SELECT e.*, o.OrgName
    FROM event e
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    ORDER BY e.EventDateTime DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOSAMessages`$$
CREATE PROCEDURE `sp_GetOSAMessages`(IN p_OrgId INT)
BEGIN
    IF p_OrgId > 0 THEN
        SELECT * FROM org_messages WHERE OrgId = p_OrgId ORDER BY SentAt ASC;
    ELSE
        SELECT * FROM org_messages ORDER BY SentAt DESC LIMIT 200;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOSAOrganizations`$$
CREATE PROCEDURE `sp_GetOSAOrganizations`()
BEGIN
    SELECT o.*,
           (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId) AS members_count,
           (SELECT COUNT(*) FROM user u WHERE u.OrgId = o.OrgId AND u.officer_role IS NOT NULL AND u.officer_role != '') AS officers_count,
           (SELECT CONCAT(u.first_name,' ',u.last_name) FROM user u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%president%' AND LOWER(u.officer_role) NOT LIKE '%vice%' LIMIT 1) AS president_name,
           (SELECT CONCAT(u.first_name,' ',u.last_name) FROM user u WHERE u.OrgId = o.OrgId AND LOWER(u.officer_role) LIKE '%vice%president%' LIMIT 1) AS vp_name,
           (SELECT GROUP_CONCAT(t.Type SEPARATOR ', ') FROM orgtype t WHERE t.OrgId = o.OrgId) AS org_type
    FROM organization o
    ORDER BY o.OrgName ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOSAReports`$$
CREATE PROCEDURE `sp_GetOSAReports`()
BEGIN
    SELECT 
        (SELECT COUNT(*) FROM event WHERE EventStatus = 'Scheduled') AS scheduled_events,
        (SELECT COUNT(*) FROM event WHERE EventStatus = 'Ongoing') AS ongoing_events,
        (SELECT COUNT(*) FROM event WHERE EventStatus = 'Completed') AS completed_events,
        (SELECT COUNT(*) FROM event WHERE EventStatus IN ('Cancelled','Delayed')) AS cancelled_events;
END$$

DROP PROCEDURE IF EXISTS `sp_GetOSAStudents`$$
CREATE PROCEDURE `sp_GetOSAStudents`()
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id,
           u.course, u.year_level, u.section, u.status, u.created_at,
           o.OrgName
    FROM `user` u
    LEFT JOIN organization o ON o.OrgId = u.OrgId
    ORDER BY u.UserId DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetStudentAnnouncements`$$
CREATE PROCEDURE `sp_GetStudentAnnouncements`()
BEGIN
    SELECT a.*, COALESCE(o.OrgName, 'NAAP OSA') AS OrgName
    FROM announcement a
    LEFT JOIN organization o ON a.OrgId = o.OrgId
    WHERE LOWER(TRIM(COALESCE(a.Status, 'approved'))) = 'approved'
    ORDER BY COALESCE(a.DatePosted, a.CreatedAt) DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetStudentCertificates`$$
CREATE PROCEDURE `sp_GetStudentCertificates`(IN p_UserId INT)
BEGIN
    SELECT c.*, e.EventName, e.EventDateTime, o.OrgName
    FROM certificates c
    LEFT JOIN event e ON e.EventId = c.EventId
    LEFT JOIN organization o ON o.OrgId = e.OrgId
    WHERE c.UserId = p_UserId
    ORDER BY c.IssuedAt DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetStudentEvents`$$
CREATE PROCEDURE `sp_GetStudentEvents`()
BEGIN
    -- 1. Revert future events back to Scheduled
    UPDATE event 
    SET EventStatus = 'Scheduled' 
    WHERE LOWER(TRIM(COALESCE(EventStatus, 'scheduled'))) IN ('ongoing', 'completed')
      AND EventDateTime > NOW();

    -- 2. Active events -> Ongoing
    UPDATE event 
    SET EventStatus = 'Ongoing' 
    WHERE LOWER(TRIM(COALESCE(EventStatus, 'scheduled'))) IN ('scheduled', 'upcoming')
      AND EventDateTime <= NOW() 
      AND (
          (EndDateTime IS NOT NULL AND EndDateTime > '2000-01-01' AND EndDateTime >= NOW())
          OR ((EndDateTime IS NULL OR EndDateTime <= '2000-01-01') AND EventDateTime >= NOW() - INTERVAL 3 HOUR)
      );

    -- 3. Completed events -> Completed
    UPDATE event 
    SET EventStatus = 'Completed' 
    WHERE LOWER(TRIM(COALESCE(EventStatus, 'ongoing'))) IN ('ongoing', 'scheduled', 'upcoming')
      AND EventDateTime <= NOW()
      AND (
          (EndDateTime IS NOT NULL AND EndDateTime > '2000-01-01' AND EndDateTime < NOW())
          OR ((EndDateTime IS NULL OR EndDateTime <= '2000-01-01') AND EventDateTime < NOW() - INTERVAL 3 HOUR)
      );

    SELECT e.*, o.OrgName,
           (SELECT COUNT(*) FROM attendance a WHERE a.EventId = e.EventId) AS attended_count,
           (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = e.EventId) AS reg_count
    FROM event e
    LEFT JOIN organization o ON e.OrgId = o.OrgId
    ORDER BY e.EventDateTime DESC;
END$$

DROP PROCEDURE IF EXISTS `sp_GetStudentInfo`$$
CREATE PROCEDURE `sp_GetStudentInfo`(IN p_StudentId VARCHAR(100), IN p_EventId INT)
BEGIN
    SELECT u.UserId, u.first_name, u.last_name, u.Email, u.student_id, 
           u.course, u.year_level, u.section, u.profile_photo, u.phone,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM eventregistration er WHERE er.EventId = p_EventId AND er.UserId = u.UserId), 0) AS is_registered,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId AND a.LogType = 'Log In'), 0) AS has_logged_in,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId AND a.LogType = 'Log Out'), 0) AS has_logged_out,
           IF(p_EventId > 0, (SELECT COUNT(*) FROM attendance a WHERE a.EventId = p_EventId AND a.UserId = u.UserId), 0) AS has_attended
    FROM `user` u 
    WHERE u.student_id = p_StudentId 
       OR u.UserId = CAST(p_StudentId AS UNSIGNED)
       OR u.Email = p_StudentId
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_GetStudentProfile`$$
CREATE PROCEDURE `sp_GetStudentProfile`(IN p_UserId INT)
BEGIN
    SELECT u.*, o.OrgName, o.OrgPicture, o.OrgId AS student_orgid
    FROM `user` u
    LEFT JOIN organization o ON o.OrgId = u.OrgId
    WHERE u.UserId = p_UserId
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_GetStudentProfileDashboard`$$
CREATE PROCEDURE `sp_GetStudentProfileDashboard`(IN p_UserId INT)
BEGIN
    SELECT
        (SELECT COUNT(DISTINCT EventId) FROM eventregistration WHERE UserId = p_UserId) AS reg_events_count,
        (SELECT COUNT(DISTINCT EventId) FROM attendance WHERE UserId = p_UserId) AS attended_events_count,
        (SELECT COUNT(*) FROM certificates WHERE UserId = p_UserId) AS certs_count;
END$$

DROP PROCEDURE IF EXISTS `sp_GetTestResponses`$$
CREATE PROCEDURE `sp_GetTestResponses`(IN p_EventId INT, IN p_TestType VARCHAR(50))
BEGIN
    IF LOWER(p_TestType) LIKE '%pre%' THEN
        SELECT r.Score, r.SubmittedAt, u.first_name, u.last_name, u.Email,
               r.tab_switches, r.engagement_score, r.monitoring_flagged
        FROM event_pretest r 
        JOIN user u ON r.UserId = u.UserId 
        WHERE r.EventId = p_EventId 
        ORDER BY r.Score DESC, r.SubmittedAt ASC;
    ELSE
        SELECT r.Score, r.SubmittedAt, u.first_name, u.last_name, u.Email,
               r.tab_switches, r.engagement_score, r.monitoring_flagged
        FROM event_posttest r 
        JOIN user u ON r.UserId = u.UserId 
        WHERE r.EventId = p_EventId 
        ORDER BY r.Score DESC, r.SubmittedAt ASC;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_GetTestResults`$$
CREATE PROCEDURE `sp_GetTestResults`(IN p_EventId INT, IN p_UserId INT)
BEGIN
    SELECT 
        (SELECT Score FROM event_pretest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS pretest_score,
        (SELECT SubmittedAt FROM event_pretest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS pretest_time,
        (SELECT Score FROM event_posttest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS posttest_score,
        (SELECT SubmittedAt FROM event_posttest WHERE EventId = p_EventId AND UserId = p_UserId ORDER BY SubmittedAt DESC LIMIT 1) AS posttest_time;
END$$

DROP PROCEDURE IF EXISTS `sp_OrgLogin`$$
CREATE PROCEDURE `sp_OrgLogin`(IN p_Username VARCHAR(255))
BEGIN
    SELECT OrgId, OrgName, username, PasswordHash
    FROM organization
    WHERE LOWER(username) = LOWER(p_Username) OR LOWER(email) = LOWER(p_Username)
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_OSALogin`$$
CREATE PROCEDURE `sp_OSALogin`(IN p_Email VARCHAR(255))
BEGIN
    SELECT OsaId, Name, Email, PasswordHash
    FROM `osa`
    WHERE LOWER(Email) = LOWER(p_Email) OR LOWER(Name) = LOWER(p_Email)
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_RecordAttendance`$$
CREATE PROCEDURE `sp_RecordAttendance`(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Method VARCHAR(50),
    IN p_Status VARCHAR(50),
    IN p_LogType VARCHAR(20)
)
BEGIN
    INSERT INTO attendance (EventId, UserId, ScanType, AttendanceStatus, Timestamp, LogType)
    VALUES (p_EventId, p_UserId, p_Method, p_Status, NOW(), p_LogType);
END$$

DROP PROCEDURE IF EXISTS `sp_RegisterStudentEvent`$$
CREATE PROCEDURE `sp_RegisterStudentEvent`(IN p_EventId INT, IN p_StudentId INT)
BEGIN
    DECLARE v_OrgId INT DEFAULT 0;
    SELECT OrgId INTO v_OrgId FROM `event` WHERE EventId = p_EventId LIMIT 1;
    
    IF NOT EXISTS (SELECT 1 FROM `eventregistration` WHERE EventId = p_EventId AND UserId = p_StudentId) THEN
        INSERT INTO `eventregistration` (UserId, EventId, OrgId, DateIssued)
        VALUES (p_StudentId, p_EventId, v_OrgId, CURDATE());
    END IF;
    SELECT 1 AS success, 'Event registration successful' AS message;
END$$

DROP PROCEDURE IF EXISTS `sp_SaveStudentQuestionResponse`$$
CREATE PROCEDURE `sp_SaveStudentQuestionResponse`(
    IN p_AssessmentId INT,
    IN p_StudentId INT,
    IN p_QuestionId INT,
    IN p_IsCorrect INT,
    IN p_GivenAnswer VARCHAR(255)
)
BEGIN
    DELETE FROM student_question_responses WHERE assessment_id = p_AssessmentId AND student_id = p_StudentId AND question_id = p_QuestionId;
    INSERT INTO student_question_responses (assessment_id, student_id, question_id, is_correct, given_answer)
    VALUES (p_AssessmentId, p_StudentId, p_QuestionId, p_IsCorrect, p_GivenAnswer);
END$$

DROP PROCEDURE IF EXISTS `sp_StudentLogin`$$
CREATE PROCEDURE `sp_StudentLogin`(IN p_Email VARCHAR(255))
BEGIN
    SELECT UserId, first_name, last_name, Email, student_id, username, PasswordHash, Status
    FROM `user`
    WHERE LOWER(Email) = LOWER(p_Email) OR LOWER(student_id) = LOWER(p_Email) OR LOWER(username) = LOWER(p_Email)
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS `sp_StudentRegister`$$
CREATE PROCEDURE `sp_StudentRegister`(
    IN p_FirstName VARCHAR(100),
    IN p_MiddleName VARCHAR(100),
    IN p_LastName VARCHAR(100),
    IN p_StudentId VARCHAR(100),
    IN p_Address TEXT,
    IN p_Email VARCHAR(255),
    IN p_Course VARCHAR(100),
    IN p_YearLevel VARCHAR(50),
    IN p_Section VARCHAR(50),
    IN p_Username VARCHAR(100),
    IN p_PassHash VARCHAR(255),
    IN p_Phone VARCHAR(50),
    IN p_ProfilePath VARCHAR(255),
    IN p_CorPath VARCHAR(255)
)
BEGIN
    INSERT INTO `user` (first_name, middle_name, last_name, student_id, Address, Email, course, year_level, section, username, PasswordHash, phone, profile_photo, cor_document, Status, verification_status, Role, created_at)
    VALUES (p_FirstName, p_MiddleName, p_LastName, p_StudentId, p_Address, p_Email, p_Course, p_YearLevel, p_Section, p_Username, p_PassHash, p_Phone, p_ProfilePath, p_CorPath, 'active', 'ai_verified', 'student', NOW());
    SELECT LAST_INSERT_ID() AS new_user_id;
END$$

DROP PROCEDURE IF EXISTS `sp_SubmitPosttest`$$
CREATE PROCEDURE `sp_SubmitPosttest`(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Score INT,
    IN p_TabSwitches INT,
    IN p_EngagementScore INT,
    IN p_MonitoringFlagged INT
)
BEGIN
    INSERT INTO event_posttest (EventId, UserId, Score, tab_switches, engagement_score, monitoring_flagged, SubmittedAt)
    VALUES (p_EventId, p_UserId, p_Score, p_TabSwitches, p_EngagementScore, p_MonitoringFlagged, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        tab_switches = p_TabSwitches,
        engagement_score = p_EngagementScore,
        monitoring_flagged = p_MonitoringFlagged,
        SubmittedAt = NOW();

    INSERT INTO preposttest (EventId, StudentId, TestType, Score, CompletedAt)
    VALUES (p_EventId, p_UserId, 'post', p_Score, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        CompletedAt = NOW();
END$$

DROP PROCEDURE IF EXISTS `sp_SubmitPretest`$$
CREATE PROCEDURE `sp_SubmitPretest`(
    IN p_EventId INT,
    IN p_UserId INT,
    IN p_Score INT,
    IN p_TabSwitches INT,
    IN p_EngagementScore INT,
    IN p_MonitoringFlagged INT
)
BEGIN
    INSERT INTO event_pretest (EventId, UserId, Score, tab_switches, engagement_score, monitoring_flagged, SubmittedAt)
    VALUES (p_EventId, p_UserId, p_Score, p_TabSwitches, p_EngagementScore, p_MonitoringFlagged, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        tab_switches = p_TabSwitches,
        engagement_score = p_EngagementScore,
        monitoring_flagged = p_MonitoringFlagged,
        SubmittedAt = NOW();

    INSERT INTO preposttest (EventId, StudentId, TestType, Score, CompletedAt)
    VALUES (p_EventId, p_UserId, 'pre', p_Score, NOW())
    ON DUPLICATE KEY UPDATE 
        Score = p_Score,
        CompletedAt = NOW();
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateAnnouncement`$$
CREATE PROCEDURE `sp_UpdateAnnouncement`(
    IN p_AnnouncementId INT,
    IN p_Title VARCHAR(255),
    IN p_Body TEXT,
    IN p_Status VARCHAR(50)
)
BEGIN
    UPDATE announcement SET Title = p_Title, Body = p_Body, Status = p_Status WHERE AnnouncementId = p_AnnouncementId;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateAnnouncementStatus`$$
CREATE PROCEDURE `sp_UpdateAnnouncementStatus`(
    IN p_AnnouncementId INT,
    IN p_Status VARCHAR(50)
)
BEGIN
    UPDATE announcement SET Status = p_Status WHERE AnnouncementId = p_AnnouncementId;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateEvent`$$
CREATE PROCEDURE `sp_UpdateEvent`(
    IN p_EventId INT,
    IN p_OrgId INT,
    IN p_EventName VARCHAR(255),
    IN p_EventDescription TEXT,
    IN p_EventDateTime DATETIME,
    IN p_EventLocation VARCHAR(255),
    IN p_EventMode VARCHAR(50),
    IN p_EventCapacity INT,
    IN p_EventSpeaker VARCHAR(255)
)
BEGIN
    UPDATE event 
    SET EventName = p_EventName,
        EventDescription = p_EventDescription,
        EventDateTime = p_EventDateTime,
        EventLocation = p_EventLocation,
        EventMode = p_EventMode,
        EventCapacity = p_EventCapacity,
        EventSpeaker = p_EventSpeaker
    WHERE EventId = p_EventId AND OrgId = p_OrgId;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateOfficerRole`$$
CREATE PROCEDURE `sp_UpdateOfficerRole`(
    IN p_UserId INT,
    IN p_OrgId INT,
    IN p_OfficerRole VARCHAR(100)
)
BEGIN
    IF p_OfficerRole IS NULL OR p_OfficerRole = '' THEN
        UPDATE `user` SET officer_role = NULL, Position = NULL, is_officer = 0 WHERE UserId = p_UserId AND OrgId = p_OrgId;
    ELSE
        UPDATE `user` SET officer_role = p_OfficerRole, Position = p_OfficerRole, is_officer = 1, OrgId = p_OrgId WHERE UserId = p_UserId;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateOrgStudentStatus`$$
CREATE PROCEDURE `sp_UpdateOrgStudentStatus`(
    IN p_UserId INT,
    IN p_OrgId INT,
    IN p_Status VARCHAR(50),
    IN p_VerificationStatus VARCHAR(50)
)
BEGIN
    UPDATE `user` SET status = p_Status, verification_status = p_VerificationStatus WHERE UserId = p_UserId AND OrgId = p_OrgId;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateOSASettings`$$
CREATE PROCEDURE `sp_UpdateOSASettings`(
    IN p_OsaId INT,
    IN p_Name VARCHAR(255),
    IN p_Email VARCHAR(255),
    IN p_PassHash VARCHAR(255)
)
BEGIN
    IF p_PassHash IS NOT NULL AND p_PassHash != '' THEN
        UPDATE `osa` SET Name = p_Name, Email = p_Email, PasswordHash = p_PassHash WHERE OsaId = p_OsaId;
    ELSE
        UPDATE `osa` SET Name = p_Name, Email = p_Email WHERE OsaId = p_OsaId;
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateStudentPassword`$$
CREATE PROCEDURE `sp_UpdateStudentPassword`(
    IN p_UserId INT,
    IN p_Email VARCHAR(255),
    IN p_PassHash VARCHAR(255)
)
BEGIN
    IF p_UserId > 0 THEN
        UPDATE `user` SET PasswordHash = p_PassHash WHERE UserId = p_UserId;
    ELSE
        UPDATE `user` SET PasswordHash = p_PassHash WHERE LOWER(Email) = LOWER(p_Email);
    END IF;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateStudentProfile`$$
CREATE PROCEDURE `sp_UpdateStudentProfile`(
    IN p_UserId INT,
    IN p_FirstName VARCHAR(100),
    IN p_LastName VARCHAR(100),
    IN p_MiddleName VARCHAR(100),
    IN p_Phone VARCHAR(50),
    IN p_Address TEXT,
    IN p_ProfilePhoto VARCHAR(255)
)
BEGIN
    UPDATE `user`
    SET first_name = p_FirstName,
        last_name = p_LastName,
        middle_name = p_MiddleName,
        phone = p_Phone,
        Address = p_Address,
        profile_photo = COALESCE(NULLIF(p_ProfilePhoto, ''), profile_photo)
    WHERE UserId = p_UserId;
END$$

DROP PROCEDURE IF EXISTS `sp_UpdateUserStatus`$$
CREATE PROCEDURE `sp_UpdateUserStatus`(
    IN p_UserId INT,
    IN p_Status VARCHAR(50)
)
BEGIN
    UPDATE `user` SET status = p_Status WHERE UserId = p_UserId;
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
