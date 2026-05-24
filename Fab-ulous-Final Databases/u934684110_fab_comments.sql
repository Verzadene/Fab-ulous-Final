-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 24, 2026 at 07:58 AM
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
-- Database: `u934684110_fab_comments`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `commentID` int(11) NOT NULL,
  `postID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `comment_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `gif_url` varchar(255) DEFAULT NULL COMMENT 'Giphy GIF URL selected by the user (NULL when plain-text comment)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`commentID`, `postID`, `userID`, `comment_text`, `created_at`, `gif_url`) VALUES
(1, 4, 7, 'Guys pa drop ng .dwg at .stl file', '2026-05-10 07:50:55', NULL),
(2, 3, 4, 'This is a comment', '2026-05-11 05:23:56', NULL),
(3, 7, 5, 'Wow GREAT MODEL!', '2026-05-12 03:15:22', NULL),
(4, 3, 5, 'good job', '2026-05-12 03:44:07', NULL),
(5, 4, 5, 'wow great', '2026-05-12 03:58:25', NULL),
(7, 7, 14, 'wow what a very hot model', '2026-05-12 08:01:45', NULL),
(11, 12, 4, 'Hello', '2026-05-12 14:33:24', 'https://media0.giphy.com/media/v1.Y2lkPTQwOGQ2ODE1NWRjbGI0MDlqZzI1YjdoanVoYWE1bzcweWd1cjB3bXZuaDZtdzJ2NSZlcD12MV9naWZzX3NlYXJjaCZjdD1n/Cmr1OMJ2FN0B2/100.gif'),
(12, 23, 6, '', '2026-05-13 00:37:20', 'https://media0.giphy.com/media/v1.Y2lkPTQwOGQ2ODE1cDFuMjU5eDliZTZ2eXB4NGF4emdscGppYjh5dmpvaTV3dmhhNHE1NSZlcD12MV9naWZzX3RyZW5kaW5nJmN0PWc/fUQ4rhUZJYiQsas6WD/100.gif');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`commentID`),
  ADD KEY `postID` (`postID`),
  ADD KEY `userID` (`userID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `commentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
