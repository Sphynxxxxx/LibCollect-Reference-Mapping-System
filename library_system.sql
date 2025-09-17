-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 17, 2025 at 12:14 PM
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
-- Database: `library_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `book_title` varchar(255) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `action`, `description`, `book_id`, `book_title`, `category`, `user_id`, `user_name`, `ip_address`, `user_agent`, `created_at`) VALUES
(356, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:05:05'),
(357, 'add', 'Added new book: \"dfsdsdd\" - Quantity: 1, Author: rfererwer, Published: 2025 (Multi-context)', 152, 'dfsdsdd', 'BIT,HBM', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:08:34'),
(358, 'add', 'Added new book: \"dfsdsdd\" - Quantity: 1, Author: rfererwer, Published: 2025 (Multi-context)', 153, 'dfsdsdd', 'BIT,HBM', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:08:34'),
(359, 'auto_archive', 'auto_archive performed on book: \"hghghgh\" - Auto-archived due to publication year: 2000', 0, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:14:37'),
(360, 'add_archived', 'add_archived performed on book: \"hghghgh\" - Book added directly to archive - Publication year: 2000', NULL, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:14:37'),
(361, 'auto_archive', 'auto_archive performed on book: \"hghghgh\" - Auto-archived due to publication year: 2000', 0, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:14:37'),
(362, 'add_archived', 'add_archived performed on book: \"hghghgh\" - Book added directly to archive - Publication year: 2000', NULL, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:14:37'),
(363, 'auto_archive', 'auto_archive performed on book: \"hghghgh\" - Auto-archived due to publication year: 2000', 0, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:14:37'),
(364, 'add_archived', 'add_archived performed on book: \"hghghgh\" - Book added directly to archive - Publication year: 2000', NULL, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:14:37'),
(365, 'delete', 'Deleted book: \"dfsdsdd\" - Permanently removed from library', 152, 'dfsdsdd', 'BIT,HBM', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:30:50'),
(366, 'delete', 'Deleted book: \"dfsdsdd\" - Permanently removed from library', 153, 'dfsdsdd', 'BIT,HBM', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:30:53'),
(367, 'add', 'Added new book: \"fdfdfdf\" - Quantity: 1, Author: fdsfdfdfdf, Published: 2025 (Multi-context)', 154, 'fdfdfdf', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:31:31'),
(368, 'add', 'Added new book: \"gfgfgf\" - Quantity: 1, Author: gbhnghgh, Published: 2024', 155, 'gfgfgf', 'BIT', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:43:28'),
(369, 'delete', 'Deleted book: \"fdfdfdf\" - Permanently removed from library', 154, 'fdfdfdf', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:45:59'),
(370, 'delete', 'Deleted book: \"gfgfgf\" - Permanently removed from library', 155, 'gfgfgf', 'BIT', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:46:04'),
(371, 'add', 'Added new book: \"fdggfgdhgfh\" - Quantity: 1, Author: rfererwer, Published: 2024 (Multi-context)', 156, 'fdggfgdhgfh', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:46:32'),
(372, 'add', 'Added new book: \"fdggfgdhgfh\" - Quantity: 1, Author: rfererwer, Published: 2024 (Multi-context)', 157, 'fdggfgdhgfh', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:46:32'),
(373, 'add', 'Added new book: \"fgbgfgf\" - Quantity: 1, Author: rfererwer, Published: 2024', 158, 'fgbgfgf', 'COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-28 18:47:40'),
(374, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 17:55:52'),
(375, 'auto_archive', 'auto_archive performed on book: \"dfsdf\" - Auto-archived due to publication year: 2000', 0, 'dfsdf', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:06:52'),
(376, 'add_archived', 'add_archived performed on book: \"dfsdf\" - Book added directly to archive - Publication year: 2000', NULL, 'dfsdf', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:06:52'),
(377, 'permanent_delete', 'Permanently deleted archived book: dfsdf by fdsfdfdfdf', 30, 'dfsdf', 'BIT,EDUCATION', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:08:57'),
(378, 'permanent_delete', 'Permanently deleted archived book: hghghgh by rfererwer', 27, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:09:00'),
(379, 'permanent_delete', 'Permanently deleted archived book: hghghgh by rfererwer', 28, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:09:03'),
(380, 'restore', 'restore performed on book: \"hghghgh\" - Restored from archive', 159, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:09:05'),
(381, 'delete', 'Deleted book: \"fdggfgdhgfh\" - Permanently removed from library', 156, 'fdggfgdhgfh', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:09:20'),
(382, 'delete', 'Deleted book: \"fdggfgdhgfh\" - Permanently removed from library', 157, 'fdggfgdhgfh', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:09:24'),
(383, 'delete', 'Deleted book: \"hghghgh\" - Permanently removed from library', 159, 'hghghgh', 'BIT,EDUCATION,HBM,COMPSTUD', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:09:29'),
(384, 'auto_archive', 'auto_archive performed on book: \"dfdfd\" - Auto-archived due to publication year: 2020', 0, 'dfdfd', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:10:06'),
(385, 'add_archived', 'add_archived performed on book: \"dfdfd\" - Book added directly to archive - Publication year: 2020', NULL, 'dfdfd', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:10:06'),
(386, 'auto_archive', 'auto_archive performed on book: \"dfsdssd\" - Auto-archived due to publication year: 2000', 0, 'dfsdssd', 'BIT', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:23:35'),
(387, 'add_archived', 'add_archived performed on book: \"dfsdssd\" - Book added directly to archive - Publication year: 2000', NULL, 'dfsdssd', 'BIT', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:23:35'),
(388, 'permanent_delete', 'Permanently deleted archived book: dfsdssd by qwqwq', 32, 'dfsdssd', 'BIT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:23:45'),
(389, 'permanent_delete', 'Permanently deleted archived book: dfdfd by rfererwer', 31, 'dfdfd', 'BIT,EDUCATION', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:23:47'),
(390, 'auto_archive', 'auto_archive performed on book: \"sasas\" - Auto-archived due to publication year: 2000', 0, 'sasas', 'BIT', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:28:24'),
(391, 'add_archived', 'add_archived performed on book: \"sasas\" - Book added directly to archive - Publication year: 2000', NULL, 'sasas', 'BIT', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:28:24'),
(392, 'permanent_delete', 'Permanently deleted archived book: sasas by sasa', 33, 'sasas', 'BIT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:28:30'),
(393, 'manual_archive', 'Manually archived book: \"dsdsds\" - Reason: aaaaaaaaaaaa', NULL, 'dsdsds', 'BIT', 5, 'System', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:51:56'),
(394, 'manual_archive', 'Manually archived book: \"dsdsds\" - Reason: aaaaaaaaaaaa', NULL, 'dsdsds', 'BIT', 5, 'System', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:51:56'),
(395, 'permanent_delete', 'Permanently deleted archived book: dsdsds by rfererwer', 35, 'dsdsds', 'BIT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:52:18'),
(396, 'permanent_delete', 'Permanently deleted archived book: dsdsds by rfererwer', 36, 'dsdsds', 'BIT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:52:18'),
(397, 'permanent_delete', 'Permanently deleted archived book: dsdsds by rfererwer', 37, 'dsdsds', 'BIT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:52:21'),
(398, 'permanent_delete', 'Permanently deleted archived book: fgfsd by fdsfdfdfdf', 34, 'fgfsd', 'BIT', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:52:24'),
(399, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:52:36'),
(400, 'manual_archive', 'Manually archived book: \"fgdsds\" - Reason: fghfgkjhnfkgfg', NULL, 'fgdsds', 'BIT', 5, 'System', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:53:03'),
(401, 'manual_archive', 'Manually archived book: \"fgdsds\" - Reason: fghfgkjhnfkgfg', NULL, 'fgdsds', 'BIT', 5, 'System', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-29 18:53:03'),
(402, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-06 10:45:12'),
(403, 'add', 'Added new book: \"dffhghgfh\" - Quantity: 1, Author: dsfsdf, Published: 2025 (Multi-context)', 160, 'dffhghgfh', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-06 10:45:50'),
(404, 'add', 'Added new book: \"dffhghgfh\" - Quantity: 1, Author: dsfsdf, Published: 2025 (Multi-context)', 161, 'dffhghgfh', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-06 10:45:50'),
(405, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 12:31:57'),
(406, 'add', 'Added new book: \"fdfsdsdf\" - Quantity: 1, Author: dsfsdf, Published: 2025', 162, 'fdfsdsdf', 'BIT', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 12:32:16'),
(407, 'add', 'Added new book: \"7ui9rtuyioyt\" - Quantity: 1, Author: njibmv b, Published: 2025 (Multi-context)', 163, '7ui9rtuyioyt', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 12:43:55'),
(408, 'add', 'Added new book: \"7ui9rtuyioyt\" - Quantity: 1, Author: njibmv b, Published: 2025 (Multi-context)', 164, '7ui9rtuyioyt', 'BIT,EDUCATION', 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-09 12:43:55'),
(409, 'failed_login', 'Failed login attempt for email: admin@staff.isatu.edu.ph', NULL, NULL, NULL, NULL, 'admin@staff.isatu.edu.ph', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:00:27'),
(410, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:00:32'),
(411, 'manual_archive', 'Manually archived book: \"gdfgfdgd\" - Reason: Donated', NULL, 'gdfgfdgd', 'BIT,EDUCATION', 5, 'System', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:10:27'),
(412, 'manual_archive', 'Manually archived book: \"gdfgfdgd\" - Reason: Donated', NULL, 'gdfgfdgd', 'BIT,EDUCATION', 5, 'System', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:10:27'),
(413, 'logout', 'User admin logged out', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:18:39'),
(414, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:18:53'),
(415, 'logout', 'User admin logged out', NULL, NULL, NULL, 5, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:19:16'),
(416, 'failed_login', 'Failed login attempt for email: admin@staff.isatu.edu.ph', NULL, NULL, NULL, NULL, 'admin@staff.isatu.edu.ph', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:19:23'),
(417, 'signup', 'New user registered: admin (admin@staff.isatu.edu.ph) as staff', NULL, NULL, NULL, 6, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:19:44'),
(418, 'logout', 'User admin logged out', NULL, NULL, NULL, 6, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:31:21'),
(419, 'login', 'User admin (admin@staff.isatu.edu.ph) logged in successfully', NULL, NULL, NULL, 6, 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 07:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `archived_books`
--

CREATE TABLE `archived_books` (
  `id` int(11) NOT NULL,
  `original_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(50) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `subject_name` varchar(255) DEFAULT NULL,
  `semester` varchar(50) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `course_code` varchar(100) DEFAULT NULL,
  `publication_year` int(4) DEFAULT NULL,
  `book_copy_number` int(11) DEFAULT NULL,
  `total_quantity` int(11) DEFAULT NULL,
  `is_multi_context` tinyint(1) DEFAULT 0,
  `same_book_series` tinyint(1) DEFAULT 0,
  `original_created_at` timestamp NULL DEFAULT NULL,
  `original_updated_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archive_reason` varchar(255) DEFAULT NULL,
  `archived_by` varchar(100) DEFAULT NULL,
  `archiving_method` enum('auto','manual') DEFAULT 'manual'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_books`
--

INSERT INTO `archived_books` (`id`, `original_id`, `title`, `author`, `isbn`, `category`, `quantity`, `description`, `subject_name`, `semester`, `section`, `year_level`, `course_code`, `publication_year`, `book_copy_number`, `total_quantity`, `is_multi_context`, `same_book_series`, `original_created_at`, `original_updated_at`, `archived_at`, `archive_reason`, `archived_by`, `archiving_method`) VALUES
(38, 0, 'fgdsds', 'rfererwer', '006', 'BIT', 1, 'fdfsdfsdf', 'Industrial Safety', 'First Semester,Second Semester', 'B', 'First Year,Second Year', 'BIT-301', 2020, 1, 2, 1, 1, '2025-08-29 18:53:03', '2025-08-29 18:53:03', '2025-08-29 18:53:03', 'fghfgkjhnfkgfg', 'System', 'manual'),
(39, 0, 'fgdsds', 'rfererwer', '006', 'BIT', 1, 'fdfsdfsdf', 'Industrial Safety', 'First Semester,Second Semester', 'B', 'First Year,Second Year', 'BIT-301', 2020, 2, 2, 1, 1, '2025-08-29 18:53:03', '2025-08-29 18:53:03', '2025-08-29 18:53:03', 'fghfgkjhnfkgfg', 'System', 'manual'),
(40, 0, 'gdfgfdgd', 'fdsdf', '001', 'BIT,EDUCATION', 1, '', 'Educational Psychology', 'First Semester,Second Semester', 'B', 'First Year,Second Year', 'EDUC-101', 2000, 1, 2, 1, 0, '2025-09-17 07:10:27', '2025-09-17 07:10:27', '2025-09-17 07:10:27', 'Donated', 'System', 'manual'),
(41, 0, 'gdfgfdgd', 'fdsdf', '002', 'BIT,EDUCATION', 1, '', 'Educational Psychology', 'First Semester,Second Semester', 'B', 'First Year,Second Year', 'EDUC-101', 2000, 2, 2, 1, 0, '2025-09-17 07:10:27', '2025-09-17 07:10:27', '2025-09-17 07:10:27', 'Donated', 'System', 'manual');

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category` text NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `subject_name` varchar(255) DEFAULT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `publication_year` int(4) DEFAULT NULL,
  `book_copy_number` int(11) DEFAULT NULL,
  `total_quantity` int(11) DEFAULT NULL,
  `is_multi_context` tinyint(1) DEFAULT 0,
  `same_book_series` tinyint(1) DEFAULT 0,
  `year_level` text DEFAULT NULL,
  `semester` text DEFAULT NULL,
  `section` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `quantity`, `description`, `created_at`, `updated_at`, `subject_name`, `course_code`, `publication_year`, `book_copy_number`, `total_quantity`, `is_multi_context`, `same_book_series`, `year_level`, `semester`, `section`) VALUES
(158, 'fgbgfgf', 'rfererwer', '006', 'COMPSTUD', 1, '', '2025-08-28 18:47:40', '2025-08-28 18:47:40', 'Data Structures', 'COMP-101', 2024, 1, 1, 0, 1, 'First Year', 'First Semester', 'A'),
(160, 'dffhghgfh', 'dsfsdf', '001', 'BIT,EDUCATION', 1, 'dAFfds', '2025-09-06 10:45:50', '2025-09-06 10:45:50', 'gvdhfvw er', 'jfbndfb', 2025, 1, 2, 1, 0, 'First Year,Second Year', 'First Semester,Second Semester', 'B'),
(161, 'dffhghgfh', 'dsfsdf', '002', 'BIT,EDUCATION', 1, 'dAFfds', '2025-09-06 10:45:50', '2025-09-06 10:45:50', 'gvdhfvw er', 'jfbndfb', 2025, 2, 2, 1, 0, 'First Year,Second Year', 'First Semester,Second Semester', 'B'),
(162, 'fdfsdsdf', 'dsfsdf', '001', 'BIT', 1, '', '2025-09-09 12:32:16', '2025-09-09 12:32:16', 'Industrial Safety', 'BIT-101', 2025, 1, 1, 0, 0, 'First Year', 'First Semester', 'B'),
(163, '7ui9rtuyioyt', 'njibmv b', '001', 'BIT,EDUCATION', 1, 'fdsfdf', '2025-09-09 12:43:55', '2025-09-09 12:43:55', 'Educational Psychology', 'EDUC-101', 2025, 1, 2, 1, 0, 'First Year', 'First Semester,Second Semester', 'B'),
(164, '7ui9rtuyioyt', 'njibmv b', '2', 'BIT,EDUCATION', 1, 'fdsfdf', '2025-09-09 12:43:55', '2025-09-09 12:43:55', 'Educational Psychology', 'EDUC-101', 2025, 2, 2, 1, 0, 'First Year', 'First Semester,Second Semester', 'B');

-- --------------------------------------------------------

--
-- Table structure for table `borrowing`
--

CREATE TABLE `borrowing` (
  `id` int(11) NOT NULL,
  `book_id` int(11) DEFAULT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `borrower_email` varchar(100) DEFAULT NULL,
  `borrowed_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `due_date` date NOT NULL,
  `returned_date` timestamp NULL DEFAULT NULL,
  `status` enum('borrowed','returned','overdue') DEFAULT 'borrowed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(50) DEFAULT 'Library',
  `position` varchar(50) DEFAULT 'Librarian',
  `bio` text DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `role` enum('admin','librarian','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `full_name`, `phone`, `department`, `position`, `bio`, `last_login`, `role`, `created_at`, `updated_at`) VALUES
(6, 'admin', '$2y$10$QLNRZRZvGbOAn.uNCnxOpuo8NWywWWna15EVLwb4z532Dndkf2G.2', 'admin@staff.isatu.edu.ph', 'Larry Denver', '+63 091 657 8908', 'Library', 'Librarian', 'fdfgsdfdds', NULL, 'staff', '2025-09-17 07:19:44', '2025-09-17 07:31:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_book_id` (`book_id`);

--
-- Indexes for table `archived_books`
--
ALTER TABLE `archived_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_original_id` (`original_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_publication_year` (`publication_year`),
  ADD KEY `idx_archived_at` (`archived_at`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_publication_year` (`publication_year`),
  ADD KEY `idx_multi_record` (`is_multi_context`),
  ADD KEY `idx_book_series` (`same_book_series`);

--
-- Indexes for table `borrowing`
--
ALTER TABLE `borrowing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `book_id` (`book_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=420;

--
-- AUTO_INCREMENT for table `archived_books`
--
ALTER TABLE `archived_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=165;

--
-- AUTO_INCREMENT for table `borrowing`
--
ALTER TABLE `borrowing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrowing`
--
ALTER TABLE `borrowing`
  ADD CONSTRAINT `borrowing_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
