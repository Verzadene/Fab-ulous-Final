-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 24, 2026 at 07:59 AM
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
-- Database: `u934684110_fab_commission`
--

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `commissionID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `commission_name` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Accepted','Ongoing','Delayed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `stl_file_url` varchar(255) DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commissions`
--

INSERT INTO `commissions` (`commissionID`, `userID`, `commission_name`, `description`, `amount`, `status`, `stl_file_url`, `admin_note`, `created_at`) VALUES
(3, 4, 'Test Commission', 'Test Description', 0.00, 'Pending', 'uploads/commissions/4_1778373054.pdf', NULL, '2026-05-10 00:30:54'),
(4, 6, 'Test Title I love 3d print', '3d print description please', 500.00, 'Ongoing', NULL, '', '2026-05-10 09:27:04'),
(5, 6, 'asdasd', 'sda', 400.00, 'Pending', NULL, '', '2026-05-10 09:35:07'),
(7, 5, 'F1 Robot', 'This is a stl file of robot', 1000.00, 'Completed', 'uploads/commissions/5_1778556424.stl', 'Days to Last for 3D printing is 5 days (Wait at May 17, 2026)', '2026-05-12 03:27:06'),
(8, 5, 'reQUEST TEST', 'Mic check 12', 10.00, 'Ongoing', 'uploads/commissions/5_1778559050.stl', '', '2026-05-12 04:10:50'),
(9, 14, 'Please help me', 'i want food', 500.00, 'Ongoing', 'uploads/commissions/14_1778572991.pdf', 'Pay it now', '2026-05-12 08:03:11'),
(10, 5, 'Sample STL', 'This is sample file', 400.00, 'Ongoing', 'uploads/commissions/5_1778604630.pdf', '', '2026-05-12 16:49:40'),
(11, 6, 'This is sample project', 'Description', 100.00, 'Ongoing', 'uploads/commissions/6_1778631784.pdf', 'Get by 2 days', '2026-05-13 00:22:41'),
(12, 6, 'Sample', 'Sample', 0.00, 'Pending', 'uploads/commissions/6_1778631936.pdf', NULL, '2026-05-13 00:25:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`commissionID`),
  ADD KEY `userID` (`userID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `commissionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
