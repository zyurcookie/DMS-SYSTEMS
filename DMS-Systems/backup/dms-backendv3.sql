-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 02, 2025 at 08:03 PM
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
  `file` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Declined') DEFAULT 'Pending',
  `created_by` varchar(255) DEFAULT 'Admin',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document`
--

INSERT INTO `document` (`doc_id`, `stud_id`, `file_name`, `doc_type`, `doc_desc`, `file`, `status`, `created_by`, `uploaded_at`, `updated_at`) VALUES
(1, 20250001, 'TestFileName', 'Certificate', 'This is a test document description', '', 'Approved', 'Admin', '2025-04-28 05:33:22', '2025-05-02 14:06:28'),
(2, 20250002, 'ID Verification', 'ID', 'This is a description for ID Verification', '', 'Pending', 'Admin', '2025-04-28 06:13:02', '2025-04-28 06:13:02'),
(3, 20250002, 'Bank Statement', 'Accounting', 'Student\'s Missing Balance', '', 'Pending', 'Admin', '2025-04-28 15:10:58', '2025-04-28 15:10:58'),
(4, 20250001, 'Senior Highschool Card', 'Grades', 'SHS Card', 'uploads/images.jpg', 'Pending', 'Admin', '2025-05-02 13:39:53', '2025-05-02 14:06:56'),
(5, 20250002, 'Senior Highschool Card', 'Grades', 'John Grades', 'uploads/bai.png', 'Pending', 'Admin', '2025-05-02 13:46:18', '2025-05-02 14:06:50'),
(6, 20250001, 'Junior Highschool Card', 'Grades', 'JHS Card', 'uploads/ronjacob.png', 'Pending', 'Admin', '2025-05-02 13:50:00', '2025-05-02 13:50:00'),
(7, 20250001, '2024 Payment', 'Other', 'Payment for 2024', 'uploads/GRADPIC(CITE).jpg', 'Pending', 'Accounting', '2025-05-02 14:02:10', '2025-05-02 17:05:10'),
(8, 20250002, '2024 Payment', 'Other', 'Payment for 2024', 'uploads/GRADPIC(CITE).jpg', 'Pending', 'Accounting', '2025-05-02 14:04:21', '2025-05-02 17:05:17'),
(9, 20250001, 'Senior Highschool Card', 'Grades', 'SHS Card', 'uploads/ronjacob.png', 'Pending', 'Accounting', '2025-05-02 14:08:36', '2025-05-02 14:08:36'),
(10, 20250001, 'College Grades', 'Grades', 'College Card', 'uploads/GRADPIC(CITE).jpg', 'Pending', 'accounting@gmail.com', '2025-05-02 14:10:40', '2025-05-02 17:05:29'),
(11, 20250002, 'College Card', 'Grades', 'College Card', 'uploads/images.jpg', 'Pending', 'accounting@gmail.com', '2025-05-02 14:10:57', '2025-05-02 17:05:35'),
(12, 20250001, 'asdasd', 'Certificate', 'asdasdas', 'uploads/doc_6814fcff3a6e27.15945292.png', 'Pending', 'Ron', '2025-05-02 17:12:31', '2025-05-02 17:12:31');

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
(20250001, 'Ron Jacob Rodanilla', 'BSIT', '4', 4.00, 5.00, 0, 1, 0, 0, 0, 100.00, 1, '2025-04-28 05:32:27', '2025-05-02 18:01:01'),
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
(1, 0, 'admin@gmail.com', 'Admin', '$2y$10$g2jw9rgEttd7I9zEuILXR.Y2CQ34KkHxIX6FAHeEGq2mOTgK64/MW', 'Admin'),
(2, 20250001, 'ronrodanilla@gmail.com', 'Ron', '$2y$10$WG.nZFw/VaAHNrnotLWWQuFHqAV3BEO7haJyqUwWaWmUv4TFmFfMe', 'Student'),
(3, 20190001, 'danny@gmail.com', 'Danny', '$2y$10$evMO2r6mEO5TW2IY8XIRZ.ESfD78Wfg94yLv0W6FlxZVc9nK4N6Bm', 'Dean'),
(4, 20250002, 'johndoe@gmail.com', 'John Doe', '$2y$10$ip3m4MeQNAg5Nk4SwehiluVvY3Boj0ckFFTjXnnU8UeAf51scS2PW', 'Student'),
(5, 20190002, 'accounting@gmail.com', 'Accounting', '$2y$10$hyTECWqxGTj8cRI2W9RFg.gM1liXb2.nj6FoYRi1LhGWz0ed6FYeS', 'Admin'),
(6, 20250003, 'janedoe@gmail.com', 'Jane Doe', '$2y$10$chzHGmIdJ6pDOOLGiKAXrOvNhk7jtLxafxC.66k2jbe555i8aANkm', 'Student'),
(7, 20250004, 'billywoke@gmail.com', 'Billy Woke', '$2y$10$LlRn1w4HsfiZ13Rd3WtG6eVy7rak2KaVKjXhktNTj1hwz47G8kYOG', 'Student'),
(8, 20250005, 'jonnybravo@gmail.com', 'Jonny Bravo', '$2y$10$zXqkk4DsDsPAeszxYa4DKe4TAyD0fNGgVzQGEk/av9r9c7.m9OnBC', 'Student'),
(9, 20250006, 'jacobsmith@gmail.com', 'Jacob Smith', '$2y$10$iIbLtKhIuwtQvOWU97WSqOXBs6SIccDVPqjMpHO/6vbhv/0fHADkW', 'Student'),
(10, 20250007, 'maryjoy@gmail.com', 'Mary Joy', '$2y$10$29d9kbtaqJmkTHHuAN7zxOD6eyDFNzr.mheW3jyTuxyQovPxf81Da', 'Student'),
(11, 20250008, 'razemane@gmail.com', 'Raze Mane', '$2y$10$VqNU2JrLrPLPRMEqhfqqfOKaUwM7N28QMg/MlhUDToaeG/H.Na2S2', 'Student'),
(12, 20190003, 'dean@gmail.com', 'Dean', '$2y$10$oOqzYL97XqjG.J.QHgUMd.k3fO2mHfbHKv5Y1CQL8CL8GQU.fzxmi', 'Dean');

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
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

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
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
