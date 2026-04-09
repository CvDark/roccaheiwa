-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 09, 2026 at 02:39 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u632352078_institution`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_keys`
--

CREATE TABLE `access_keys` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `key_value` varchar(255) NOT NULL,
  `key_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `access_logs`
--

CREATE TABLE `access_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `key_used` varchar(255) DEFAULT NULL,
  `access_method` enum('qr_code','manual_key','mobile_app','web','card_scan') NOT NULL DEFAULT 'qr_code',
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `key_used` varchar(64) DEFAULT NULL,
  `access_method` enum('web','qr_code','mobile_app','manual_key','card_scan','key_regeneration','nfc_card') NOT NULL DEFAULT 'web',
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `locker_id`, `device_id`, `key_used`, `access_method`, `success`, `timestamp`) VALUES
(1, 1, 2, NULL, 'LK-B2E411', 'web', 1, '2026-03-12 10:43:25');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','staff') NOT NULL DEFAULT 'staff',
  `institution` varchar(100) DEFAULT NULL COMMENT 'Nama institusi diuruskan',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `username`, `password`, `role`, `institution`, `is_active`, `last_login`, `created_at`) VALUES
(1, 'Nopal', 'admin', '$2y$10$mKv4pGyMJyTIBW4U.r7LD.0SsAdU3b1pP38MxV.77imNSEsQXyivW', 'superadmin', 'Smart Locker Institution', 1, '2026-04-07 01:07:43', '2026-03-10 20:01:00'),
(2, 'Adam', 'DamZu', '$2y$10$Qli6xrLvBvC4rtaOD4Kgye6FZxtZoGZUPTRRE14VV.XoakvoOjSG6', 'superadmin', 'KMJ', 1, NULL, '2026-04-07 01:50:20');

-- --------------------------------------------------------

--
-- Table structure for table `gm861_scan_queue`
--

CREATE TABLE `gm861_scan_queue` (
  `id` int(11) NOT NULL,
  `esp32_device_id` varchar(50) NOT NULL,
  `scanned_data` text NOT NULL,
  `status` enum('pending','processed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `id_scan_sessions`
--

CREATE TABLE `id_scan_sessions` (
  `id` int(11) NOT NULL,
  `session_token` varchar(64) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `locker_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','expired') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lockers`
--

CREATE TABLE `lockers` (
  `id` int(11) NOT NULL,
  `device_id` varchar(255) DEFAULT NULL COMMENT 'ESP32 device ID',
  `locker_key` varchar(100) DEFAULT NULL COMMENT 'Key fizikal locker — hanya admin boleh set/lihat',
  `unique_code` varchar(50) NOT NULL COMMENT 'Locker unique code e.g. LKR001',
  `status` enum('available','occupied','active','maintenance') NOT NULL DEFAULT 'available',
  `command_status` enum('IDLE','UNLOCK','LOCK') NOT NULL DEFAULT 'IDLE',
  `nfc_register_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = register mode active (admin scanning card), 0 = normal access mode',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lockers`
--

INSERT INTO `lockers` (`id`, `device_id`, `locker_key`, `unique_code`, `status`, `command_status`, `nfc_register_mode`, `created_at`) VALUES
(1, 'INST-BACKUP', 'KEY123', 'INST-BACKUP', 'available', 'IDLE', 0, '2026-03-10 12:01:00'),
(2, 'DEV-DUAL', 'KEY456', 'INST-001', 'available', 'IDLE', 0, '2026-03-10 12:01:00');

-- --------------------------------------------------------

--
-- Table structure for table `locker_requests`
--

CREATE TABLE `locker_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) DEFAULT NULL COMMENT 'NULL = belum assign locker',
  `note` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `nfc_scan_queue`
--

CREATE TABLE `nfc_scan_queue` (
  `id` int(11) NOT NULL,
  `esp32_device_id` varchar(50) NOT NULL,
  `nfc_uid` varchar(50) NOT NULL,
  `purpose` enum('register','access') NOT NULL DEFAULT 'access',
  `status` enum('pending','processed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `nfc_scan_queue`
--

INSERT INTO `nfc_scan_queue` (`id`, `esp32_device_id`, `nfc_uid`, `purpose`, `status`, `created_at`, `processed_at`) VALUES
(6, 'DEV001', '0433DBE43D0289', 'register', 'processed', '2026-04-06 01:55:41', '2026-04-06 01:55:43'),
(7, 'DEV001', '0463C1EB3D0289', 'register', 'processed', '2026-04-06 01:56:06', '2026-04-06 01:56:06'),
(9, 'DEV001', '04A335EE3D0289', 'register', 'processed', '2026-04-06 01:59:00', '2026-04-06 01:59:01'),
(20, 'DEV001', '04A335EE3D0289', 'access', 'processed', '2026-04-06 02:29:45', '2026-04-06 02:29:45'),
(21, 'DEV001', 'EB1FCB06', 'access', 'processed', '2026-04-06 02:29:53', '2026-04-06 02:29:53'),
(22, 'DEV001', '0463C1EB3D0289', 'access', 'processed', '2026-04-06 02:30:00', '2026-04-06 02:30:00'),
(23, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-06 02:30:10', '2026-04-06 02:30:11'),
(24, 'DEV001', '04A335EE3D0289', 'access', 'processed', '2026-04-06 02:33:53', '2026-04-06 02:33:54'),
(25, 'DEV001', '04A335EE3D0289', 'access', 'processed', '2026-04-06 02:35:29', '2026-04-06 02:35:30'),
(26, 'DEV001', '04A335EE3D0289', 'access', 'processed', '2026-04-06 02:40:58', '2026-04-06 02:40:58'),
(27, 'DEV001', '04A335EE3D0289', 'access', 'processed', '2026-04-06 02:41:57', '2026-04-06 02:41:58'),
(35, 'DEV001', '0433DBE43D0289', 'register', 'processed', '2026-04-07 01:16:25', '2026-04-07 01:16:26'),
(36, 'DEV001', '0463C1EB3D0289', 'register', 'processed', '2026-04-07 01:16:45', '2026-04-07 01:16:46'),
(37, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-07 01:17:07', '2026-04-07 01:17:08'),
(38, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-07 01:17:10', '2026-04-07 01:17:10'),
(39, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-07 01:17:11', '2026-04-07 01:17:13'),
(40, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-07 01:17:14', '2026-04-07 01:17:14'),
(41, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-07 01:17:45', '2026-04-07 01:17:46'),
(42, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-07 01:17:48', '2026-04-07 01:17:49'),
(43, 'DEV001', '0433DBE43D0289', 'access', 'processed', '2026-04-07 01:18:19', '2026-04-07 01:18:21'),
(84, 'DEV-DUAL', '0463C1EB3D0289', 'access', 'pending', '2026-04-08 14:56:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `registered_matrics`
--

CREATE TABLE `registered_matrics` (
  `id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL COMMENT 'Nombor matrik / staff ID',
  `nfc_uid` varchar(50) DEFAULT NULL COMMENT 'UID NFC sticker linked ke matric ini',
  `full_name` varchar(255) DEFAULT NULL,
  `user_type` enum('student','staff') NOT NULL DEFAULT 'student',
  `institution` varchar(100) DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = dah register akaun',
  `added_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registered_matrics`
--

INSERT INTO `registered_matrics` (`id`, `id_number`, `nfc_uid`, `full_name`, `user_type`, `institution`, `is_used`, `added_by`, `created_at`) VALUES
(1, 'MC2516203256', '0463C1EB3D0289', 'MOHAMMAD ADAM ZUHAIR BIN MOHAMMAD YUSUF', 'student', 'KOLEJ MATRIKULASI JOHOR', 1, 1, '2026-03-12 22:08:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nfc_uid` varchar(50) DEFAULT NULL COMMENT 'UID NFC sticker/card',
  `nfc_registered_at` datetime DEFAULT NULL,
  `nfc_assigned_by` int(11) DEFAULT NULL COMMENT 'Admin ID yang assign card',
  `role` enum('admin','manager','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `user_id_number` varchar(50) NOT NULL COMMENT 'Matric/Staff ID',
  `user_type` enum('student','staff') NOT NULL DEFAULT 'student',
  `institution` varchar(100) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `paired_device_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `nfc_uid`, `nfc_registered_at`, `nfc_assigned_by`, `role`, `created_at`, `last_login`, `user_id_number`, `user_type`, `institution`, `is_active`, `paired_device_token`) VALUES
(1, 'muhdnaufal123456789@gmail.com', '$2y$10$XX1FmuIKWtAFZQrVmiDwGeeaKCvJgs7KgFkp0WKG5koVPUXyBDMZe', 'Muhammad Naufal', '01158657710', '0433DBE43D0289', '2026-04-07 01:16:33', 1, 'user', '2026-03-10 12:31:20', '2026-04-08 13:47:23', 'MC2516203265', 'student', 'Johor Matriculation College', 1, NULL),
(2, 'kisshorrengasamy@gmail.com', '$2y$10$nOgvuIhl9z5GGJaCXABFmeZSKcvlOQNuc9suKMeq8Jn6/N4OzU.f6', 'Iham', '0124105952', NULL, NULL, 1, 'user', '2026-03-12 02:48:04', '2026-03-12 03:07:12', 'MC2516203260', 'staff', 'Johor Matriculation College', 1, NULL),
(3, 'MohammadAdamZuhair@gmail.com', '$2y$10$aVuZXMo8xow6q.F69rqIX.k4kBtfs9yC6c7Bq2xHGqiGZVsbWfhky', 'MOHAMMAD ADAM ZUHAIR BIN MOHAMMAD YUSUF', '01111953345', '0463C1EB3D0289', '2026-04-07 01:16:56', 1, 'user', '2026-03-12 14:23:22', '2026-03-12 14:23:35', 'MC2516203256', 'student', 'KOLEJ MATRIKULASI JOHOR', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_locker_assignments`
--

CREATE TABLE `user_locker_assignments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `key_value` varchar(255) DEFAULT NULL COMMENT 'Access key untuk locker ini',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `custom_name` varchar(100) DEFAULT NULL COMMENT 'Nama custom oleh user',
  `custom_location` varchar(100) DEFAULT NULL COMMENT 'Lokasi custom oleh user',
  `id_number_used` varchar(50) DEFAULT NULL COMMENT 'ID number waktu assign',
  `access_expiry` datetime DEFAULT NULL COMMENT 'Tamat tempoh akses (NULL = tiada had)',
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_locker_assignments`
--

INSERT INTO `user_locker_assignments` (`id`, `user_id`, `locker_id`, `key_value`, `assigned_at`, `is_active`, `custom_name`, `custom_location`, `id_number_used`, `access_expiry`, `updated_at`) VALUES
(5, 1, 1, 'LK-A198', '2026-03-12 02:03:43', 0, 'Locker Naufal', 'Makmal Tutor', NULL, NULL, '2026-03-13 12:30:43'),
(6, 1, 2, 'LK-B2E411', '2026-03-12 02:43:25', 0, 'Locker Naufal', 'Makmal Tutor', NULL, NULL, '2026-04-06 02:27:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_keys`
--
ALTER TABLE `access_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_value` (`key_value`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_locker_id` (`locker_id`),
  ADD KEY `idx_key_value` (`key_value`);

--
-- Indexes for table `access_logs`
--
ALTER TABLE `access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_locker_id` (`locker_id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_locker_id` (`locker_id`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `gm861_scan_queue`
--
ALTER TABLE `gm861_scan_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device_status` (`esp32_device_id`,`status`);

--
-- Indexes for table `id_scan_sessions`
--
ALTER TABLE `id_scan_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `lockers`
--
ALTER TABLE `lockers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`unique_code`),
  ADD KEY `idx_unique_code` (`unique_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_device_id` (`device_id`);

--
-- Indexes for table `locker_requests`
--
ALTER TABLE `locker_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `nfc_scan_queue`
--
ALTER TABLE `nfc_scan_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device_status_purpose` (`esp32_device_id`,`status`,`purpose`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `registered_matrics`
--
ALTER TABLE `registered_matrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_id_number` (`id_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id_number` (`user_id_number`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_id_number` (`user_id_number`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_institution` (`institution`);

--
-- Indexes for table `user_locker_assignments`
--
ALTER TABLE `user_locker_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_locker` (`user_id`,`locker_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_locker_id` (`locker_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_keys`
--
ALTER TABLE `access_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `access_logs`
--
ALTER TABLE `access_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `gm861_scan_queue`
--
ALTER TABLE `gm861_scan_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `id_scan_sessions`
--
ALTER TABLE `id_scan_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lockers`
--
ALTER TABLE `lockers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `locker_requests`
--
ALTER TABLE `locker_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nfc_scan_queue`
--
ALTER TABLE `nfc_scan_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `registered_matrics`
--
ALTER TABLE `registered_matrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_locker_assignments`
--
ALTER TABLE `user_locker_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `access_keys`
--
ALTER TABLE `access_keys`
  ADD CONSTRAINT `fk_ak_locker` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ak_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `access_logs`
--
ALTER TABLE `access_logs`
  ADD CONSTRAINT `fk_acl_locker` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_acl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_al_locker` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_al_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `locker_requests`
--
ALTER TABLE `locker_requests`
  ADD CONSTRAINT `fk_lr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_locker_assignments`
--
ALTER TABLE `user_locker_assignments`
  ADD CONSTRAINT `fk_ula_locker` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ula_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
