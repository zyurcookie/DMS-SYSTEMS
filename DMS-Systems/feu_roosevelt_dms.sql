-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 17, 2025 at 02:56 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `feu_roosevelt_dms`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`audit_id`, `user_id`, `action`, `table_name`, `record_id`, `ip_address`, `timestamp`) VALUES
(1, 3, 'User Login', 'user', 3, '::1', '2025-11-15 09:59:16'),
(2, 4, 'User Login', 'user', 4, '::1', '2025-11-15 10:01:33'),
(3, 3, 'User Login', 'user', 3, '::1', '2025-11-15 10:07:21'),
(4, 1, 'User Login', 'user', 1, '::1', '2025-11-15 10:08:38'),
(5, 1, 'User Login', 'user', 1, '::1', '2025-11-15 14:29:21'),
(6, 1, 'User Login', 'user', 1, '::1', '2025-11-15 23:28:38'),
(7, 1, 'User Login', 'user', 1, '::1', '2025-11-15 23:33:37'),
(8, 1, 'User Login', 'user', 1, '::1', '2025-11-15 23:48:44'),
(9, 3, 'User Login', 'user', 3, '::1', '2025-11-16 07:28:36'),
(10, 1, 'User Login', 'user', 1, '::1', '2025-11-16 07:30:20'),
(11, 3, 'User Login', 'user', 3, '::1', '2025-11-16 07:31:52'),
(12, 3, 'User Login', 'user', 3, '::1', '2025-11-16 07:36:32'),
(13, 5, 'User Login', 'user', 5, '::1', '2025-11-16 07:54:22'),
(14, 1, 'User Login', 'user', 1, '::1', '2025-11-16 08:35:06'),
(15, 3, 'User Login', 'user', 3, '::1', '2025-11-16 10:03:04'),
(16, 5, 'User Login', 'user', 5, '::1', '2025-11-16 11:40:52'),
(17, 3, 'User Login', 'user', 3, '::1', '2025-11-16 11:51:20'),
(18, 2, 'User Login', 'user', 2, '::1', '2025-11-16 13:58:43'),
(19, 3, 'User Login', 'user', 3, '::1', '2025-11-16 14:19:56'),
(20, 1, 'User Login', 'user', 1, '::1', '2025-11-16 17:06:22'),
(21, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:22:18'),
(22, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:22:25'),
(23, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:23:09'),
(24, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:23:14'),
(25, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:23:21'),
(26, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:23:26'),
(27, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:24:20'),
(28, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:25:49'),
(29, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:25:53'),
(30, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:30:41'),
(31, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:30:51'),
(32, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:40:29'),
(33, 6, 'User Login', 'user', 6, '::1', '2025-11-16 20:40:33'),
(34, 3, 'User Login', 'user', 3, '::1', '2025-11-16 20:41:01'),
(35, 6, 'User Login', 'user', 6, '::1', '2025-11-16 22:38:59');

-- --------------------------------------------------------

--
-- Table structure for table `dean_list`
--

CREATE TABLE `dean_list` (
  `list_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `stud_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester','Summer') NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
  `gpa` decimal(3,2) NOT NULL,
  `qpa` decimal(3,2) DEFAULT NULL,
  `status` enum('Pending','Under Review','Verified','Rejected') DEFAULT 'Pending',
  `remarks` text DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_date` timestamp NULL DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `submitted_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dean_list`
--

INSERT INTO `dean_list` (`list_id`, `student_id`, `stud_id`, `academic_year`, `semester`, `year_level`, `gpa`, `qpa`, `status`, `remarks`, `verified_by`, `verified_date`, `submitted_by`, `submitted_date`, `created_at`) VALUES
(1, 4, 20250001, '2024-2025', '2nd Semester', '3rd Year', 4.00, 4.00, 'Pending', NULL, 3, '2025-11-15 09:57:26', NULL, NULL, '2025-11-15 09:57:26'),
(2, 5, 20250002, '2024-2025', '2nd Semester', '3rd Year', 3.95, 3.95, 'Verified', NULL, 3, '2025-11-15 09:57:26', NULL, NULL, '2025-11-15 09:57:26'),
(3, 4, 20250001, '2024-2025', '1st Semester', '3rd Year', 3.92, 3.92, 'Verified', NULL, 3, '2025-05-15 09:57:26', NULL, NULL, '2025-11-15 09:57:26'),
(4, 4, 20250001, '2025-2026', '1st Semester', '4th Year', 4.00, 4.00, 'Verified', '', 3, '2025-11-16 07:32:57', NULL, NULL, '2025-11-15 09:57:26'),
(5, 5, 20250002, '2025-2026', '1st Semester', '4th Year', 3.87, 3.87, 'Under Review', NULL, NULL, NULL, NULL, NULL, '2025-11-15 09:57:26');

-- --------------------------------------------------------

--
-- Table structure for table `document`
--

CREATE TABLE `document` (
  `doc_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `stud_id` int(11) NOT NULL,
  `doc_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `doc_desc` text DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `related_type` enum('enrollment','dean_list','scholarship','general') DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Declined') DEFAULT 'Pending',
  `comments` text DEFAULT NULL,
  `created_by` varchar(255) DEFAULT 'Student',
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document`
--

INSERT INTO `document` (`doc_id`, `student_id`, `stud_id`, `doc_name`, `file_name`, `doc_type`, `doc_desc`, `file_path`, `file`, `file_size`, `related_type`, `related_id`, `status`, `comments`, `created_by`, `upload_date`, `uploaded_at`, `reviewed_by`, `review_date`, `updated_at`) VALUES
(1, 4, 20250001, 'Final Grades 2024-2025', 'grades_ron_2024_2025.pdf', 'Grades', 'Final grades for dean\'s list verification', NULL, NULL, NULL, 'dean_list', NULL, 'Approved', '', 'Ron', '2025-11-15 09:57:26', '2025-11-15 09:57:26', 3, '2025-11-16 15:28:01', '2025-11-16 15:28:01'),
(2, 5, 20250002, 'Student ID Copy', 'id_john_doe.pdf', 'ID', 'Valid student ID', NULL, NULL, NULL, 'scholarship', NULL, 'Approved', '', 'John Doe', '2025-11-15 09:57:26', '2025-11-15 09:57:26', 3, '2025-11-16 15:30:46', '2025-11-16 15:30:46'),
(3, 4, 20250001, 'Scholarship Application Form', 'scholarship_form_ron.pdf', 'Certificate', 'Completed scholarship application', NULL, NULL, NULL, 'scholarship', NULL, 'Approved', '', 'Ron', '2025-11-15 09:57:26', '2025-11-15 09:57:26', 3, '2025-11-16 15:28:10', '2025-11-16 15:28:10');

-- --------------------------------------------------------

--
-- Table structure for table `document_tags`
--

CREATE TABLE `document_tags` (
  `doc_tag_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_tags`
--

INSERT INTO `document_tags` (`doc_tag_id`, `doc_id`, `tag_id`) VALUES
(2, 1, 6),
(1, 1, 8),
(3, 1, 11),
(5, 2, 5),
(4, 2, 9),
(7, 3, 6),
(6, 3, 9);

-- --------------------------------------------------------

--
-- Table structure for table `guidance_assessment`
--

CREATE TABLE `guidance_assessment` (
  `assessment_id` int(11) NOT NULL,
  `app_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assessed_by` int(11) NOT NULL,
  `financial_need_score` int(2) DEFAULT NULL COMMENT 'Score 1-10',
  `character_score` int(2) DEFAULT NULL COMMENT 'Score 1-10',
  `leadership_score` int(2) DEFAULT NULL COMMENT 'Score 1-10',
  `personal_circumstances` text DEFAULT NULL,
  `assessment_notes` text DEFAULT NULL,
  `overall_recommendation` enum('Strongly Recommended','Recommended','Conditionally Recommended','Not Recommended') DEFAULT NULL,
  `priority_level` enum('High','Medium','Low') DEFAULT 'Medium',
  `recommended_amount` decimal(10,2) DEFAULT NULL,
  `assessment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guidance_assessment`
--

INSERT INTO `guidance_assessment` (`assessment_id`, `app_id`, `student_id`, `assessed_by`, `financial_need_score`, `character_score`, `leadership_score`, `personal_circumstances`, `assessment_notes`, `overall_recommendation`, `priority_level`, `recommended_amount`, `assessment_date`, `updated_at`) VALUES
(1, 2, 5, 6, 8, 9, 7, 'Student demonstrates genuine financial need. Family income below threshold.', 'Excellent character references. Active in community service. Recommended for full scholarship.', 'Strongly Recommended', 'High', 50000.00, '2025-11-16 22:38:21', '2025-11-16 22:38:21');

-- --------------------------------------------------------

--
-- Table structure for table `guidance_interview`
--

CREATE TABLE `guidance_interview` (
  `interview_id` int(11) NOT NULL,
  `app_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `conducted_by` int(11) NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time DEFAULT NULL,
  `interview_type` enum('Initial','Follow-up','Verification','Counseling') DEFAULT 'Initial',
  `interview_reason` text DEFAULT NULL,
  `interview_findings` text DEFAULT NULL,
  `verified_information` text DEFAULT NULL,
  `student_demeanor` text DEFAULT NULL,
  `follow_up_needed` tinyint(1) DEFAULT 0,
  `follow_up_notes` text DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled','No Show') DEFAULT 'Scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

CREATE TABLE `profile` (
  `profile_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stud_id` int(11) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `firstName` varchar(100) NOT NULL,
  `lastName` varchar(100) NOT NULL,
  `middleName` varchar(100) DEFAULT NULL,
  `name` varchar(255) GENERATED ALWAYS AS (concat(`firstName`,' ',ifnull(concat(`middleName`,' '),''),`lastName`)) STORED,
  `course` varchar(255) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') DEFAULT NULL,
  `contactNumber` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profile`
--

INSERT INTO `profile` (`profile_id`, `user_id`, `stud_id`, `student_number`, `firstName`, `lastName`, `middleName`, `course`, `year_level`, `contactNumber`, `address`, `created_at`, `updated_at`) VALUES
(1, 4, 20250001, '20250001', 'Ron Jacob', 'Rodanilla', NULL, 'BSIT', '4th Year', '09123456789', 'Quezon City', '2025-11-15 09:57:26', '2025-11-15 09:57:26'),
(2, 5, 20250002, '20250002', 'John', 'Doe', NULL, 'BSIT', '4th Year', '09187654321', 'Marikina City', '2025-11-15 09:57:26', '2025-11-15 09:57:26');

-- --------------------------------------------------------

--
-- Table structure for table `scholarship_application`
--

CREATE TABLE `scholarship_application` (
  `app_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `stud_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester','Summer') NOT NULL,
  `scholarship_type` varchar(255) NOT NULL,
  `status` enum('Submitted','Under Review','Recommended','Under Guidance Review','On Hold - Guidance Review','Endorsed by Guidance','Not Endorsed','Approved','Rejected','On Hold') DEFAULT 'Submitted',
  `remarks` text DEFAULT NULL,
  `guidance_remarks` text DEFAULT NULL,
  `guidance_recommendation` enum('Endorsed','Not Endorsed','Needs Interview','Pending') DEFAULT 'Pending',
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `guidance_reviewed_by` int(11) DEFAULT NULL,
  `review_date` timestamp NULL DEFAULT NULL,
  `guidance_review_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `scholarship_application`
--

INSERT INTO `scholarship_application` (`app_id`, `student_id`, `stud_id`, `academic_year`, `semester`, `scholarship_type`, `status`, `remarks`, `guidance_remarks`, `guidance_recommendation`, `application_date`, `reviewed_by`, `guidance_reviewed_by`, `review_date`, `guidance_review_date`) VALUES
(1, 4, 20250001, '2025-2026', '1st Semester', 'Dean\'s List Scholar', 'Approved', NULL, NULL, 'Pending', '2025-11-15 09:57:26', 1, NULL, NULL, NULL),
(2, 5, 20250002, '2025-2026', '1st Semester', 'Academic Excellence', 'Under Review', NULL, NULL, 'Pending', '2025-11-15 09:57:26', NULL, NULL, NULL, NULL),
(3, 4, 20250001, '2024-2025', '2nd Semester', 'Dean\'s List Scholar', 'Approved', NULL, NULL, 'Pending', '2025-11-15 09:57:26', 1, NULL, NULL, NULL),
(4, 5, 20250002, '2025-2026', '1st Semester', 'Need-Based Scholarship', 'Recommended', 'Recommended by Dean for Guidance review', NULL, 'Pending', '2025-11-16 22:38:21', 2, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `id` int(11) NOT NULL,
  `stud_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `course` varchar(255) NOT NULL,
  `year_level` varchar(50) NOT NULL,
  `qpa` decimal(3,2) DEFAULT NULL,
  `min_grade` decimal(3,2) DEFAULT NULL,
  `is_regular_student` tinyint(1) DEFAULT 1,
  `took_only_curriculum_courses` tinyint(1) DEFAULT 1,
  `has_incomplete_grade` tinyint(1) DEFAULT 0,
  `has_dropped_or_failed` tinyint(1) DEFAULT 0,
  `violated_rules` tinyint(1) DEFAULT 0,
  `attendance_percent` decimal(5,2) DEFAULT NULL,
  `eligible` tinyint(1) DEFAULT NULL,
  `eligibility_status` varchar(50) DEFAULT 'Pending',
  `requirements` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`id`, `stud_id`, `user_id`, `name`, `course`, `year_level`, `qpa`, `min_grade`, `is_regular_student`, `took_only_curriculum_courses`, `has_incomplete_grade`, `has_dropped_or_failed`, `violated_rules`, `attendance_percent`, `eligible`, `eligibility_status`, `requirements`, `created_at`, `updated_at`) VALUES
(1, 20250001, 4, 'Ron Jacob Rodanilla', 'BSIT', '4th Year', 4.00, 1.00, 1, 1, 0, 0, 0, 100.00, 1, 'Eligible', NULL, '2025-11-15 09:57:26', '2025-11-15 09:57:26'),
(2, 20250002, 5, 'John Doe', 'BSIT', '4th Year', 3.95, 1.00, 1, 1, 0, 0, 0, 98.00, 1, 'Eligible', NULL, '2025-11-15 09:57:26', '2025-11-15 09:57:26');

-- --------------------------------------------------------

--
-- Table structure for table `student_enrollment`
--

CREATE TABLE `student_enrollment` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `stud_id` int(11) NOT NULL,
  `academic_year` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester','Summer') NOT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year') NOT NULL,
  `course` varchar(255) NOT NULL,
  `status` enum('Enrolled','Withdrawn','Completed') DEFAULT 'Enrolled',
  `enrollment_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_enrollment`
--

INSERT INTO `student_enrollment` (`enrollment_id`, `student_id`, `stud_id`, `academic_year`, `semester`, `year_level`, `course`, `status`, `enrollment_date`) VALUES
(1, 4, 20250001, '2025-2026', '1st Semester', '4th Year', 'BSIT', 'Enrolled', '2025-11-15 09:57:26'),
(2, 5, 20250002, '2025-2026', '1st Semester', '4th Year', 'BSIT', 'Enrolled', '2025-11-15 09:57:26'),
(3, 4, 20250001, '2024-2025', '2nd Semester', '3rd Year', 'BSIT', 'Completed', '2025-11-15 09:57:26'),
(4, 5, 20250002, '2024-2025', '2nd Semester', '3rd Year', 'BSIT', 'Completed', '2025-11-15 09:57:26');

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `tag_id` int(11) NOT NULL,
  `tag_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`tag_id`, `tag_name`) VALUES
(12, 'Academic'),
(6, 'Approved'),
(8, 'Dean\'s List'),
(7, 'Declined'),
(1, 'Education'),
(10, 'Enrollment'),
(3, 'Finance'),
(5, 'Pending'),
(4, 'Personal'),
(9, 'Scholarship'),
(2, 'Urgent'),
(11, 'Verified');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `stud_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Student','Admin','Dean','Registrar','Guidance') NOT NULL,
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `stud_id`, `email`, `user_name`, `password`, `role`, `status`, `created_at`, `last_login`) VALUES
(1, NULL, 'admin@feu.edu.ph', 'Admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Active', '2025-11-15 09:57:26', '2025-11-16 17:06:22'),
(2, NULL, 'dean@feu.edu.ph', 'Dean', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dean', 'Active', '2025-11-15 09:57:26', '2025-11-16 13:58:43'),
(3, NULL, 'registrar@feu.edu.ph', 'Registrar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Registrar', 'Active', '2025-11-15 09:57:26', '2025-11-16 20:41:01'),
(4, 20250001, 'ronrodanilla@gmail.com', 'Ron', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Active', '2025-11-15 09:57:26', '2025-11-15 10:01:33'),
(5, 20250002, 'johndoe@gmail.com', 'John Doe', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Student', 'Active', '2025-11-15 09:57:26', '2025-11-16 11:40:52'),
(6, NULL, 'guidance@feu.edu.ph', 'Guidance Office', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Guidance', 'Active', '2025-11-16 20:21:00', '2025-11-16 22:38:59');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_active_scholars`
-- (See below for the actual view)
--
CREATE TABLE `v_active_scholars` (
`app_id` int(11)
,`student_id` int(11)
,`stud_id` int(11)
,`scholarship_type` varchar(255)
,`academic_year` varchar(20)
,`semester` enum('1st Semester','2nd Semester','Summer')
,`student_name` varchar(255)
,`student_number` varchar(50)
,`course` varchar(255)
,`year_level` enum('1st Year','2nd Year','3rd Year','4th Year')
,`contactNumber` varchar(20)
,`email` varchar(255)
,`qpa` decimal(3,2)
,`gpa` decimal(3,2)
,`application_date` timestamp
,`guidance_review_date` timestamp
,`total_interviews` bigint(21)
,`last_interview_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_guidance_applications`
-- (See below for the actual view)
--
CREATE TABLE `v_guidance_applications` (
`app_id` int(11)
,`student_id` int(11)
,`stud_id` int(11)
,`academic_year` varchar(20)
,`semester` enum('1st Semester','2nd Semester','Summer')
,`scholarship_type` varchar(255)
,`status` enum('Submitted','Under Review','Recommended','Under Guidance Review','On Hold - Guidance Review','Endorsed by Guidance','Not Endorsed','Approved','Rejected','On Hold')
,`guidance_recommendation` enum('Endorsed','Not Endorsed','Needs Interview','Pending')
,`application_date` timestamp
,`guidance_reviewed_by` int(11)
,`guidance_review_date` timestamp
,`guidance_remarks` text
,`student_name` varchar(255)
,`student_number` varchar(50)
,`course` varchar(255)
,`year_level` enum('1st Year','2nd Year','3rd Year','4th Year')
,`contactNumber` varchar(20)
,`student_email` varchar(255)
,`qpa` decimal(3,2)
,`eligible` tinyint(1)
,`gpa` decimal(3,2)
,`priority_level` varchar(6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_student_complete`
-- (See below for the actual view)
--
CREATE TABLE `v_student_complete` (
`user_id` int(11)
,`email` varchar(255)
,`account_status` enum('Active','Inactive','Suspended')
,`stud_id` int(11)
,`student_number` varchar(50)
,`firstName` varchar(100)
,`lastName` varchar(100)
,`full_name` varchar(255)
,`course` varchar(255)
,`year_level` enum('1st Year','2nd Year','3rd Year','4th Year')
,`contactNumber` varchar(20)
,`qpa` decimal(3,2)
,`eligible` tinyint(1)
,`eligibility_status` varchar(50)
,`total_enrollments` bigint(21)
,`deans_list_count` bigint(21)
,`scholarship_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `v_active_scholars`
--
DROP TABLE IF EXISTS `v_active_scholars`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_active_scholars`  AS SELECT `sa`.`app_id` AS `app_id`, `sa`.`student_id` AS `student_id`, `sa`.`stud_id` AS `stud_id`, `sa`.`scholarship_type` AS `scholarship_type`, `sa`.`academic_year` AS `academic_year`, `sa`.`semester` AS `semester`, `p`.`name` AS `student_name`, `p`.`student_number` AS `student_number`, `p`.`course` AS `course`, `p`.`year_level` AS `year_level`, `p`.`contactNumber` AS `contactNumber`, `u`.`email` AS `email`, `s`.`qpa` AS `qpa`, `dl`.`gpa` AS `gpa`, `sa`.`application_date` AS `application_date`, `sa`.`guidance_review_date` AS `guidance_review_date`, count(distinct `gi`.`interview_id`) AS `total_interviews`, max(`gi`.`interview_date`) AS `last_interview_date` FROM (((((`scholarship_application` `sa` join `profile` `p` on(`sa`.`stud_id` = `p`.`stud_id`)) join `user` `u` on(`sa`.`student_id` = `u`.`user_id`)) left join `student` `s` on(`sa`.`stud_id` = `s`.`stud_id`)) left join `dean_list` `dl` on(`sa`.`student_id` = `dl`.`student_id` and `sa`.`academic_year` = `dl`.`academic_year` and `sa`.`semester` = `dl`.`semester`)) left join `guidance_interview` `gi` on(`sa`.`app_id` = `gi`.`app_id`)) WHERE `sa`.`status` = 'Approved' GROUP BY `sa`.`app_id` ORDER BY `sa`.`academic_year` DESC, `sa`.`semester` DESC, `p`.`name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_guidance_applications`
--
DROP TABLE IF EXISTS `v_guidance_applications`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_guidance_applications`  AS SELECT `sa`.`app_id` AS `app_id`, `sa`.`student_id` AS `student_id`, `sa`.`stud_id` AS `stud_id`, `sa`.`academic_year` AS `academic_year`, `sa`.`semester` AS `semester`, `sa`.`scholarship_type` AS `scholarship_type`, `sa`.`status` AS `status`, `sa`.`guidance_recommendation` AS `guidance_recommendation`, `sa`.`application_date` AS `application_date`, `sa`.`guidance_reviewed_by` AS `guidance_reviewed_by`, `sa`.`guidance_review_date` AS `guidance_review_date`, `sa`.`guidance_remarks` AS `guidance_remarks`, `p`.`name` AS `student_name`, `p`.`student_number` AS `student_number`, `p`.`course` AS `course`, `p`.`year_level` AS `year_level`, `p`.`contactNumber` AS `contactNumber`, `u`.`email` AS `student_email`, `s`.`qpa` AS `qpa`, `s`.`eligible` AS `eligible`, `dl`.`gpa` AS `gpa`, CASE WHEN `sa`.`scholarship_type` like '%Need-Based%' THEN 'High' WHEN `sa`.`status` = 'On Hold - Guidance Review' THEN 'Urgent' ELSE 'Normal' END AS `priority_level` FROM ((((`scholarship_application` `sa` left join `profile` `p` on(`sa`.`stud_id` = `p`.`stud_id`)) left join `user` `u` on(`sa`.`student_id` = `u`.`user_id`)) left join `student` `s` on(`sa`.`stud_id` = `s`.`stud_id`)) left join `dean_list` `dl` on(`sa`.`student_id` = `dl`.`student_id` and `sa`.`academic_year` = `dl`.`academic_year` and `sa`.`semester` = `dl`.`semester`)) WHERE `sa`.`status` in ('Recommended','Under Guidance Review','On Hold - Guidance Review','Endorsed by Guidance','Not Endorsed') ORDER BY CASE WHEN `sa`.`status` = 'On Hold - Guidance Review' THEN 1 WHEN `sa`.`status` = 'Under Guidance Review' THEN 2 WHEN `sa`.`status` = 'Recommended' THEN 3 ELSE 4 END ASC, `sa`.`application_date` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_student_complete`
--
DROP TABLE IF EXISTS `v_student_complete`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_student_complete`  AS SELECT `u`.`user_id` AS `user_id`, `u`.`email` AS `email`, `u`.`status` AS `account_status`, `p`.`stud_id` AS `stud_id`, `p`.`student_number` AS `student_number`, `p`.`firstName` AS `firstName`, `p`.`lastName` AS `lastName`, `p`.`name` AS `full_name`, `p`.`course` AS `course`, `p`.`year_level` AS `year_level`, `p`.`contactNumber` AS `contactNumber`, `s`.`qpa` AS `qpa`, `s`.`eligible` AS `eligible`, `s`.`eligibility_status` AS `eligibility_status`, count(distinct `e`.`enrollment_id`) AS `total_enrollments`, count(distinct `dl`.`list_id`) AS `deans_list_count`, count(distinct `sa`.`app_id`) AS `scholarship_count` FROM (((((`user` `u` join `profile` `p` on(`u`.`user_id` = `p`.`user_id`)) left join `student` `s` on(`p`.`stud_id` = `s`.`stud_id`)) left join `student_enrollment` `e` on(`u`.`user_id` = `e`.`student_id`)) left join `dean_list` `dl` on(`u`.`user_id` = `dl`.`student_id` and `dl`.`status` = 'Verified')) left join `scholarship_application` `sa` on(`u`.`user_id` = `sa`.`student_id`)) WHERE `u`.`role` = 'Student' GROUP BY `u`.`user_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_audit` (`user_id`,`timestamp`);

--
-- Indexes for table `dean_list`
--
ALTER TABLE `dean_list`
  ADD PRIMARY KEY (`list_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `submitted_by` (`submitted_by`),
  ADD KEY `idx_deans_list` (`student_id`,`academic_year`,`semester`),
  ADD KEY `idx_stud_id` (`stud_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `document`
--
ALTER TABLE `document`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_document` (`student_id`,`related_type`),
  ADD KEY `idx_stud_id` (`stud_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `document_tags`
--
ALTER TABLE `document_tags`
  ADD PRIMARY KEY (`doc_tag_id`),
  ADD UNIQUE KEY `unique_doc_tag` (`doc_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `guidance_assessment`
--
ALTER TABLE `guidance_assessment`
  ADD PRIMARY KEY (`assessment_id`),
  ADD KEY `idx_app_id` (`app_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_assessed_by` (`assessed_by`),
  ADD KEY `idx_overall_recommendation` (`overall_recommendation`),
  ADD KEY `idx_priority` (`priority_level`);

--
-- Indexes for table `guidance_interview`
--
ALTER TABLE `guidance_interview`
  ADD PRIMARY KEY (`interview_id`),
  ADD KEY `idx_app_id` (`app_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_conducted_by` (`conducted_by`),
  ADD KEY `idx_interview_date` (`interview_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `profile`
--
ALTER TABLE `profile`
  ADD PRIMARY KEY (`profile_id`),
  ADD UNIQUE KEY `stud_id` (`stud_id`),
  ADD UNIQUE KEY `student_number` (`student_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_student_number` (`student_number`),
  ADD KEY `idx_stud_id` (`stud_id`),
  ADD KEY `idx_name` (`lastName`,`firstName`);

--
-- Indexes for table `scholarship_application`
--
ALTER TABLE `scholarship_application`
  ADD PRIMARY KEY (`app_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_scholarship` (`student_id`,`academic_year`,`semester`),
  ADD KEY `idx_stud_id` (`stud_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_guidance_status` (`status`,`guidance_recommendation`),
  ADD KEY `idx_guidance_reviewed` (`guidance_reviewed_by`,`guidance_review_date`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stud_id` (`stud_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_stud_id` (`stud_id`),
  ADD KEY `idx_student_name` (`name`);

--
-- Indexes for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `idx_enrollment` (`student_id`,`academic_year`,`semester`),
  ADD KEY `idx_stud_id` (`stud_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`tag_id`),
  ADD UNIQUE KEY `tag_name` (`tag_name`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_stud_id` (`stud_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `dean_list`
--
ALTER TABLE `dean_list`
  MODIFY `list_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document`
--
ALTER TABLE `document`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_tags`
--
ALTER TABLE `document_tags`
  MODIFY `doc_tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `guidance_assessment`
--
ALTER TABLE `guidance_assessment`
  MODIFY `assessment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `guidance_interview`
--
ALTER TABLE `guidance_interview`
  MODIFY `interview_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `profile_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `scholarship_application`
--
ALTER TABLE `scholarship_application`
  MODIFY `app_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `dean_list`
--
ALTER TABLE `dean_list`
  ADD CONSTRAINT `dean_list_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dean_list_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dean_list_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `document`
--
ALTER TABLE `document`
  ADD CONSTRAINT `document_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `document_tags`
--
ALTER TABLE `document_tags`
  ADD CONSTRAINT `document_tags_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `document` (`doc_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE;

--
-- Constraints for table `guidance_assessment`
--
ALTER TABLE `guidance_assessment`
  ADD CONSTRAINT `guidance_assessment_ibfk_1` FOREIGN KEY (`app_id`) REFERENCES `scholarship_application` (`app_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guidance_assessment_ibfk_2` FOREIGN KEY (`assessed_by`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `guidance_interview`
--
ALTER TABLE `guidance_interview`
  ADD CONSTRAINT `guidance_interview_ibfk_1` FOREIGN KEY (`app_id`) REFERENCES `scholarship_application` (`app_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `guidance_interview_ibfk_2` FOREIGN KEY (`conducted_by`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `profile`
--
ALTER TABLE `profile`
  ADD CONSTRAINT `profile_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `scholarship_application`
--
ALTER TABLE `scholarship_application`
  ADD CONSTRAINT `fk_guidance_reviewer` FOREIGN KEY (`guidance_reviewed_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `scholarship_application_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `scholarship_application_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_enrollment`
--
ALTER TABLE `student_enrollment`
  ADD CONSTRAINT `student_enrollment_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
