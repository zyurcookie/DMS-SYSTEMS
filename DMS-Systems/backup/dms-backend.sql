-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 28, 2025 at 06:03 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dms-backend`
--

-- --------------------------------------------------------

--
-- Table structure for table `document`
--

CREATE TABLE `document` (
  `doc_id` int(11) NOT NULL,
  `stud_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `doc_desc` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `created_by` varchar(255) DEFAULT 'Admin',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document`
--

INSERT INTO `document` (`doc_id`, `stud_id`, `file_name`, `doc_type`, `doc_desc`, `status`, `created_by`, `uploaded_at`, `updated_at`) VALUES
(1, 20250001, 'TestFileName', 'TestDocType', 'This is a test document description', 'Approved', 'Admin', '2025-04-28 05:33:22', '2025-04-28 14:55:32'),
(2, 20250002, 'ID Verification', 'ID', 'This is a description for ID Verification', 'Pending', 'Admin', '2025-04-28 06:13:02', '2025-04-28 06:13:02'),
(3, 20250002, 'Bank Statement', 'Accounting', 'Student\'s Missing Balance', 'Pending', 'Admin', '2025-04-28 15:10:58', '2025-04-28 15:10:58');

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
(1, 1, 1),
(2, 1, 5),
(3, 2, 5);

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `stud_id` int(11) NOT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`stud_id`, `name`, `course`, `year_level`, `qpa`, `min_grade`, `is_regular_student`, `took_only_curriculum_courses`, `has_incomplete_grade`, `has_dropped_or_failed`, `violated_rules`, `attendance_percent`, `eligible`, `created_at`, `updated_at`) VALUES
(20250001, 'Ron Jacob Rodanilla', 'BSIT', '4', 4.00, 1.00, 1, 1, 0, 0, 0, 100.00, 1, '2025-04-28 05:32:27', '2025-04-28 05:32:27'),
(20250002, 'John Doe', 'BSIT', '4', 4.00, 1.00, 1, 1, 0, 0, 0, 100.00, 1, '2025-04-28 06:12:19', '2025-04-28 14:50:33');

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
(1, 'Education'),
(2, 'Urgent'),
(3, 'Finance'),
(4, 'Personal'),
(5, 'Pending'),
(6, 'Approved'),
(7, 'Declined');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `stud_id` int(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Student','Admin','Dean','Registrar') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `stud_id`, `email`, `user_name`, `password`, `role`) VALUES
(1, NULL, 'admin@gmail.com', 'Admin', '$2y$10$g2jw9rgEttd7I9zEuILXR.Y2CQ34KkHxIX6FAHeEGq2mOTgK64/MW', 'Admin'),
(2, 20250001, 'ronrodanilla@gmail.com', 'Ron', '$2y$10$WG.nZFw/VaAHNrnotLWWQuFHqAV3BEO7haJyqUwWaWmUv4TFmFfMe', 'Student'),
(3, NULL, 'dean@gmail.com', 'Dean', '$2y$10$evMO2r6mEO5TW2IY8XIRZ.ESfD78Wfg94yLv0W6FlxZVc9nK4N6Bm', 'Dean'),
(4, 20250002, 'johndoe@gmail.com', 'John Doe', '$2y$10$ip3m4MeQNAg5Nk4SwehiluVvY3Boj0ckFFTjXnnU8UeAf51scS2PW', 'Student');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `document`
--
ALTER TABLE `document`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `idxDocStud` (`stud_id`);

--
-- Indexes for table `document_tags`
--
ALTER TABLE `document_tags`
  ADD PRIMARY KEY (`doc_tag_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`stud_id`),
  ADD KEY `idx_student_name` (`name`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`tag_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_stud_id` (`stud_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `document`
--
ALTER TABLE `document`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_tags`
--
ALTER TABLE `document_tags`
  MODIFY `doc_tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `stud_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20250003;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `tag_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `document_tags`
--
ALTER TABLE `document_tags`
  ADD CONSTRAINT `document_tags_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `document` (`doc_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`tag_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
