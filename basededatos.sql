-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 18, 2026 at 09:22 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `basededatos`
--

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` bigint(20) NOT NULL,
  `team1` varchar(255) DEFAULT NULL,
  `team2` varchar(255) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `real_goals_team1` int(11) DEFAULT NULL,
  `real_goals_team2` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `team1`, `team2`, `scheduled_at`, `real_goals_team1`, `real_goals_team2`) VALUES
(1, 'Argentina', 'Francia', '2026-06-10 20:00:00', 1, 1),
(2, 'Brasil', 'España', '2026-06-21 15:30:00', NULL, NULL),
(3, 'Uruguay', 'Portugal', '2026-06-18 11:00:00', 0, 2),
(4, 'México', 'Alemania', '2026-06-18 14:00:00', 10, 0),
(5, 'Argentina', 'Brasil', '2026-06-28 21:00:00', NULL, NULL),
(6, 'España', 'Francia', '2026-06-30 16:00:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `predictions`
--

CREATE TABLE `predictions` (
  `id` bigint(20) NOT NULL,
  `score` int(11) NOT NULL DEFAULT 0,
  `predicted_goals_team1` int(11) NOT NULL DEFAULT 0,
  `predicted_goals_team2` int(11) NOT NULL DEFAULT 0,
  `match_id` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `predictions`
--

INSERT INTO `predictions` (`id`, `score`, `predicted_goals_team1`, `predicted_goals_team2`, `match_id`, `created_at`, `updated_at`, `user_id`) VALUES
(23, 0, 2, 1, 1, '2026-06-18 19:16:51', '2026-06-18 19:21:23', 13),
(24, 0, 1, 1, 2, '2026-06-18 19:16:51', '2026-06-18 19:16:51', 13),
(25, 7, 0, 2, 3, '2026-06-18 19:16:51', '2026-06-18 19:18:39', 13),
(26, 0, 3, 0, 1, '2026-06-18 19:16:51', '2026-06-18 19:21:23', 14),
(27, 0, 1, 2, 2, '2026-06-18 19:16:51', '2026-06-18 19:16:51', 14),
(28, 0, 1, 1, 3, '2026-06-18 19:16:51', '2026-06-18 19:16:51', 14),
(29, 5, 0, 0, 1, '2026-06-18 19:16:51', '2026-06-18 19:21:23', 15),
(30, 0, 2, 2, 2, '2026-06-18 19:16:51', '2026-06-18 19:16:51', 15),
(31, 2, 4, 1, 4, '2026-06-18 19:16:51', '2026-06-18 19:17:20', 15);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `score` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `created_at`, `updated_at`, `role`, `score`) VALUES
(13, 'Sierra', NULL, '2026-06-18 19:16:51', '2026-06-18 19:21:23', 'user', 7),
(14, 'Santiago', NULL, '2026-06-18 19:16:51', '2026-06-18 19:21:23', 'user', 0),
(15, 'Valeria', NULL, '2026-06-18 19:16:51', '2026-06-18 19:21:23', 'Admin', 7);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `predictions`
--
ALTER TABLE `predictions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_predictions_match_user` (`match_id`,`user_id`),
  ADD KEY `idx_predictions_match_id` (`match_id`),
  ADD KEY `fk_predictions_users` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `predictions`
--
ALTER TABLE `predictions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `predictions`
--
ALTER TABLE `predictions`
  ADD CONSTRAINT `fk_predictions_matches` FOREIGN KEY (`match_id`) REFERENCES `matches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_predictions_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
