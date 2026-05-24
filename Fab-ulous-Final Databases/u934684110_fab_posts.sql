-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 24, 2026 at 07:57 AM
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
-- Database: `u934684110_fab_posts`
--

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `postID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `caption` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`postID`, `userID`, `caption`, `image_url`, `created_at`) VALUES
(2, 4, 'Test', NULL, '2026-05-08 17:06:16'),
(3, 4, 'test', NULL, '2026-05-09 01:08:33'),
(4, 7, 'Traffic Light . dwg', '../uploads/posts/post_6a00386e59b807.18003030.jpg', '2026-05-10 07:49:02'),
(7, 5, 'This is my 3d printing file', '../uploads/posts/post_6a029b3c999569.70065348.jpg', '2026-05-12 03:15:08'),
(8, 5, 'this is me and my friends 3d printed', '../uploads/posts/post_6a029c0681c604.25034794.png', '2026-05-12 03:18:30'),
(10, 14, 'This is my pet dog, Sydney. He is my humanoid-robot dog project. The project was started in 2009.', '../uploads/posts/post_6a02ddf385b144.48221361.jpg', '2026-05-12 07:59:47'),
(11, 15, 'This is my project called air it allows us to breath', NULL, '2026-05-12 08:02:14'),
(12, 18, 'HELLO EVERYONE!', NULL, '2026-05-12 10:03:52'),
(17, 23, 'LF +1 gold/plat WTO405', NULL, '2026-05-12 14:39:14'),
(18, 24, '3D Printed Raspberry Pi Pico based PC/Console Controller  PETG Case', '../uploads/posts/post_6a0360da2a1945.06008181.jpg', '2026-05-12 17:18:18'),
(19, 25, 'Juan for all, all for Juan', '../uploads/posts/post_6a03681cb54de2.01425163.jpeg', '2026-05-12 17:49:16'),
(20, 26, 'hello', NULL, '2026-05-12 18:36:05'),
(21, 26, 'engine startt', NULL, '2026-05-12 18:37:03'),
(23, 27, 'Mr Bean', '../uploads/posts/post_6a0378df27cf42.76674090.jpeg', '2026-05-12 19:00:47'),
(24, 30, 'mixing hell', '../uploads/posts/post_6a055add38f9f0.02816994.png', '2026-05-14 05:17:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`postID`),
  ADD KEY `userID` (`userID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `postID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
