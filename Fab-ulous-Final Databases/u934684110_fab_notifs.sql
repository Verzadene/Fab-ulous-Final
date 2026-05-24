-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 24, 2026 at 08:01 AM
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
-- Database: `u934684110_fab_notifs`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notifID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `type` enum('like','comment','friend_request','friend_accept','commission_submitted','commission_approved','commission_updated','commission_paid','message') NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notifID`, `userID`, `actor_id`, `type`, `post_id`, `ref_id`, `is_read`, `created_at`) VALUES
(1, 2, 1, 'message', NULL, 1, 1, '2026-05-07 17:16:35'),
(5, 6, 4, 'commission_updated', NULL, 4, 1, '2026-05-10 23:28:40'),
(7, 5, 5, 'commission_paid', NULL, 7, 1, '2026-05-12 03:35:16'),
(9, 4, 5, 'friend_request', NULL, 2, 1, '2026-05-12 03:37:00'),
(10, 7, 5, 'friend_request', NULL, 3, 0, '2026-05-12 03:37:12'),
(11, 6, 5, 'friend_request', NULL, 4, 1, '2026-05-12 03:37:15'),
(12, 5, 4, 'commission_updated', NULL, 7, 1, '2026-05-12 03:41:37'),
(13, 4, 5, 'like', 3, NULL, 1, '2026-05-12 03:44:00'),
(14, 4, 5, 'like', 2, NULL, 1, '2026-05-12 03:44:01'),
(15, 5, 4, 'commission_updated', NULL, 7, 1, '2026-05-12 03:46:42'),
(16, 7, 5, 'like', 4, NULL, 0, '2026-05-12 03:58:20'),
(17, 6, 5, 'like', 9, NULL, 1, '2026-05-12 03:59:04'),
(18, 5, 5, 'commission_paid', NULL, 8, 0, '2026-05-12 04:11:35'),
(19, 5, 4, 'commission_updated', NULL, 8, 0, '2026-05-12 05:29:13'),
(21, 14, 4, 'commission_updated', NULL, 9, 0, '2026-05-12 08:03:49'),
(22, 5, 17, 'like', 8, NULL, 1, '2026-05-12 08:21:40'),
(23, 15, 4, 'like', 11, NULL, 0, '2026-05-12 10:03:32'),
(24, 15, 18, 'like', 11, NULL, 0, '2026-05-12 10:04:22'),
(25, 18, 4, 'friend_request', NULL, 5, 0, '2026-05-12 10:06:39'),
(30, 24, 4, 'like', 18, NULL, 0, '2026-05-12 17:28:06'),
(31, 5, 4, 'commission_approved', NULL, 10, 0, '2026-05-12 17:40:37'),
(32, 5, 7, 'like', 8, NULL, 0, '2026-05-12 17:56:39'),
(33, 24, 5, 'like', 18, NULL, 0, '2026-05-12 18:19:09'),
(34, 26, 4, 'like', 21, NULL, 0, '2026-05-12 18:42:16'),
(35, 5, 4, 'commission_updated', NULL, 10, 1, '2026-05-12 18:56:11'),
(37, 5, 4, 'message', NULL, 4, 0, '2026-05-12 19:12:53'),
(38, 6, 6, 'commission_paid', NULL, 11, 0, '2026-05-13 00:28:13'),
(39, 6, 5, 'commission_updated', NULL, 11, 0, '2026-05-13 00:28:59'),
(40, 5, 6, 'message', NULL, 6, 0, '2026-05-13 00:37:40'),
(41, 25, 30, 'like', 19, NULL, 0, '2026-05-14 04:39:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notifID`),
  ADD KEY `userID` (`userID`),
  ADD KEY `actor_id` (`actor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notifID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
