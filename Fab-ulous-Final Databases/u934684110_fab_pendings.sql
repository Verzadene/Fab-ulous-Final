-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 24, 2026 at 08:02 AM
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
-- Database: `u934684110_fab_pendings`
--

-- --------------------------------------------------------

--
-- Table structure for table `pending_registrations`
--

CREATE TABLE `pending_registrations` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pending_registrations`
--

INSERT INTO `pending_registrations` (`id`, `email`, `username`, `password_hash`, `first_name`, `last_name`, `verification_code`, `google_id`, `expires_at`, `created_at`) VALUES
(8, 'ljvidal555@gmail.com', 'jlo', '$2y$10$JoRheu53MeC8KpvCXpKbUOKgJ5svN2jFCHKqWG5XnIffdL.BIBHd2', 'Jaylorine', 'Vidal', '079267', NULL, '2026-05-12 18:34:08', '2026-05-12 09:34:08'),
(9, 'sydhaloot@gmail.com', 'JaneSydGavroche', '$2y$10$2LDQCzjR3Qrhy9NexaW4Lu3Z6siwP6WpGGJT68rk2Wg2cGvRyz9Py', 'Jane', 'Gavroche', '835466', NULL, '2026-05-12 19:06:10', '2026-05-12 10:06:10'),
(15, 'bjs0111@dlsud.edu.ph', 'Godfrey', '$2y$10$Zp142eIQFOTTM/CR00hhKOMqLPkwDRYdhZP9IoeNvPg3ia2swZyFa', 'God', 'Frey', '238282', NULL, '2026-05-13 11:09:25', '2026-05-13 02:07:26'),
(17, 'konomaken@gmail.com', 'Konomaken@gmail.com', '$2y$10$Emhti4./iRvLIre9aNFqCeRleckAd5twW/XuDv033FysWmQ5CvzIG', 'Godfrey', 'Babilonia', '549004', NULL, '2026-05-13 12:44:56', '2026-05-13 03:44:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pending_registrations`
--
ALTER TABLE `pending_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pending_registrations`
--
ALTER TABLE `pending_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
