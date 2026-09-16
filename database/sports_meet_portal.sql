-- phpMyAdmin SQL Dump
-- version 5.0.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 10, 2026 at 05:42 PM
-- Server version: 10.4.11-MariaDB
-- PHP Version: 7.4.2

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sports_meet_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'login', 'User logged in', '::1', '2026-07-09 19:46:48'),
(2, 1, 'logout', 'User logged out', '::1', '2026-07-09 19:46:56'),
(3, 1, 'login', 'User logged in', '::1', '2026-07-09 19:47:06'),
(4, 1, 'logout', 'User logged out', '::1', '2026-07-09 19:47:35'),
(5, 1, 'login', 'User logged in', '::1', '2026-07-10 10:39:13');

-- --------------------------------------------------------

--
-- Table structure for table `approval_workflow`
--

CREATE TABLE `approval_workflow` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `action` enum('submit','approve','reject','return_for_revision') NOT NULL,
  `action_by` int(11) NOT NULL,
  `role_at_time` enum('county_coordinator','association_admin','super_admin') NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `counties`
--

CREATE TABLE `counties` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `group_label` enum('A','B','C','D') NOT NULL,
  `code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `counties`
--

INSERT INTO `counties` (`id`, `name`, `group_label`, `code`, `created_at`) VALUES
(1, 'Montserrado', 'C', 'MG', '2026-07-09 19:45:47'),
(2, 'Margibi', 'D', 'MA', '2026-07-09 19:45:47'),
(3, 'Grand Bassa', 'C', 'GB', '2026-07-09 19:45:47'),
(4, 'River Cess', 'B', 'RC', '2026-07-09 19:45:47'),
(5, 'Nimba', 'A', 'NM', '2026-07-09 19:45:47'),
(6, 'Lofa', 'C', 'LF', '2026-07-09 19:45:47'),
(7, 'Bong', 'B', 'BG', '2026-07-09 19:45:47'),
(8, 'Gbarpolu', 'A', 'GP', '2026-07-09 19:45:47'),
(9, 'Grand Gedeh', 'A', 'GG', '2026-07-09 19:45:47'),
(10, 'River Gee', 'A', 'RG', '2026-07-09 19:45:47'),
(11, 'Sinoe', 'C', 'SN', '2026-07-09 19:45:47'),
(12, 'Maryland', 'B', 'ML', '2026-07-09 19:45:47'),
(13, 'Grand Cape Mount', 'B', 'GM', '2026-07-09 19:45:47'),
(14, 'Bomi', 'D', 'BM', '2026-07-09 19:45:47'),
(15, 'Grand Kru', 'D', 'GK', '2026-07-09 19:45:47');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `gallery_photos`
--

CREATE TABLE `gallery_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_slug` varchar(100) NOT NULL,
  `category_title` varchar(200) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT '',
  `sort_order` int(11) DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `sport_discipline_id` int(11) NOT NULL,
  `home_county_id` int(11) NOT NULL,
  `away_county_id` int(11) NOT NULL,
  `home_score` int(11) DEFAULT NULL,
  `away_score` int(11) DEFAULT NULL,
  `match_date` datetime NOT NULL,
  `status` enum('scheduled','live','completed') DEFAULT 'scheduled',
  `group_label` enum('A','B','C','D') NOT NULL,
  `round` varchar(50) DEFAULT 'Group Stage',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `match_reports`
--

CREATE TABLE `match_reports` (
  `id` int(11) NOT NULL,
  `match_id` int(11) NOT NULL,
  `commissioner_id` int(11) NOT NULL,
  `home_yellow_cards` int(11) DEFAULT 0,
  `home_red_cards` int(11) DEFAULT 0,
  `away_yellow_cards` int(11) DEFAULT 0,
  `away_red_cards` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `match_report_cards`
--

CREATE TABLE `match_report_cards` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `team` enum('home','away') NOT NULL,
  `card_type` enum('yellow','red') NOT NULL,
  `jersey_number` int(11) NOT NULL,
  `player_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `match_squad_players`
--

CREATE TABLE `match_squad_players` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `team` enum('home','away') NOT NULL,
  `player_type` enum('starting','substitute') NOT NULL,
  `jersey_number` int(11) NOT NULL,
  `player_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `nationality` varchar(100) NOT NULL DEFAULT 'Liberian',
  `year_of_nscm` varchar(4) NOT NULL,
  `age` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `city` varchar(100) NOT NULL DEFAULT '',
  `last_club` varchar(200) NOT NULL DEFAULT '',
  `current_club` varchar(200) NOT NULL DEFAULT '',
  `county_id` int(11) NOT NULL,
  `primary_position` varchar(50) NOT NULL,
  `emergency_contact_name` varchar(100) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `emergency_contact_relation` varchar(50) NOT NULL,
  `medical_fitness_status` enum('fit','unfit','pending_review') DEFAULT 'pending_review',
  `medical_notes` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `sport_discipline_id` int(11) NOT NULL,
  `registered_by` int(11) NOT NULL,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `sports_disciplines`
--

CREATE TABLE `sports_disciplines` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `association_name` varchar(100) NOT NULL,
  `association_code` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `sports_disciplines`
--

INSERT INTO `sports_disciplines` (`id`, `name`, `association_name`, `association_code`, `description`, `status`, `created_at`) VALUES
(1, 'Football', 'Liberia Football Association', 'LFA', NULL, 'active', '2026-07-09 19:45:47'),
(2, 'Kickball', 'Liberia Kickball Association', 'LKA', NULL, 'active', '2026-07-09 19:45:47'),
(3, 'Basketball', 'Liberia Basketball Association', 'LBA', NULL, 'active', '2026-07-09 19:45:47'),
(4, 'Athletics', 'Liberia Athletics Association', 'LAA', NULL, 'active', '2026-07-09 19:45:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('super_admin','county_coordinator','association_admin','match_commissioner') NOT NULL,
  `county_id` int(11) DEFAULT NULL,
  `association_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `role`, `county_id`, `association_id`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'admin@sportsmeet.gov.lr', 'System Administrator', 'super_admin', NULL, NULL, NULL, 'active', '2026-07-09 19:45:47', '2026-07-09 19:45:47'),
(4, 'bong_coord', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'bong_coord@sportsmeet.gov.lr', 'Bong Coordinator', 'county_coordinator', 7, NULL, NULL, 'active', '2026-07-09 19:45:47', '2026-07-09 19:45:47'),
(7, 'lfa_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'lfa@lfa.org', 'LFA Administrator', 'association_admin', NULL, 1, NULL, 'active', '2026-07-09 19:45:47', '2026-07-09 19:45:47'),
(8, 'lka_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'lka@lka.org', 'LKA Administrator', 'association_admin', NULL, 2, NULL, 'active', '2026-07-09 19:45:47', '2026-07-09 19:45:47'),
(9, 'lba_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'lba@lba.org', 'LBA Administrator', 'association_admin', NULL, 3, NULL, 'active', '2026-07-09 19:45:47', '2026-07-09 19:45:47'),
(10, 'laa_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'laa@laa.org', 'LAA Administrator', 'association_admin', NULL, 4, NULL, 'active', '2026-07-09 19:45:47', '2026-07-09 19:45:47'),
(11, 'commissioner', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'commissioner@sportsmeet.gov.lr', 'Match Commissioner', 'match_commissioner', NULL, NULL, NULL, 'active', '2026-07-09 19:45:47', '2026-07-09 19:45:47'),
(15, 'margibi_coord', '$2y$10$dReUbsuBeneBmqsIVLYby.raNyCtMQ0vFwaAeCa9YHr/hTaNW7QAG', 'margibi_coord@sportsmeet.gov.lr', 'Margibi Coordinator', 'county_coordinator', 2, NULL, NULL, 'active', '2026-07-09 19:45:55', '2026-07-09 19:45:55'),
(16, 'kru_coord', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'kru_coord@sportsmeet.gov.lr', 'Grand Kru Coordinator', 'county_coordinator', 15, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00'),
(17, 'kru_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'kru_admin@sportsmeet.gov.lr', 'Grand Kru Admin', 'county_coordinator', 15, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00'),
(18, 'gedeh_coord', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'gedeh_coord@sportsmeet.gov.lr', 'Grand Gedeh Coordinator', 'county_coordinator', 9, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00'),
(19, 'gedeh_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'gedeh_admin@sportsmeet.gov.lr', 'Grand Gedeh Admin', 'county_coordinator', 9, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00'),
(20, 'lofa_coord', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'lofa_coord@sportsmeet.gov.lr', 'Lofa Coordinator', 'county_coordinator', 6, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00'),
(21, 'lofa_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'lofa_admin@sportsmeet.gov.lr', 'Lofa Admin', 'county_coordinator', 6, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00'),
(22, 'bong_admin', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'bong_admin@sportsmeet.gov.lr', 'Bong Admin', 'county_coordinator', 7, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00'),
(23, 'sports_coord', '$2y$10$Uy1Hl09eP7QDGoeZ1qndA.PGQT4ZByKWNte54rmuwz7lcLJAqiF9m', 'sports_coord@sportsmeet.gov.lr', 'Sports Bureau Coordinator', 'super_admin', NULL, NULL, NULL, 'active', '2026-07-10 00:00:00', '2026-07-10 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `video_type` enum('file','url') NOT NULL DEFAULT 'file',
  `video_path` varchar(255) DEFAULT NULL,
  `embed_url` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_user` (`user_id`);

--
-- Indexes for table `approval_workflow`
--
ALTER TABLE `approval_workflow`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action_by` (`action_by`),
  ADD KEY `idx_approval_player` (`player_id`);

--
-- Indexes for table `counties`
--
ALTER TABLE `counties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_documents_uploaded` (`uploaded_by`);

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `home_county_id` (`home_county_id`),
  ADD KEY `away_county_id` (`away_county_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_matches_sport` (`sport_discipline_id`),
  ADD KEY `idx_matches_group` (`group_label`),
  ADD KEY `idx_matches_status` (`status`);

--
-- Indexes for table `match_reports`
--
ALTER TABLE `match_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `match_id` (`match_id`),
  ADD KEY `commissioner_id` (`commissioner_id`);

--
-- Indexes for table `match_report_cards`
--
ALTER TABLE `match_report_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `match_squad_players`
--
ALTER TABLE `match_squad_players`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`);

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD KEY `registered_by` (`registered_by`),
  ADD KEY `idx_players_county` (`county_id`),
  ADD KEY `idx_players_sport` (`sport_discipline_id`),
  ADD KEY `idx_players_status` (`status`);

--
-- Indexes for table `sports_disciplines`
--
ALTER TABLE `sports_disciplines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `association_code` (`association_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `approval_workflow`
--
ALTER TABLE `approval_workflow`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `counties`
--
ALTER TABLE `counties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_reports`
--
ALTER TABLE `match_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_report_cards`
--
ALTER TABLE `match_report_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `match_squad_players`
--
ALTER TABLE `match_squad_players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sports_disciplines`
--
ALTER TABLE `sports_disciplines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `approval_workflow`
--
ALTER TABLE `approval_workflow`
  ADD CONSTRAINT `approval_workflow_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `approval_workflow_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`sport_discipline_id`) REFERENCES `sports_disciplines` (`id`),
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`home_county_id`) REFERENCES `counties` (`id`),
  ADD CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`away_county_id`) REFERENCES `counties` (`id`),
  ADD CONSTRAINT `matches_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `match_reports`
--
ALTER TABLE `match_reports`
  ADD CONSTRAINT `match_reports_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `match_reports_ibfk_2` FOREIGN KEY (`commissioner_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `match_report_cards`
--
ALTER TABLE `match_report_cards`
  ADD CONSTRAINT `match_report_cards_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `match_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `match_squad_players`
--
ALTER TABLE `match_squad_players`
  ADD CONSTRAINT `match_squad_players_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `match_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `players`
--
ALTER TABLE `players`
  ADD CONSTRAINT `players_ibfk_1` FOREIGN KEY (`county_id`) REFERENCES `counties` (`id`),
  ADD CONSTRAINT `players_ibfk_2` FOREIGN KEY (`sport_discipline_id`) REFERENCES `sports_disciplines` (`id`),
  ADD CONSTRAINT `players_ibfk_3` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`);

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
