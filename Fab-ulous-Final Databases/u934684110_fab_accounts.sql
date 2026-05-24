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
-- Database: `u934684110_fab_accounts`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `bio` varchar(255) DEFAULT NULL,
  `role` enum('user','admin','super_admin') NOT NULL DEFAULT 'user',
  `banned` tinyint(1) NOT NULL DEFAULT 0,
  `google_id` varchar(255) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `mfa_code` varchar(6) DEFAULT NULL,
  `mfa_code_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `username`, `email`, `password`, `first_name`, `last_name`, `bio`, `role`, `banned`, `google_id`, `profile_pic`, `mfa_code`, `mfa_code_expires_at`, `created_at`) VALUES
(4, 'Zen', 'zehgenious@gmail.com', '$2y$10$WYbuDnAIFx0FfwZlWRsyxe4N.efNJlkSn3viZubRB9D5mFNsscXxq', 'Zillen', 'Bayson', 'This is a bio', 'super_admin', 0, '110315846807180400740', '4_1778334314.jpg', '867416', '2026-05-12 02:21:47', '2026-05-08 15:13:09'),
(5, 'Nyel12', 'bct0030@dlsud.edu.ph', '$2y$10$u3qcxR0f99NWLWtRMDAE0Oc9JzCDqtUgnSzloBvEkT50E7SgTRGc2', 'LELIZABETH', 'JAMES', 'I am elizabron', 'admin', 0, NULL, '5_1778474180.jpeg', NULL, NULL, '2026-05-09 02:09:44'),
(6, 'The Hunt Man', 'huncegub69420@gmail.com', '$2y$10$7j2RRdMZFw.W/24GgRV2fu6CcZFaQcTHpV/3/fv./LK.T4LJ8sFtu', 'Hunce', 'Gub', NULL, 'user', 0, '101386300672699008960', NULL, NULL, NULL, '2026-05-09 15:46:47'),
(7, 'Clicker', 'yiannispedrozo.01@gmail.com', '$2y$10$8XWdzrwz4dTj1kVa98tOre7pHYtk.OfYqeBHHDLcag993RLQiv9RC', 'Gain', 'Eager', NULL, 'user', 0, '103762979422464413448', NULL, NULL, NULL, '2026-05-10 07:48:43'),
(12, 'KingSheeran12', 'baluyutc10@gmail.com', '$2y$10$kdG/YA.7.PCO6DgWRkn4RuLXbXnUo2/8e0TPylGl6NFBOqib9Dbda', 'JebronLame', 'King', NULL, 'user', 0, NULL, NULL, NULL, NULL, '2026-05-12 04:24:13'),
(13, 'gridquils', 'gwaiprogs@gmail.com', '$2y$10$qYW8zKv50Sui8ZK2cqxIzetjyYgG/vYwhoyD9/.GzazWBw/AtSYj.', 'Ingrid', 'Quilang', NULL, 'user', 0, '101543484599841377575', NULL, NULL, NULL, '2026-05-12 07:55:04'),
(14, 'yum', 'aikinejaynhaloot@gmail.com', '$2y$10$uXPqqiEQYlbZeBFoaVBDeuRs7HKO4Dv/p4yjS4b3JvtEJGO2PreIq', 'Aikine Jayn', 'Haloot', '', 'user', 0, '104361988674050267360', '14_1778574218.png', NULL, NULL, '2026-05-12 07:58:25'),
(15, 'mgp', 'mgpgenshin5@gmail.com', '$2y$10$zotBoRjqZpfyXp6GNAMAJenFb29uM/pRCsyvdZgvDuDeZifsNV3oK', 'mg', 'p', NULL, 'user', 0, '118145293777307541780', NULL, NULL, NULL, '2026-05-12 07:59:53'),
(16, 'joe', 'joelleannefaytaren@gmail.com', '$2y$10$HAH998j7yI9zv9gs6AIEU.Hqgzjohz53nP2mhaooFaxD4KerqMCHa', 'Faytaren,', 'Joelle Anne', NULL, 'user', 0, '115156549375524341887', NULL, NULL, NULL, '2026-05-12 08:02:04'),
(17, 'chynnagayled@gmail.com', 'chynnagayled@gmail.com', '$2y$10$AEwPXN/3XEmHUO.I3Fcgh.d6QPLB5rmyEVoOXs0QQPEV50yJUDoX2', 'Chynna', 'Dizon', NULL, 'user', 0, '104493133781692177204', NULL, NULL, NULL, '2026-05-12 08:20:32'),
(18, 'heheheyum', 'kenwilburpajanel@gmail.com', '$2y$10$iJHvl3cFxOLAzcySAOxM1es61ZnwizuvTEF6V9y3emlFQu2L7Q2J.', 'Ken', 'Pajanel', '', 'user', 0, '116560319201688412142', NULL, NULL, NULL, '2026-05-12 10:03:24'),
(19, 'JaneGavroche', 'sydhaloot@gmail.com', '$2y$10$UTJkcAB5Dagoti1.tjunm.PHaCDFtkAy6DrSBEcPrxfBbj8UHrNrG', 'syd', 'haloot', NULL, 'user', 0, '116581519996794438994', NULL, NULL, NULL, '2026-05-12 10:17:02'),
(20, 'marcryane29', 'marcampoloquio21@gmail.com', '$2y$10$i.Z8GLdoNRJWiXAqoM1KuusTQrJ/CsTqqEZ8AKCzNomytz.I/lZ2.', 'Marc', 'Ampoloquio', NULL, 'user', 0, '114333988351750743799', NULL, NULL, NULL, '2026-05-12 10:24:30'),
(21, 'cdmonzon', 'cdmonzon@gmail.com', '$2y$10$TLHEPbu.aRd8miRZkuq2Ne/gkzs4K0KzOXGHVT4G41Hj1ekH/8Gzy', 'Conrado', 'Monzon', NULL, 'user', 0, NULL, NULL, NULL, NULL, '2026-05-12 11:39:51'),
(22, 'Sh1ng4', 'gaileyeisenhowernofuente@gmail.com', '$2y$10$0RjQuPjrVi9wVtZb1gpXi.TEWxeeGWWKX.DC4piQbvSLsTD6xLLfW', 'Hower', 'Nofuente', NULL, 'user', 0, NULL, NULL, NULL, NULL, '2026-05-12 14:25:53'),
(23, 'Wolf120405', 'klasupendio@gmail.com', '$2y$10$rA2JGD7yrl9UEmwRhK1iQuXzvht.mebg53ALsR5g0oUButrQmRxDe', 'Kurt', 'Lui Supendio', NULL, 'user', 0, '108553789028756536939', NULL, NULL, NULL, '2026-05-12 14:36:47'),
(24, 'pko0631', 'kobepilarsk8@gmail.com', '$2y$10$OxzfEcf0Bo.xr6/bfdS59O.0VV/ca13Xsef2hK2mow7c7GMWYrDFK', 'Dezero', '10', NULL, 'user', 0, '117286753670200491778', NULL, NULL, NULL, '2026-05-12 17:12:15'),
(25, 'Ronald Bato Dela Rosa', 'rjb2034@dlsud.edu.ph', '$2y$10$iiB1ucG5jmMeidv.moetcu0rzoea.QXZGYPNuGtm9FG/aGeUf7n9q', 'Ronald Bato', 'Dela Rosa', 'Pag di ako pumapasok hinahanap niyo ko, pero nung pumasok ako tinatanong niyo bat pumasok ako - Senator', 'user', 0, NULL, NULL, NULL, NULL, '2026-05-12 17:43:33'),
(26, 'xoxososss', 'darkboomer9@gmail.com', '$2y$10$zdjXM.Cx5amI/PYRpr1o.OTjS7VWEgvmPgeJWF9zpinqc9FyFNAU2', 'Kabesang', 'Tales', NULL, 'user', 0, '101321901526932354824', NULL, NULL, NULL, '2026-05-12 18:35:45'),
(27, 'zhylecheslie', 'zhylebayson@gmail.com', '$2y$10$wbrPryM493AYYOpi/rxmlOwFZCREyX71WjlktAKLdvGN6f6jDYX7S', 'Zhyle', 'Bayson', NULL, 'user', 0, NULL, NULL, NULL, NULL, '2026-05-12 18:59:33'),
(28, 'caralei', 'carangalanlei@gmail.com', '$2y$10$Dym8VqIntHRP2OBkA.h50uk1fOe7TsWenUZBMFDw2cA68JNNvrT2y', 'Lei-Ann Jen', 'Carangalan', NULL, 'user', 0, NULL, NULL, NULL, NULL, '2026-05-12 19:08:00'),
(29, 'niggasyllable', 'rcpantig03@gmail.com', '$2y$10$0P8f2eUi5z5GXUYuEUNfa.9njr0dQv4GAO53wecPuC6r7sNRyulP.', 'Nigga', 'Syllable', NULL, 'user', 0, NULL, NULL, NULL, NULL, '2026-05-13 06:29:41'),
(30, 'GIBA', 'gerinibertarupo@gmail.com', '$2y$10$dtgsjCqinedwNwiMthiGpu0NsXjhosxrMgw4KJif69izpZtfb4GIC', 'Phaccing', 'Xit', 'No I will not tell something about myself.', 'user', 0, NULL, NULL, NULL, NULL, '2026-05-14 04:38:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `google_id` (`google_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
