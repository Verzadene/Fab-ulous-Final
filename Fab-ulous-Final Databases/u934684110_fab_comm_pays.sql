-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 24, 2026 at 08:00 AM
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
-- Database: `u934684110_fab_comm_pays`
--

-- --------------------------------------------------------

--
-- Table structure for table `commission_payments`
--

CREATE TABLE `commission_payments` (
  `paymentID` int(11) NOT NULL,
  `commissionID` int(11) NOT NULL,
  `paymongo_payment_id` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commission_payments`
--

INSERT INTO `commission_payments` (`paymentID`, `commissionID`, `paymongo_payment_id`, `status`, `amount`, `created_at`, `paid_at`) VALUES
(1, 4, 'cs_5d5a7b8d87576baaa7fb8df0', 'pending', 500.00, '2026-05-10 23:17:02', NULL),
(2, 5, 'cs_fbf6db03ea2741350b0765d6', 'failed', 400.00, '2026-05-11 06:11:22', NULL),
(3, 5, 'cs_20baa67fe6856871d13ca47a', 'pending', 400.00, '2026-05-12 02:19:57', NULL),
(4, 7, 'checkout_return_6a029ff4b54d93.29103616', 'paid', 1000.00, '2026-05-12 03:27:30', '2026-05-12 03:35:16'),
(5, 8, 'checkout_return_6a02a877cc98b5.85682594', 'paid', 10.00, '2026-05-12 04:11:16', '2026-05-12 04:11:35'),
(6, 9, 'cs_905eece69b0b300be81fe912', 'pending', 500.00, '2026-05-12 08:03:51', NULL),
(7, 10, 'cs_52b7ea775ee47d3baed083af', 'failed', 400.00, '2026-05-12 17:41:02', NULL),
(8, 10, 'cs_bd31b5c9164b629e811e1bf7', 'pending', 400.00, '2026-05-12 17:41:24', NULL),
(9, 11, 'checkout_return_6a03c59d00a102.56160253', 'paid', 100.00, '2026-05-13 00:27:52', '2026-05-13 00:28:13');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD PRIMARY KEY (`paymentID`),
  ADD UNIQUE KEY `paymongo_payment_id` (`paymongo_payment_id`),
  ADD KEY `commissionID` (`commissionID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `commission_payments`
--
ALTER TABLE `commission_payments`
  MODIFY `paymentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
