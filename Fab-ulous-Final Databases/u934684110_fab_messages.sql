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
-- Database: `u934684110_fab_messages`
--

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `messageID` int(11) NOT NULL,
  `senderID` int(11) NOT NULL,
  `receiverID` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gif_url` varchar(255) DEFAULT NULL COMMENT 'Giphy GIF URL selected by the user (NULL when plain-text message)',
  `image_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`messageID`, `senderID`, `receiverID`, `message_text`, `is_read`, `created_at`, `gif_url`, `image_url`) VALUES
(1, 1, 2, 'Hello', 0, '2026-05-07 17:16:35', NULL, NULL),
(3, 4, 5, 'hello', 1, '2026-05-09 12:21:06', NULL, NULL),
(4, 4, 5, '👍', 1, '2026-05-09 12:21:37', NULL, NULL),
(5, 5, 4, 'Hello Ryan Gosling', 1, '2026-05-12 03:36:31', NULL, NULL),
(6, 14, 4, 'are you eating feet?', 1, '2026-05-12 08:02:33', NULL, NULL),
(7, 4, 5, '', 1, '2026-05-12 14:32:40', 'https://media1.giphy.com/media/v1.Y2lkPTQwOGQ2ODE1NWhmN3U5ZGE5bHM2dWN1Nm9kenl4dDljMTQzOWJrb28yeG9teHNzZCZlcD12MV9naWZzX3RyZW5kaW5nJmN0PWc/xT9IgG50Fb7Mi0prBC/100.gif', NULL),
(8, 4, 5, 'Testing', 1, '2026-05-12 18:56:59', 'https://media0.giphy.com/media/v1.Y2lkPTQwOGQ2ODE1OGhtbXRibXRidDZ0ZTA5dnJsNTljMTZvZ3Y4OTQ2eDBqY21zbnFiNiZlcD12MV9naWZzX3RyZW5kaW5nJmN0PWc/JrTcsscdxJoCeBn7Pm/100.gif', NULL),
(9, 4, 5, '', 0, '2026-05-12 19:12:53', NULL, '../uploads/messages/msg_6a037bb5afede9.78849431.png'),
(10, 6, 5, '', 0, '2026-05-13 00:37:40', 'https://media2.giphy.com/media/v1.Y2lkPTQwOGQ2ODE1cDFuMjU5eDliZTZ2eXB4NGF4emdscGppYjh5dmpvaTV3dmhhNHE1NSZlcD12MV9naWZzX3RyZW5kaW5nJmN0PWc/xT9IgG50Fb7Mi0prBC/100.gif', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`messageID`),
  ADD KEY `sender_id` (`senderID`),
  ADD KEY `receiver_id` (`receiverID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `messageID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
