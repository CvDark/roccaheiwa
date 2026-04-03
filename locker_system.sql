-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 02, 2026 at 05:25 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `locker_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_logs`
--

CREATE TABLE `access_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `key_used` varchar(255) DEFAULT NULL,
  `access_method` enum('qr_code','manual_key','mobile_app','web') DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `device_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) DEFAULT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `key_used` varchar(64) DEFAULT NULL,
  `access_method` enum('web','qr_code','mobile_app','manual_key','card_scan','key_regeneration','gm861_matric','nfc_card') NOT NULL DEFAULT 'web',
  `success` tinyint(1) DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `locker_id`, `device_id`, `key_used`, `access_method`, `success`, `notes`, `timestamp`) VALUES
(1, 2, 2, 'web', 'af269ec021d089b93e924d17a296ddb5', 'web', 1, NULL, '2026-03-10 11:29:31'),
(2, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 22:57:07'),
(3, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:12:11'),
(4, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:13:04'),
(5, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:13:09'),
(6, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:16:18'),
(7, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:16:20'),
(8, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:16:25'),
(9, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:16:33'),
(10, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:16:42'),
(11, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:16:54'),
(12, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:17:14'),
(13, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:20:04'),
(14, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:20:09'),
(15, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:20:20'),
(16, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:20:52'),
(17, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:29:39'),
(18, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:33:50'),
(19, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:33:57'),
(20, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-10 23:34:06'),
(21, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 00:17:26'),
(22, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 00:17:29'),
(23, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 00:17:35'),
(24, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 00:17:45'),
(25, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:29:53'),
(26, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:29:56'),
(27, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:32:30'),
(28, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:32:37'),
(29, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:32:53'),
(30, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:33:03'),
(31, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:33:23'),
(32, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:33:43'),
(33, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:33:53'),
(34, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:34:33'),
(35, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:38:37'),
(36, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:38:50'),
(37, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:40:41'),
(38, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:40:47'),
(39, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:40:52'),
(40, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:41:07'),
(41, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:41:10'),
(42, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:41:21'),
(43, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:41:24'),
(44, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:42:58'),
(45, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:43:04'),
(46, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:43:08'),
(47, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:43:11'),
(48, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:43:20'),
(49, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:44:19'),
(50, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:45:00'),
(51, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:45:55'),
(52, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:46:35'),
(53, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:49:21'),
(54, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:54:00'),
(55, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:54:06'),
(56, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:54:13'),
(57, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:54:17'),
(58, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:54:34'),
(59, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:54:53'),
(60, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:55:04'),
(61, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:55:21'),
(62, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-11 09:55:35'),
(63, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:21:42'),
(64, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:21:44'),
(65, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:22:07'),
(66, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:23:37'),
(67, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:23:50'),
(68, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:23:59'),
(69, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:24:05'),
(70, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:27:11'),
(71, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 10:30:58'),
(72, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:01:44'),
(73, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:01:59'),
(74, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:17:56'),
(75, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:17:58'),
(76, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:18:02'),
(77, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:18:17'),
(78, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:18:21'),
(79, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:18:30'),
(80, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:18:38'),
(81, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:19:05'),
(82, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:20:29'),
(83, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:20:40'),
(84, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:20:49'),
(85, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:21:00'),
(86, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:21:10'),
(87, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:21:47'),
(88, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:21:58'),
(89, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:22:12'),
(90, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:22:26'),
(91, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:22:33'),
(92, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:22:41'),
(93, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:22:56'),
(94, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:23:30'),
(95, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:23:35'),
(96, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:23:35'),
(97, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:23:50'),
(98, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:25:04'),
(99, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:25:10'),
(100, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:25:40'),
(101, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:26:02'),
(102, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:26:07'),
(103, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:26:12'),
(104, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:27:54'),
(105, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:28:29'),
(106, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:29:25'),
(107, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-12 11:34:34'),
(108, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:18:45'),
(109, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:18:47'),
(110, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:18:51'),
(111, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:19:03'),
(112, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:19:08'),
(113, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:19:14'),
(114, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:19:21'),
(115, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:19:36'),
(116, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:20:16'),
(117, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:21:42'),
(118, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:21:45'),
(119, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:21:46'),
(120, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:22:03'),
(121, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:25:20'),
(122, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:30:25'),
(123, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:30:29'),
(124, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:30:56'),
(125, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:36:18'),
(126, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:36:22'),
(127, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:36:30'),
(128, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-15 07:36:36'),
(129, 2, 2, 'DEV001', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 21:58:41'),
(130, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 21:58:44'),
(131, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 21:58:49'),
(132, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 21:58:52'),
(133, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:00:41'),
(134, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:00:45'),
(135, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:01:00'),
(136, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:02:53'),
(137, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:02:56'),
(138, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:05:58'),
(139, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:06:02'),
(140, 2, 2, 'web_api', 'af269ec021d089b93e924d17a296ddb5', 'qr_code', 1, NULL, '2026-03-23 22:06:27');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','staff') DEFAULT 'staff',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `username`, `password`, `role`, `is_active`, `last_login`, `created_at`) VALUES
(1, 'Super Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, '2026-04-02 10:25:27', '2026-03-08 16:54:54');

-- --------------------------------------------------------

--
-- Table structure for table `gm861_scan_queue`
--

CREATE TABLE `gm861_scan_queue` (
  `id` int(11) NOT NULL,
  `esp32_device_id` varchar(50) NOT NULL,
  `scanned_data` text NOT NULL,
  `status` enum('pending','processed') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `id_scan_sessions`
--

CREATE TABLE `id_scan_sessions` (
  `id` int(11) NOT NULL,
  `session_token` varchar(64) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `locker_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','expired') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lockers`
--

CREATE TABLE `lockers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `unique_code` varchar(50) DEFAULT NULL,
  `status` enum('available','occupied','active','maintenance') DEFAULT 'available',
  `command_status` enum('IDLE','UNLOCK','LOCK') DEFAULT 'IDLE',
  `nfc_register_mode` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=register mode active, 0=normal access',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `locker_key` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lockers`
--

INSERT INTO `lockers` (`id`, `name`, `location`, `device_id`, `unique_code`, `status`, `command_status`, `nfc_register_mode`, `created_at`, `locker_key`) VALUES
(2, NULL, NULL, 'DEV001', 'LKR001', 'active', 'LOCK', 0, '2026-03-10 03:29:20', '5df37f9d2e08829c');

-- --------------------------------------------------------

--
-- Table structure for table `locker_requests`
--

CREATE TABLE `locker_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `nfc_sessions`
--

CREATE TABLE `nfc_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `nfc_uid` varchar(50) NOT NULL,
  `status` enum('active','used','expired') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `nfc_uid` varchar(50) DEFAULT NULL COMMENT 'UID physical NFC card/fob (MIFARE)',
  `nfc_registered_at` datetime DEFAULT NULL,
  `role` enum('admin','manager','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `user_id_number` varchar(50) DEFAULT NULL,
  `user_type` enum('student','staff','public') DEFAULT 'student',
  `institution` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `paired_device_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `phone`, `nfc_uid`, `nfc_registered_at`, `role`, `created_at`, `last_login`, `user_id_number`, `user_type`, `institution`, `is_active`, `paired_device_token`) VALUES
(1, 'admin@example.com', '$2y$10$nMOEDK.AihhmRDBhOpJV5eIp0wcG.S4HCyk4rErMx4wzW8l4Qb0wu', 'System Administrator', NULL, NULL, NULL, 'admin', '2026-02-10 01:44:56', '2026-02-23 01:16:20', '123456789', 'student', 'KMJ', 1, NULL),
(2, 'muhdnaufal123456789@gmail.com', '$2y$10$GJ36aWKBaWig8qQBSYU/v.xC0tTky94JKIGexwD64y5dqZiBZwzMq', 'Muhammad Naufal', '01158657714', NULL, NULL, 'user', '2026-02-10 03:37:20', '2026-04-02 13:26:41', '071124100539', 'student', 'KMJ', 1, NULL),
(3, 'mazmyusuf@gmail.com', '$2y$10$Y4OcXnzhslTToDAB9iUK2.Xq7lQjF4xVkXzDY/8AQkmDBQZiFI0Hq', 'MOHAMMAD ADAM ZUHAIR BIN MOHAMMAD YUSUF', '01111953345', NULL, NULL, 'user', '2026-02-28 05:12:55', '2026-03-08 09:22:09', 'MC2516203256', 'staff', 'KOLEJ MATRIKULASI JOHOR', 1, NULL),
(4, 'bsnsiks@gmail.con', '$2y$10$FfDKbqchcVgJzjGk/p3sjOb8vGtA804qTg1HvuGRhY.HfPrunDrkC', 'irham', '0179780878', NULL, NULL, 'user', '2026-03-08 09:25:58', '2026-03-08 09:29:02', 'MC2516203254', 'student', 'Matriks', 1, NULL),
(5, 'muhdnaufal74756@gmail.com', '$2y$10$bm0RDq2ch9bUbWDwR6WeleTs.wTQzoxuILhN3LwRusbTKjQcE71I2', 'Muhammad Naufal bin Mohd Radzuan', '01158657714', NULL, NULL, 'user', '2026-03-08 09:50:12', '2026-04-01 13:54:01', 'MC2516203265', 'student', 'Johor Matriculation College', 1, NULL),
(6, 'zed@gmail.com', '$2y$10$tdLfHrcwqxhtg.rQEOW.VuLnoqwyCy7DToCCoC.XojVMGCgaEsY3.', 'zed', '1234', NULL, NULL, 'user', '2026-03-09 10:33:37', '2026-03-09 10:33:49', '1234', 'student', '1234', 1, NULL),
(7, 'ffafif719@gmail.com', '$2y$10$jBnc.b7jXeEPsnmdV8nuCuUMn5IJiel29Ep8iWOhwqVpZ7NW/ImtW', 'Afif', '11', NULL, NULL, 'user', '2026-03-13 04:30:55', '2026-03-13 04:39:50', 'a', 'student', 'a', 1, NULL),
(8, 'test@gmail.com', '$2y$10$xjzsBjAUX/ahDjfOzeqB/eoaoQt2lNYeLdu65DaCmCzvRF/DNbONq', 'test', '', NULL, NULL, 'user', '2026-03-21 07:03:29', '2026-03-21 07:04:25', 'test', 'student', '', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_locker_assignments`
--

CREATE TABLE `user_locker_assignments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `locker_id` int(11) NOT NULL,
  `key_value` varchar(255) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `custom_name` varchar(100) DEFAULT NULL,
  `custom_location` varchar(100) DEFAULT NULL,
  `id_number_used` varchar(50) DEFAULT NULL,
  `access_expiry` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_locker_assignments`
--

INSERT INTO `user_locker_assignments` (`id`, `user_id`, `locker_id`, `key_value`, `assigned_at`, `is_active`, `custom_name`, `custom_location`, `id_number_used`, `access_expiry`, `updated_at`) VALUES
(1, 2, 2, 'af269ec021d089b93e924d17a296ddb5', '2026-03-10 03:29:30', 1, 'Locker Naufal', 'A4.17', NULL, NULL, '2026-03-10 11:31:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_logs`
--
ALTER TABLE `access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `locker_id` (`locker_id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `locker_id` (`locker_id`);

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
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `id_scan_sessions`
--
ALTER TABLE `id_scan_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `locker_id` (`locker_id`);

--
-- Indexes for table `lockers`
--
ALTER TABLE `lockers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`unique_code`);

--
-- Indexes for table `locker_requests`
--
ALTER TABLE `locker_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `locker_id` (`locker_id`);

--
-- Indexes for table `nfc_scan_queue`
--
ALTER TABLE `nfc_scan_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device_status` (`esp32_device_id`,`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `nfc_sessions`
--
ALTER TABLE `nfc_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_locker` (`user_id`,`locker_id`),
  ADD KEY `idx_status_expires` (`status`,`expires_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_id_number` (`user_id_number`);

--
-- Indexes for table `user_locker_assignments`
--
ALTER TABLE `user_locker_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `locker_id` (`locker_id`),
  ADD KEY `idx_id_number` (`id_number_used`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_logs`
--
ALTER TABLE `access_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `locker_requests`
--
ALTER TABLE `locker_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nfc_scan_queue`
--
ALTER TABLE `nfc_scan_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `nfc_sessions`
--
ALTER TABLE `nfc_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_locker_assignments`
--
ALTER TABLE `user_locker_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `access_logs`
--
ALTER TABLE `access_logs`
  ADD CONSTRAINT `access_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `access_logs_ibfk_2` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `activity_logs_ibfk_2` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `id_scan_sessions`
--
ALTER TABLE `id_scan_sessions`
  ADD CONSTRAINT `id_scan_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `id_scan_sessions_ibfk_2` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `locker_requests`
--
ALTER TABLE `locker_requests`
  ADD CONSTRAINT `locker_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `locker_requests_ibfk_2` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`);

--
-- Constraints for table `user_locker_assignments`
--
ALTER TABLE `user_locker_assignments`
  ADD CONSTRAINT `user_locker_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_locker_assignments_ibfk_2` FOREIGN KEY (`locker_id`) REFERENCES `lockers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
