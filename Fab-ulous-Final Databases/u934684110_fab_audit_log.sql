-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 24, 2026 at 08:03 AM
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
-- Database: `u934684110_fab_audit_log`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `logID` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `admin_username` varchar(50) NOT NULL,
  `action` varchar(512) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `visibility_role` enum('admin','super_admin') NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL DEFAULT '2000-01-01 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`logID`, `admin_id`, `admin_username`, `action`, `target_type`, `target_id`, `visibility_role`, `created_at`) VALUES
(17, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-08 23:21:55'),
(18, 4, 'Zen', 'User Login', 'account', 4, 'admin', '2026-05-09 01:06:09'),
(19, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-09 01:13:48'),
(20, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-09 09:07:01'),
(21, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-09 09:07:30'),
(22, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-09 09:08:26'),
(23, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-09 09:51:57'),
(24, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-09 09:52:04'),
(25, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-09 12:28:41'),
(26, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-09 19:57:10'),
(27, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2026-05-09 23:46:54'),
(28, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-09 23:46:58'),
(29, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-10 08:23:21'),
(30, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-10 10:04:25'),
(31, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-10 10:04:29'),
(32, 4, 'Zen', 'Role Promoted: @Nyel12 (ID 5) promoted from user to admin.', '0', 5, 'super_admin', '2026-05-10 10:27:53'),
(33, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-10 11:55:52'),
(34, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-10 11:56:26'),
(35, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-10 11:56:50'),
(36, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-10 11:57:24'),
(37, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2026-05-10 11:57:26'),
(38, 4, 'Zen', 'User Login', 'account', 4, 'admin', '2026-05-10 12:05:59'),
(39, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-10 16:29:05'),
(40, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-10 16:56:11'),
(41, 6, 'The Hunt Man', 'Post created: @The Hunt Man posted #5 — \"the post man\"', 'post', 5, 'admin', '2026-05-10 17:05:16'),
(42, 6, 'The Hunt Man', 'Commission submitted: @The Hunt Man — #4 \"Test Title I love 3d print\" — \"3d print description please\"', 'commission', 4, 'admin', '2026-05-10 17:27:04'),
(43, 6, 'The Hunt Man', 'Commission submitted: @The Hunt Man — #5 \"asdasd\" — \"sda\"', 'commission', 5, 'admin', '2026-05-10 17:35:07'),
(44, 6, 'The Hunt Man', 'Commission submitted: @The Hunt Man — #6 \"ada\" — \"dad\"', 'commission', 6, 'admin', '2026-05-10 17:35:21'),
(45, 4, 'Zen', 'Deleted commission #1 \"Test Title\". Reason: \'Hey\'.', '0', 1, 'admin', '2026-05-10 17:48:06'),
(46, 4, 'Zen', 'Deleted commission #2 \"Test\". Reason: \'Type\'.', '0', 2, 'admin', '2026-05-10 17:48:16'),
(47, 4, 'Zen', 'Removed post #5 owned by \'The Hunt Man\' (userID: 6). Reason: \'Test delete\'. Email sent to owner.', '0', 5, 'admin', '2026-05-10 17:52:39'),
(48, 4, 'Zen', 'Deleted commission #6 \"ada\". Reason: \'Test delete\'. Email notification sent.', '0', 6, 'super_admin', '2026-05-10 18:08:56'),
(49, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 06:57:39'),
(50, 4, 'Zen', 'Commission updated: #4 \"Test Title I love 3d print\" | Status: Pending | Amount: ₱0.00 → ₱500.00', 'commission', 4, 'admin', '2026-05-11 07:06:22'),
(51, 4, 'Zen', 'Commission updated: #4 \"Test Title I love 3d print\" | Status: Pending | Amount: ₱500.00', 'commission', 4, 'admin', '2026-05-11 07:06:23'),
(52, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-11 07:06:40'),
(53, 4, 'Zen', 'Commission updated: #4 \"Test Title I love 3d print\" | Status: Pending → Ongoing | Amount: ₱500.00', 'commission', 4, 'admin', '2026-05-11 07:28:40'),
(54, 4, 'Zen', 'Commission updated: #4 \"Test Title I love 3d print\" | Status: Ongoing | Amount: ₱500.00', 'commission', 4, 'admin', '2026-05-11 07:28:42'),
(55, 4, 'Zen', 'Commission updated: #4 \"Test Title I love 3d print\" | Status: Ongoing | Amount: ₱500.00', 'commission', 4, 'admin', '2026-05-11 07:28:45'),
(56, 4, 'Zen', 'Commission updated: #4 \"Test Title I love 3d print\" | Status: Ongoing | Amount: ₱500.00', 'commission', 4, 'admin', '2026-05-11 07:28:45'),
(57, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 11:50:44'),
(58, 4, 'Zen', 'Commission updated: #5 \"asdasd\" | Status: Pending | Amount: ₱0.00 → ₱400.00', 'commission', 5, 'admin', '2026-05-11 11:51:16'),
(59, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-11 12:33:15'),
(60, 8, 'Jane', 'Post created: @Jane posted #6 [+image] — \"Jeyps x Marvs\"', 'post', 6, 'admin', '2026-05-11 12:35:46'),
(61, 4, 'Zen', 'Removed post #6 owned by \'Jane\' (userID: 8). Reason: \'The post abuses the community guidelines so we had to remove it\'. Email sent to owner.', '0', 6, 'admin', '2026-05-11 12:37:51'),
(62, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 13:15:26'),
(63, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-11 14:11:07'),
(64, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2026-05-11 14:56:55'),
(65, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 16:16:03'),
(66, 4, 'Zen', '0', 'account', 8, 'super_admin', '2026-05-11 16:16:49'),
(67, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 16:17:00'),
(68, 4, 'Zen', '0', 'account', 3, 'super_admin', '2026-05-11 16:18:46'),
(69, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 16:21:06'),
(70, 4, 'Zen', '0', 'account', 9, 'super_admin', '2026-05-11 16:45:10'),
(71, 4, 'Zen', '0', 'account', 10, 'super_admin', '2026-05-11 16:55:40'),
(72, 10, 'Zenny', 'User Logout', 'account', 10, 'admin', '2026-05-11 16:57:51'),
(73, 4, 'Zen', '0', 'account', 11, 'super_admin', '2026-05-11 16:59:06'),
(74, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-11 17:19:01'),
(75, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 17:19:13'),
(76, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-11 17:23:27'),
(77, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-11 21:50:47'),
(78, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 22:14:04'),
(79, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-11 22:14:26'),
(80, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 22:14:30'),
(81, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2026-05-11 22:42:38'),
(82, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2026-05-11 22:42:46'),
(83, 4, 'Zen', 'Banned user ID 7. Reason: test', '0', 7, 'super_admin', '2026-05-11 22:47:44'),
(84, 4, 'Zen', 'Unbanned user ID 7.', '0', 7, 'admin', '2026-05-11 22:47:57'),
(85, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2000-01-01 00:00:00'),
(86, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2000-01-01 00:00:00'),
(87, 5, 'Nyel12', 'User Logout', 'account', 5, 'admin', '2000-01-01 00:00:00'),
(88, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2000-01-01 00:00:00'),
(89, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'admin', '2000-01-01 00:00:00'),
(90, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2000-01-01 00:00:00'),
(91, 6, 'The Hunt Man', 'User Login', 'account', 6, 'admin', '2000-01-01 00:00:00'),
(92, 4, 'Zen', 'Banned user ID 6. Reason: test', '0', 6, 'super_admin', '2026-05-12 11:01:56'),
(93, 4, 'Zen', 'Unbanned user ID 6.', '0', 6, 'admin', '2026-05-12 11:02:04'),
(94, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2000-01-01 00:00:00'),
(95, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2000-01-01 00:00:00'),
(96, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2000-01-01 00:00:00'),
(97, 5, 'Nyel12', 'Post created: @Nyel12 posted #7 [+image] — \"This is my 3d printing file\"', 'post', 7, 'admin', '2000-01-01 00:00:00'),
(98, 5, 'Nyel12', 'Post created: @Nyel12 posted #8 [+image] — \"this is me and my friends 3d printed\"', 'post', 8, 'admin', '2000-01-01 00:00:00'),
(99, 5, 'Nyel12', 'User Logout', 'account', 5, 'admin', '2026-05-12 11:20:35'),
(100, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-12 11:21:10'),
(101, 5, 'Nyel12', 'Commission submitted: @Nyel12 — #7 \"F1 Robot\" [+attachment] — \"This is a stl file of robot\"', 'commission', 7, 'admin', '2026-05-12 11:27:06'),
(102, 4, 'Zen', 'Commission updated: #7 \"F1 Robot\" | Status: Pending | Amount: ₱0.00 → ₱1,000.00', 'commission', 7, 'admin', '2026-05-12 11:27:22'),
(103, 4, 'Zen', 'Commission updated: #7 \"F1 Robot\" | Status: Pending → Ongoing | Amount: ₱1,000.00', 'commission', 7, 'admin', '2026-05-12 11:41:37'),
(104, 4, 'Zen', 'Commission updated: #7 \"F1 Robot\" | Status: Ongoing | Amount: ₱1,000.00', 'commission', 7, 'admin', '2026-05-12 11:41:54'),
(105, 4, 'Zen', 'Commission updated: #7 \"F1 Robot\" | Status: Ongoing → Completed | Amount: ₱1,000.00', 'commission', 7, 'admin', '2026-05-12 11:46:42'),
(106, 4, 'Zen', 'Commission updated: #7 \"F1 Robot\" | Status: Completed | Amount: ₱1,000.00', 'commission', 7, 'admin', '2026-05-12 11:46:43'),
(107, 6, 'The Hunt Man', 'Post created: @The Hunt Man posted #9 — \"Test Post\"', 'post', 9, 'admin', '2000-01-01 00:00:00'),
(108, 5, 'Nyel12', 'Commission submitted: @Nyel12 — #8 \"reQUEST TEST\" [+attachment] — \"Mic check 12\"', 'commission', 8, 'admin', '2026-05-12 12:10:50'),
(109, 4, 'Zen', 'Commission updated: #8 \"reQUEST TEST\" | Status: Pending | Amount: ₱0.00 → ₱10.00', 'commission', 8, 'admin', '2026-05-12 12:11:10'),
(110, 5, 'Nyel12', 'User Nyel12 paid commission #8 (₱10.00) via PayMongo checkout.', 'commission', 8, '', '2026-05-12 12:11:35'),
(111, 5, 'Nyel12', 'User Logout', 'account', 5, 'admin', '2026-05-12 12:21:35'),
(112, 12, 'KingSheeran12', 'User Logout', 'account', 12, 'admin', '2026-05-12 12:54:08'),
(113, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 13:18:59'),
(114, 4, 'Zen', 'Commission updated: #8 \"reQUEST TEST\" | Status: Pending → Ongoing | Amount: ₱10.00', 'commission', 8, 'admin', '2026-05-12 13:29:13'),
(115, 4, 'Zen', 'User Logout', 'account', 4, 'super_admin', '2026-05-12 13:47:01'),
(116, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 13:47:09'),
(117, 7, 'GainEager', 'User Login via Google OAuth', 'account', 7, 'admin', '2026-05-12 13:50:53'),
(118, 4, 'Zen', 'User Logout', 'account', 4, 'super_admin', '2026-05-12 13:51:44'),
(119, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-12 13:52:52'),
(120, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 13:58:53'),
(121, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2000-01-01 00:00:00'),
(122, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 14:07:36'),
(123, 4, 'Zen', 'Removed post #9 owned by \'The Hunt Man\' (userID: 6). Reason: \'test\'. Email sent to owner.', '0', 9, 'super_admin', '2026-05-12 14:26:33'),
(124, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2000-01-01 00:00:00'),
(125, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2026-05-12 14:48:34'),
(126, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 15:44:09'),
(127, 14, 'yoomiel2905', 'Post created: @yoomiel2905 posted #10 [+image] — \"This is my pet dog, Sydney. He is my humanoid-robot dog project. The project was started in 2009.\"', 'post', 10, 'admin', '2000-01-01 00:00:00'),
(128, 15, 'mgp', 'Post created: @mgp posted #11 — \"This is my project called air it allows us to breath\"', 'post', 11, 'admin', '2000-01-01 00:00:00'),
(129, 16, 'joe', 'User Logout', 'account', 16, 'admin', '2026-05-12 16:02:23'),
(130, 16, 'joe', 'User Login via Google OAuth', 'account', 16, 'admin', '2026-05-12 16:02:30'),
(131, 14, 'yoomiel2905', 'Commission submitted: @yoomiel2905 — #9 \"Please help me\" [+attachment] — \"i want food\"', 'commission', 9, 'admin', '2026-05-12 16:03:11'),
(132, 4, 'Zen', 'Commission updated: #9 \"Please help me\" | Status: Pending | Amount: ₱0.00 → ₱500.00', 'commission', 9, 'admin', '2026-05-12 16:03:30'),
(133, 4, 'Zen', 'Commission updated: #9 \"Please help me\" | Status: Pending | Amount: ₱500.00', 'commission', 9, 'admin', '2026-05-12 16:03:33'),
(134, 4, 'Zen', 'Commission updated: #9 \"Please help me\" | Status: Pending → Ongoing | Amount: ₱500.00', 'commission', 9, 'admin', '2026-05-12 16:03:49'),
(135, 4, 'Zen', 'User Logout', 'account', 4, 'super_admin', '2026-05-12 16:15:10'),
(136, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 16:15:15'),
(137, 4, 'Zen', 'User Logout', 'account', 4, 'super_admin', '2026-05-12 16:20:34'),
(138, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 16:20:40'),
(139, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 16:28:32'),
(140, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 16:29:19'),
(141, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 16:51:57'),
(142, 4, 'Zen', 'User Logout', 'account', 4, 'super_admin', '2026-05-12 16:53:03'),
(143, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 17:34:49'),
(144, 18, 'kennyman', 'Post created: @kennyman posted #12 — \"HELLO EVERYONE!\"', 'post', 12, 'admin', '2000-01-01 00:00:00'),
(145, 18, 'heheheyum', 'Post created: @heheheyum posted #13 [+image] — \"HEHEHEHHEE\"', 'post', 13, 'admin', '2000-01-01 00:00:00'),
(146, 4, 'Zen', 'Removed post #13 owned by \'heheheyum\' (userID: 18). Reason: \'HOY HOY\'. Email sent to owner.', '0', 13, 'super_admin', '2026-05-12 18:16:00'),
(147, 19, 'JaneGavroche', 'Post created: @JaneGavroche posted #14 [+image] — \"Before Manginasal\"', 'post', 14, 'admin', '2000-01-01 00:00:00'),
(148, 19, 'JaneGavroche', 'Post created: @JaneGavroche posted #15 [+image] — \"After Manginasal\"', 'post', 15, 'admin', '2000-01-01 00:00:00'),
(149, 19, 'JaneGavroche', 'Post created: @JaneGavroche posted #16 [+image] — \"Happy Marcus Day!\"', 'post', 16, 'admin', '2000-01-01 00:00:00'),
(150, 4, 'Zen', 'Removed post #16 owned by \'JaneGavroche\' (userID: 19). Reason: \'hoy\'. Email sent to owner.', '0', 16, 'super_admin', '2026-05-12 18:53:07'),
(151, 4, 'Zen', 'Removed post #15 owned by \'JaneGavroche\' (userID: 19). Reason: \'bad to\'. Email sent to owner.', '0', 15, 'super_admin', '2026-05-12 18:53:17'),
(152, 4, 'Zen', 'Removed post #14 owned by \'JaneGavroche\' (userID: 19). Reason: \'bad to\'. Email sent to owner.', '0', 14, 'super_admin', '2026-05-12 18:53:27'),
(153, 4, 'Zen', 'User Logout', 'account', 4, 'super_admin', '2026-05-12 19:12:25'),
(154, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 19:12:30'),
(155, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 19:15:43'),
(156, 21, 'cdmonzon', 'User Logout', 'account', 21, 'admin', '2026-05-12 19:41:25'),
(157, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-12 22:29:50'),
(158, 23, 'Wolf120405', 'Post created: @Wolf120405 posted #17 — \"LF +1 gold/plat WTO405\"', 'post', 17, 'admin', '2000-01-01 00:00:00'),
(159, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-12 22:59:21'),
(160, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-12 23:44:24'),
(161, 4, 'Zen', 'Banned user ID 5. Reason: test', '0', 5, 'super_admin', '2026-05-12 23:46:52'),
(162, 5, 'Nyel12', 'User Logout', 'account', 5, 'admin', '2026-05-12 23:47:24'),
(163, 4, 'Zen', 'Unbanned user ID 5.', '0', 5, 'super_admin', '2026-05-12 23:48:08'),
(164, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-12 23:48:52'),
(165, 6, 'The Hunt Man', 'User Login', 'account', 6, 'admin', '2026-05-13 00:26:37'),
(166, 5, 'Nyel12', 'Commission submitted: @Nyel12 — #10 \"Sample STL\" [+attachment] — \"This is sample file\"', 'commission', 10, 'admin', '2026-05-13 00:49:40'),
(167, 6, 'The Hunt Man', 'Commission edited by owner: @The Hunt Man — #5 \"asdasd\"', 'commission', 5, 'admin', '2026-05-13 00:50:13'),
(168, 6, 'The Hunt Man', 'Commission edited by owner: @The Hunt Man — #5 \"asdasd\"', 'commission', 5, 'admin', '2026-05-13 00:50:14'),
(169, 5, 'Nyel12', 'Commission edited by owner: @Nyel12 — #10 \"Sample STL\" [new attachment]', 'commission', 10, 'admin', '2026-05-13 00:50:30'),
(170, 5, 'Nyel12', 'User Logout', 'account', 5, 'admin', '2026-05-13 00:53:25'),
(171, 24, 'pko0631', 'Post created: @pko0631 posted #18 [+image] — \"3D Printed Raspberry Pi Pico based PC/Console Controller  PETG Case\"', 'post', 18, 'admin', '2000-01-01 00:00:00'),
(172, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-13 01:38:07'),
(173, 4, 'Zen', 'Commission updated: #10 \"Sample STL\" | Status: Pending → Accepted', 'commission', 10, 'super_admin', '2026-05-13 01:40:37'),
(174, 4, 'Zen', 'Commission updated: #10 \"Sample STL\" | Status: Accepted | Amount: ₱0.00 → ₱400.00', 'commission', 10, 'super_admin', '2026-05-13 01:40:43'),
(175, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-13 01:49:14'),
(176, 25, 'Ronald Bato Dela Rosa', 'Post created: @Ronald Bato Dela Rosa posted #19 [+image] — \"Juan for all, all for Juan\"', 'post', 19, 'admin', '2000-01-01 00:00:00'),
(177, 7, 'Clicker', 'User Login via Google OAuth', 'account', 7, 'admin', '2026-05-13 01:56:10'),
(178, 7, 'Clicker', 'User Logout', 'account', 7, 'admin', '2026-05-13 01:57:27'),
(179, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2026-05-13 02:33:37'),
(180, 26, 'xoxososss', 'Post created: @xoxososss posted #20 — \"hello\"', 'post', 20, 'admin', '2000-01-01 00:00:00'),
(181, 26, 'xoxososss', 'Post created: @xoxososss posted #21 — \"engine startt\"', 'post', 21, 'admin', '2000-01-01 00:00:00'),
(182, 26, 'xoxososss', 'Post created: @xoxososss posted #22 [+image] — \"wow\"', 'post', 22, 'admin', '2000-01-01 00:00:00'),
(183, 26, 'xoxososss', 'User deleted their own post (ID: #22)', 'post', 22, 'admin', '2000-01-01 00:00:00'),
(184, 4, 'Zen', 'Commission updated: #10 \"Sample STL\" | Status: Accepted → Ongoing | Amount: ₱400.00', 'commission', 10, 'super_admin', '2026-05-13 02:56:11'),
(185, 27, 'zhylecheslie', 'Post created: @zhylecheslie posted #23 [+image] — \"Mr Bean\"', 'post', 23, 'admin', '2000-01-01 00:00:00'),
(186, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-13 08:17:01'),
(187, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2000-01-01 00:00:00'),
(188, 5, 'Nyel12', 'User Login', 'account', 5, 'admin', '2026-05-13 08:19:03'),
(189, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-13 08:21:28'),
(190, 6, 'The Hunt Man', 'Commission submitted: @The Hunt Man — #11 \"This is sample project\" [+attachment] — \"Description\"', 'commission', 11, 'admin', '2026-05-13 08:22:41'),
(191, 6, 'The Hunt Man', 'Commission edited by owner: @The Hunt Man — #11 \"This is sample project\" [new attachment]', 'commission', 11, 'admin', '2026-05-13 08:23:04'),
(192, 6, 'The Hunt Man', 'Commission submitted: @The Hunt Man — #12 \"Sample\" [+attachment] — \"Sample\"', 'commission', 12, 'admin', '2026-05-13 08:25:37'),
(193, 5, 'Nyel12', 'Commission updated: #11 \"This is sample project\" | Status: Pending | Amount: ₱0.00 → ₱100.00', 'commission', 11, 'admin', '2026-05-13 08:27:42'),
(194, 6, 'The Hunt Man', 'User The Hunt Man paid commission #11 (₱100.00) via PayMongo checkout.', 'commission', 11, '', '2026-05-13 08:28:13'),
(195, 5, 'Nyel12', 'Commission updated: #11 \"This is sample project\" | Status: Pending → Ongoing | Amount: ₱100.00', 'commission', 11, 'admin', '2026-05-13 08:28:59'),
(196, 5, 'Nyel12', 'Commission updated: #11 \"This is sample project\" | Status: Ongoing | Amount: ₱100.00', 'commission', 11, 'admin', '2026-05-13 08:29:01'),
(197, 5, 'Nyel12', 'Commission updated: #11 \"This is sample project\" | Status: Ongoing | Amount: ₱100.00', 'commission', 11, 'admin', '2026-05-13 08:31:10'),
(198, 5, 'Nyel12', 'Commission updated: #11 \"This is sample project\" | Status: Ongoing | Amount: ₱100.00', 'commission', 11, 'admin', '2026-05-13 08:31:11'),
(199, 6, 'The Hunt Man', 'User Logout', 'account', 6, 'admin', '2026-05-13 08:36:27'),
(200, 6, 'The Hunt Man', 'User Login via Google OAuth', 'account', 6, 'admin', '2026-05-13 08:36:54'),
(201, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-14 01:16:19'),
(202, 30, 'GIBA', 'Post created: @GIBA posted #24 [+image] — \"mixing hell\"', 'post', 24, 'admin', '2000-01-01 00:00:00'),
(203, 30, 'GIBA', 'Post created: @GIBA posted #25 [+image] — \"mixing hell\"', 'post', 25, 'admin', '2000-01-01 00:00:00'),
(204, 30, 'GIBA', 'User deleted their own post (ID: #25)', 'post', 25, 'admin', '2000-01-01 00:00:00'),
(205, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-14 14:00:53'),
(206, 4, 'Zen', 'User Logout', 'account', 4, 'admin', '2000-01-01 00:00:00'),
(207, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-18 01:05:20'),
(208, 4, 'Zen', 'User Login via Google OAuth', 'account', 4, 'super_admin', '2026-05-24 12:35:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`logID`),
  ADD KEY `admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `logID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=209;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
