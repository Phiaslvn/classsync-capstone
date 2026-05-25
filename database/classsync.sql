-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 18, 2026 at 01:09 PM
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
-- Database: `u204830654_final_evsu`
--

-- --------------------------------------------------------

--
-- Table structure for table `account`
--

CREATE TABLE `account` (
  `acc_id` int(11) NOT NULL,
  `fname` varchar(100) NOT NULL,
  `lname` varchar(100) NOT NULL,
  `minitial` varchar(10) DEFAULT NULL,
  `suffix` varchar(10) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `acc_user` varchar(50) NOT NULL,
  `role_id` int(11) DEFAULT NULL,
  `acc_pass` varchar(255) NOT NULL,
  `verification_token` varchar(255) DEFAULT NULL,
  `verification_otp` varchar(6) DEFAULT NULL,
  `verification_otp_expires_at` datetime DEFAULT NULL,
  `acc_email` varchar(100) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `acc_status` enum('Active','Inactive','Deleted','Pending') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account`
--

INSERT INTO `account` (`acc_id`, `fname`, `lname`, `minitial`, `suffix`, `dept_id`, `acc_user`, `role_id`, `acc_pass`, `verification_token`, `verification_otp`, `verification_otp_expires_at`, `acc_email`, `profile_picture`, `acc_status`, `created_by`, `created_at`) VALUES
(1, 'Anne Sophia', 'Silvano', 'L', NULL, NULL, 'admin', 1, '$2y$10$T/tcmdDUsdrGXpGjAe8QuuvjR9APpgU0lNx4zb3H8a35mFQ1/7sWW', NULL, NULL, NULL, 'silvano.annesophia@evsu.edu.ph', NULL, 'Active', NULL, '2025-09-04 03:54:24'),
(108, 'Department', 'Head', 'S', '', 1, 'comdept', 2, '$2y$10$0izZCmdeRSS.IGA2o409ZujXJVd.lZCUse6Z7gJH0paPm5R7pDu9e', NULL, NULL, NULL, 'josephjaymelmorpos@gmail.com', 'assets/uploads/profile_pictures/profile_108_1768557421.jpg', 'Active', NULL, '2025-11-29 12:10:40'),
(109, 'Techer Education', 'Department', '', '', 3, 'educdept', 2, '$2y$10$foXjfWyJUrZErg4HsSEe4eZXFi/Qmz71k63Mmsfg48jtii2qAPpfi', NULL, NULL, NULL, 'educdept@evsu.edu.ph', 'assets/uploads/profile_pictures/profile_109_1768557838.jpg', 'Active', NULL, '2025-11-29 12:16:41'),
(110, 'Department', 'Head', '', '', 2, 'technodept', 2, '$2y$10$26MLaZYd8Weigmrct90b3OqOMFGSM/1KNgSUJSb7NvQQdUbYFgIXW', NULL, NULL, NULL, 'technodept@evsu.edu.ph', 'assets/uploads/profile_pictures/profile_110_1768557886.jpg', 'Active', NULL, '2025-11-29 12:18:11'),
(111, 'Joseph Jaymel', 'Morpos', 'S', NULL, 1, 'jjm', 4, '$2y$10$jHmqnBEnXgXeMTc7G0sweu5iNGsCp9EocaTz0RfXhzyJNNhcyGcCi', NULL, NULL, NULL, 'josephjaymel.morpos@evsu.edu.ph', NULL, 'Active', 108, '2025-11-29 14:02:11'),
(112, 'Phil John', 'Edosma', 'T', NULL, 1, 'pje', 4, '$2y$10$leHsCJUSrTJKerzVOUHwv.rypNX.NEhZN3SiCk3lKGlO64hqdZ2Py', NULL, NULL, NULL, 'philjohn.edosma@evsu.edu.ph', NULL, 'Active', 108, '2025-11-29 14:07:22'),
(121, 'Rose Bell', 'Esolana', 'D', NULL, 1, 'rbe', 3, '$2y$10$.qOglij0eqft0CWl/avyqOzJ9nL1bBbzksk755N/4MvX9oKDyBjTe', NULL, NULL, NULL, 'rosebell.esolana@evsu.edu.ph', NULL, 'Active', 108, '2025-12-01 16:11:36'),
(122, 'Wilferd Jude', 'Perante', 'A', NULL, 1, 'wjp', 4, '$2y$10$kBVZ8Z/rI59.UwCO1AnDyObWOvrb2P6Zdqc0AQNu5lNhx7XokssaS', NULL, NULL, NULL, 'wilferdjude.perante@evsu.edu.ph', NULL, 'Active', 108, '2025-12-01 16:23:55'),
(123, 'Fritz Marc', 'Aseo', 'Y', NULL, 1, 'mfa', 4, '$2y$10$g0uMdcVPk9YXy0LPAA8CrOM2L1xNGOnxxGEgJ/wlZe/im37neZJ7i', NULL, NULL, NULL, 'marcfritz.aseo@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 01:15:33'),
(124, 'Edward', 'Bertulfo', 'B', NULL, 1, 'eb', NULL, '$2y$10$LVw1a668q1Edx2hTvZ12MO6peFty3LD/1YXjT4f8sJWFMAuvAZ7qC', NULL, NULL, NULL, 'edward.bertulfo@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 01:43:08'),
(125, 'Pol', 'Miro', 'A', NULL, 1, 'pm', NULL, '$2y$10$a4lxhUlFjG3VX7NwW4m9OekLpGl2eoG35JtG5qyyVzzv7tqx/cYR.', NULL, NULL, NULL, 'pol.miro@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 12:55:02'),
(126, 'Jude Alexes', 'Ramas', 'M', NULL, 1, 'jar', NULL, '$2y$10$ZsAUXxYk6VQv.GdarHtrR.chO0m4lRdvlV9JpJ/N4ccBjylXJf2ra', NULL, NULL, NULL, 'judealexes_ramas@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 12:57:44'),
(127, 'Wilson', 'Pogosa', 'A', 'Jr.', 1, 'wp', NULL, '$2y$10$7Qqp7c/HONWBQ5xIPTjFMOlJrL5kAe6aDw8I59pd/C3tZXbjbzwOC', NULL, NULL, NULL, 'wilson.pogosa@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 13:31:44'),
(128, 'Romulo Joseph', 'Jereza', 'M', 'IV', 1, 'rjj', NULL, '$2y$10$0qJCamD247dCpaI1dBXXce1mvE8wGApNxJ3aLdJ29gDAIDePEkrc6', NULL, NULL, NULL, 'romulojoseph.jereza@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 14:12:26'),
(129, 'Nena Divina', 'Fevidal', 'D', '', 1, 'ndf', NULL, '$2y$10$jydPq1fBWpLQKfM4YPaC4uYsT0TQc16D.37MZ6BHJJJh7fl8uIZJC', NULL, NULL, NULL, 'nenadivina.fevidal@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 14:15:35'),
(130, 'Jaime', 'Condes', 'S', '', 1, 'jc', NULL, '$2y$10$K1LejDByjSjZXGJIWH1QkOomKdbBXizC8wK46lnjeS0vAH62fbMGO', NULL, NULL, NULL, 'jaime.condes@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 14:33:23'),
(131, 'Jamirah', 'Abdullah', 'L', '', 1, 'ja', NULL, '$2y$10$gnPsuXXC3wEFXRR8g2G8LOH6ISuyts5yr1rDnhjD9B96deVrwsMNC', NULL, NULL, NULL, 'jamirah.abdullah@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 14:36:26'),
(133, 'Jotham', 'Lopez', 'P', '', 1, 'jl', NULL, '$2y$10$XdFDPPyleaL3J5CBOzDR9eNR90v6f7a3WC60zLal4k9A9weUowS..', NULL, NULL, NULL, 'jotham.lopez@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 14:40:45'),
(134, 'Leo Ritchie', 'Tugonon', 'M', '', 1, 'lrt', NULL, '$2y$10$0e2YrOzl2JH3gNTISL23hOLGfBv4cl0fM/v6SVUkR1swsOOE3d4S.', NULL, NULL, NULL, 'leoritchie.tugonon@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 15:07:45'),
(135, 'Daniel', 'Ligutan', 'V', '', 1, 'dl', NULL, '$2y$10$TtA8i.Vh1ai9ZTbMsK5Ub.8RMY2G2Phy9H9FYMYJZfH9M.ORb28uS', NULL, NULL, NULL, 'daniel.ligutan@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 15:09:24'),
(137, 'Ma. Johara', 'Justimbaste', 'V', '', 1, 'mjj', NULL, '$2y$10$fvfaaH/uM4hZ7gCsLWFIMOQje0qRIdynrWoRpOkKC5FCbsWq1U0s2', NULL, NULL, NULL, 'majohara.justimbaste@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 15:17:33'),
(138, 'Cherry', 'Bertulfo', 'C', '', 1, 'cb', NULL, '$2y$10$PCbqE4zHTBLPZSnxuthnOu6ZR1.EiS2DwASdvm2vwG88e57TEr7pe', NULL, NULL, NULL, 'cherry.bertulfo@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 15:33:49'),
(139, 'Sedrick Razeal', 'Arcenal', 'R', '', 1, 'sra', NULL, '$2y$10$HMpejEa21/bdl5Y9hJzApeMh2IpAcuVOwEjyDS/w0h.pcRoPD9DHy', NULL, NULL, NULL, 'sedrickrazeal.arcenal@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 15:43:49'),
(140, 'Chito Antonio', 'Rallos', 'A', '', 1, 'car', NULL, '$2y$10$ZEW6CJVa/W7.kOfKmiVN4ehO4F6mm6lPflC60pxM3km43BysBiaSS', NULL, NULL, NULL, 'chitoantonio.rallos@evsu.edu.ph', NULL, 'Active', 108, '2025-12-02 15:53:59'),
(143, 'Lovely', 'Mabini', 'J', '', 1, 'lovely', NULL, '$2y$10$pjM2gqIAUYaPBvyXxYuIQ.NGHciEQcME9tjPmcVvgktLx4NgfZkAq', NULL, NULL, NULL, 'lovelyjeanmabini@evsu.edu.ph', NULL, 'Active', 108, '2025-12-11 18:16:10'),
(148, 'Allan Reynaldo', 'Mabitad', 'E', '', 2, 'allan', NULL, '$2y$10$Apnvf3B82O.CMM9fVimQT.D9ZVArMKeCN3561Vvw9l0HmF0IhSpfu', NULL, NULL, NULL, 'allanreynaldo.mabitad@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 14:03:37'),
(149, 'Mary Joy', 'Baltonado', 'B', '', 2, 'mjb', NULL, '$2y$10$WonQTZDm0OezZSV6BD98JO3YNCXyYitgEmOXiOUl0cNRXLqrOKY/W', NULL, NULL, NULL, 'maryjoy.baltonado@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 14:16:54'),
(150, 'Rosita', 'Lariosa', 'D', '', 2, 'rl', NULL, '$2y$10$Ab0PYE6vN6mDjGi1UIHPMOR5EO9Y2ysLU3/RNr4yzhis.S5bbZUza', NULL, NULL, NULL, 'rosita.lariosa@evsu.edu.ph', NULL, 'Active', 1, '2026-01-06 16:48:46'),
(151, 'Jesiel', 'Arcillas', 't', '', 2, 'jta', NULL, '$2y$10$RsgbZAEpP6eyfFTubQR.u.ldytRXN7nbkiz4rYa1gcRBmkfBr8dKy', NULL, NULL, NULL, 'jesiel.arcillas@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 16:54:56'),
(153, 'Dionisio', 'Cecilio', 's', 'Jr.', 2, 'dsc', NULL, '$2y$10$ROCcouFVfDfPHkyGkVWqdeawd3pgWnWgdEweKYZ2wWwfoznx0W7Ya', NULL, NULL, NULL, 'dionisio.cecilio@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:03:32'),
(154, 'Arnel', 'Pepito', 'G', '', 2, 'agp', NULL, '$2y$10$TEkpkxM/pIa8JeV1uXxsKe8lac01zWvwzqqqCyqPZO.Yl6Ajih342', NULL, NULL, NULL, 'arnel.pepito@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:06:49'),
(155, 'Marvin', 'Rosario', 'M', '', 2, 'mmr', NULL, '$2y$10$MnOvFBPlRKkGGeUQuQ5TYOl9ip1Fho6S6FHGzkN9zx4j9oIEcD/EK', NULL, NULL, NULL, 'marvin.rosario@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:11:10'),
(156, 'Jasper Jim', 'Tajos', 'P', '', 2, 'jjt', NULL, '$2y$10$PIjWB.8CWxj8W/84SxfOYurhleSprOuT8Xnj9S/NP/EMxcCylQb2C', NULL, NULL, NULL, 'jasperjim.tajos@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:21:00'),
(157, 'Generoso', 'Banagbanag', 'P', 'Jr', 2, 'gpb', NULL, '$2y$10$uZbMNrkSbDIBnFE4LPwtJethLJ4DcDFwXfZILTa.VLNF1Eno.IsRW', NULL, NULL, NULL, 'generoso.banagbanag@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:24:07'),
(158, 'Marimel', 'Caagay', 'P', '', 2, 'mpc', NULL, '$2y$10$WuLd3yQqfgQssK9GhVwS6.2tGWYZ5/mxQo/ljsRIUA2AHyQnPjt1S', NULL, NULL, NULL, 'marimel.caagay@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:26:43'),
(159, 'Lorraine', 'Taypa', 'D', '', 2, 'ldt', NULL, '$2y$10$DZl5.Upq4I6fhuRGpGMoq.lbbIYFBoUzAaq7O.uQOai3SkuhOcwim', NULL, NULL, NULL, 'lorraine.taypa@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:30:11'),
(160, 'Shaina Mae', 'Ompad', 'c', '', 2, 'smo', NULL, '$2y$10$DJjPoZGXIVtu47R/XT2QVO/ZTPOWrpGEwP3e9waGx4Ed9rQ3eENTe', NULL, NULL, NULL, 'shainamae.ompad@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:33:05'),
(161, 'Romelyn', 'Sasing', 'B', '', 2, 'rbs', NULL, '$2y$10$18Wtsg.lwnSDLAsKl5nFi..ZXk8PHMqacBd1DLCkcBWryLj8xMi1G', NULL, NULL, NULL, 'romelyn.sasing@evsu.edu.ph', NULL, 'Active', 110, '2026-01-06 17:39:44'),
(162, 'Engineering', 'Department', '', '', 4, 'engdept', NULL, '$2y$10$eLimlJz2kKWyFD82RM3KEuQ4v7VLnKArsitf.MofaIiS2WFSMvXsC', NULL, NULL, NULL, 'engrdept.samson@evsu.edu.ph', 'assets/uploads/profile_pictures/profile_162_1768558266.jpg', 'Active', NULL, '2026-01-16 10:09:36'),
(163, 'Georgina', 'Orbeta', 'M', '', 3, 'gmo', NULL, '$2y$10$fc5BOff7k4dWs4fcZ7AnhOOodJQCG6wtgbAxRCvx97MBxoEhHtYc.', NULL, NULL, NULL, 'georgina.orbeta@evsu.edu.ph', NULL, 'Active', 109, '2026-01-25 05:48:22'),
(164, 'Julito', 'Acebron', 'f', '', 3, 'jfa', NULL, '$2y$10$M91Ws.Dk2ylN9uXhd/BdAuCZtMhij9Qp86cfTfEXU5utEvzuOWsR6', NULL, NULL, NULL, 'julito.acebron@evsu.edu.ph', NULL, 'Active', 109, '2026-01-25 05:53:11'),
(165, 'Linda', 'Walker', 'A', NULL, 5, 'lw', NULL, '$2y$10$DANUjDfxXyIJ/faboWvjde1nbrTjRjUX2l5RJUrmIEiZdDZ6A17MG', NULL, NULL, NULL, 'c36634130@gmail.com', NULL, 'Deleted', NULL, '2026-03-01 12:47:57'),
(166, 'Jurybels', 'Catingub', '', '', 3, 'jury', NULL, '$2y$10$GKDgJgxy3kFjtD9SOzOivOdY9Rs5SCYtcJch.0ygHCmm3oKpzKqPe', NULL, NULL, NULL, 'jurymae.catingub@evsu.edu.ph', 'assets/uploads/profile_pictures/profile_166_1776515211.jpg', 'Active', 109, '2026-04-18 12:25:08'),
(167, 'Hospitality', 'Management', '', NULL, 7, 'hmdept', NULL, '$2y$10$8e6ItdoeCi.GhBjzTgs6yeyEyfVX4c8yMufDbUiKWocAae5JUjsNK', NULL, NULL, NULL, 'hmdept@evsu.edu.ph', 'assets/uploads/profile_pictures/profile_167_1778494476.jpg', 'Active', NULL, '2026-05-01 14:27:22');

-- --------------------------------------------------------

--
-- Table structure for table `account_departments`
--

CREATE TABLE `account_departments` (
  `id` int(11) NOT NULL,
  `acc_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_departments`
--

INSERT INTO `account_departments` (`id`, `acc_id`, `dept_id`, `created_at`, `updated_at`) VALUES
(1, 108, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(2, 111, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(3, 112, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(4, 121, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(5, 122, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(6, 123, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(7, 124, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(8, 125, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(9, 126, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(10, 127, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(11, 128, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(12, 129, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(13, 130, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(14, 131, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(15, 133, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(16, 134, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(17, 135, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(18, 137, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(19, 138, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(20, 139, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(21, 140, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(22, 143, 1, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(23, 110, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(24, 148, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(25, 149, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(26, 150, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(27, 151, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(28, 153, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(29, 154, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(30, 155, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(31, 156, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(32, 157, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(33, 158, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(34, 159, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(35, 160, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(36, 161, 2, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(37, 109, 3, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(38, 163, 3, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(39, 164, 3, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(40, 162, 4, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(41, 165, 5, '2026-04-04 14:49:06', '2026-04-04 14:49:06'),
(64, 127, 3, '2026-04-04 14:49:32', '2026-04-04 14:49:32'),
(65, 166, 3, '2026-04-18 12:25:08', '2026-04-18 12:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `active_school_year_semester`
--

CREATE TABLE `active_school_year_semester` (
  `id` int(11) NOT NULL,
  `sy_id` int(11) NOT NULL,
  `semester` enum('1st','2nd','Summer') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `active_school_year_semester`
--

INSERT INTO `active_school_year_semester` (`id`, `sy_id`, `semester`, `is_active`, `created_at`) VALUES
(2, 10, '1st', 0, '2026-05-11 10:20:03'),
(3, 8, '2nd', 1, '2026-05-11 15:20:40'),
(4, 9, '1st', 0, '2026-05-11 15:20:21'),
(5, 11, '2nd', 0, '2026-01-10 18:31:30');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `acc_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `log_date` datetime NOT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`log_id`, `acc_id`, `action`, `log_date`, `details`) VALUES
(109, 108, 'Email verified via OTP: Joseph Jaymel Morpos', '2025-11-29 20:13:53', '{\"account_id\":108,\"email\":\"annesophiasilvano407@gmail.com\",\"verification_method\":\"OTP\"}'),
(110, 109, 'Email verified via OTP: Beatrice Mabitad', '2025-11-29 20:19:12', '{\"account_id\":109,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(111, 110, 'Email verified via OTP: Allan Reynaldo Mabitad', '2025-11-29 20:19:48', '{\"account_id\":110,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(112, 108, 'Added new program: BSIT (Bachelor of Science in Information Technology) in Computer Studies Departme', '2025-11-29 20:30:18', '{\"program_id\":30,\"program_code\":\"BSIT\",\"program_name\":\"Bachelor of Science in Information Technology\",\"effective_academic_year\":\"2025-2026\",\"program_type\":\"BS\",\"total_units_required\":120,\"major_track\":\"Information Technology\",\"program_years\":4,\"department_id\":1,\"department_name\":\"Computer Studies Department\",\"added_by\":108}'),
(113, 108, 'Added new instructor account: jjm (Joseph Jaymel Morpos)', '2025-11-29 22:02:18', '{\"new_account_id\":111,\"new_instructor_id\":24,\"username\":\"jjm\",\"email\":\"annesophiasilvano407@gmail.com\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Assistant Professor III\",\"designation\":\"Head\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(114, 108, 'Added new instructor account: pje (Phil John Edosma)', '2025-11-29 22:07:28', '{\"new_account_id\":112,\"new_instructor_id\":25,\"username\":\"pje\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(115, 111, 'Email verified via OTP: Joseph Jaymel Morpos', '2025-11-29 22:08:05', '{\"account_id\":111,\"email\":\"annesophiasilvano407@gmail.com\",\"verification_method\":\"OTP\"}'),
(116, 112, 'Email verified via OTP: Phil John Edosma', '2025-11-29 22:08:41', '{\"account_id\":112,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(117, 108, 'Added new curriculum: P2018C - Prior 2018 Curriculum', '2025-11-29 22:20:33', '{\"curriculum_id\":38,\"curriculum_code\":\"P2018C\",\"curriculum_name\":\"Prior 2018 Curriculum\",\"curriculum_level\":4,\"curriculum_year\":\"2025-2026\",\"department_id\":1,\"total_units\":120,\"status\":\"active\",\"added_by\":108}'),
(118, 108, 'Added new program: BSCS (Bachelor of Science in Computer Science) in Computer Studies Department', '2025-11-30 12:15:57', '{\"program_id\":31,\"program_code\":\"BSCS\",\"program_name\":\"Bachelor of Science in Computer Science\",\"effective_academic_year\":\"2025-2026\",\"program_type\":\"BS\",\"total_units_required\":176,\"major_track\":\"Computer Science\",\"program_years\":4,\"department_id\":1,\"department_name\":\"Computer Studies Department\",\"added_by\":108}'),
(119, 108, 'Granted room access: Room ID 18 to Department ID 2', '2025-12-01 19:27:27', '{\"room_id\":18,\"room_name\":\"ITRM1NB\",\"granted_to_dept_id\":2,\"granted_to_dept_name\":\"Industrial Technology Department\",\"granted_by_dept_id\":1,\"granted_by_dept_name\":\"Computer Studies Department\",\"granted_by\":108}'),
(120, 110, 'Room request submitted: Room ID 18 for 2025-12-01 09:00', '2025-12-01 20:16:50', '{\"room_id\":18,\"room_name\":\"ITRM1NB\",\"request_date\":\"2025-12-01 09:00:00\",\"day\":\"Mon\",\"duration\":3,\"comment\":\"Can we use this hehehe\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"requester_dept_name\":\"Industrial Technology Department\"}'),
(121, 108, 'Approved room request: Request ID 4', '2025-12-01 20:28:35', '{\"req_id\":4,\"room_name\":\"ITRM1NB\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"status\":\"Accepted\",\"updated_by\":108}'),
(122, 110, 'Added new curriculum: P2018C - Prior 2018 Curriculum', '2025-12-01 20:35:14', '{\"curriculum_id\":39,\"curriculum_code\":\"P2018C\",\"curriculum_name\":\"Prior 2018 Curriculum\",\"curriculum_level\":4,\"curriculum_year\":\"2025-2026\",\"department_id\":2,\"total_units\":120,\"status\":\"active\",\"added_by\":110}'),
(123, 110, 'Added new program: BindTech (Bachelor of Industrial Technology Major in Culinary Technology) in Indu', '2025-12-01 20:41:38', '{\"program_id\":32,\"program_code\":\"BindTech\",\"program_name\":\"Bachelor of Industrial Technology Major in Culinary Technology\",\"effective_academic_year\":\"2025-2026\",\"program_type\":\"BS\",\"total_units_required\":120,\"major_track\":\"Culinary Technology\",\"program_years\":4,\"department_id\":2,\"department_name\":\"Industrial Technology Department\",\"added_by\":110}'),
(124, 108, 'Granted room access: Room ID 18 to Department ID 3', '2025-12-01 21:41:34', '{\"room_id\":18,\"room_name\":\"ITRM1NB\",\"granted_to_dept_id\":3,\"granted_to_dept_name\":\"Teacher Education Department\",\"granted_by_dept_id\":1,\"granted_by_dept_name\":\"Computer Studies Department\",\"granted_by\":108}'),
(125, 108, 'Added new instructor account: rbe (Rose Bell Esolana)', '2025-12-02 00:11:42', '{\"new_account_id\":121,\"new_instructor_id\":27,\"username\":\"rbe\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":3,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(126, 121, 'Email verified via OTP: Rose Bell Esolana', '2025-12-02 00:18:11', '{\"account_id\":121,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(127, 108, 'Added new instructor account: wjp (Wilferd Jude Perante)', '2025-12-02 00:24:00', '{\"new_account_id\":122,\"new_instructor_id\":28,\"username\":\"wjp\",\"email\":\"phiaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Assistant Professor III\",\"designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(128, 122, 'Email verified via OTP: Wilferd Jude Perante', '2025-12-02 00:50:35', '{\"account_id\":122,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(129, 108, 'Added new instructor account: mfa (Fritz Marc Aseo)', '2025-12-02 09:15:38', '{\"new_account_id\":123,\"new_instructor_id\":29,\"username\":\"mfa\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(130, 123, 'Email verified via OTP: Fritz Marc Aseo', '2025-12-02 09:17:49', '{\"account_id\":123,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(131, 108, 'Added new instructor account: eb (Edward Bertulfo)', '2025-12-02 09:43:13', '{\"new_account_id\":124,\"new_instructor_id\":30,\"username\":\"eb\",\"email\":\"phiaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor III\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(132, 124, 'Email verified via OTP: Edward Bertulfo', '2025-12-02 09:44:02', '{\"account_id\":124,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(133, 108, 'Archived room request: Request ID 4', '2025-12-02 16:53:27', '{\"req_id\":4,\"room_name\":\"ITRM1NB\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"status\":\"Archived\",\"updated_by\":108}'),
(134, 108, 'Added new instructor account: pm (Pol Miro)', '2025-12-02 20:55:07', '{\"new_account_id\":125,\"new_instructor_id\":31,\"username\":\"pm\",\"email\":\"phiaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(135, 108, 'Added new instructor account: jar (Jude Alexes Ramas)', '2025-12-02 20:57:51', '{\"new_account_id\":126,\"new_instructor_id\":32,\"username\":\"jar\",\"email\":\"annesophiasilvano407@gmail.com\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Associate Professor III\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(136, 126, 'Email verified via OTP: Jude Alexes Ramas', '2025-12-02 21:00:08', '{\"account_id\":126,\"email\":\"annesophiasilvano407@gmail.com\",\"verification_method\":\"OTP\"}'),
(137, 125, 'Email verified via OTP: Pol Miro', '2025-12-02 21:00:56', '{\"account_id\":125,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(138, 108, 'Added new instructor account: wp (Wilson Pogosa Jr.)', '2025-12-02 21:31:49', '{\"new_account_id\":127,\"new_instructor_id\":33,\"username\":\"wp\",\"email\":\"phiaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(139, 127, 'Email verified via OTP: Wilson Pogosa Jr.', '2025-12-02 22:01:13', '{\"account_id\":127,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(140, 108, 'Added new instructor account: rjj (Romulo Joseph Jereza)', '2025-12-02 22:12:31', '{\"new_account_id\":128,\"new_instructor_id\":34,\"username\":\"rjj\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(141, 108, 'Added new instructor account: ndf (Nena Divina Fevidal)', '2025-12-02 22:15:40', '{\"new_account_id\":129,\"new_instructor_id\":35,\"username\":\"ndf\",\"email\":\"annesophiasilvano407@gmail.com\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(142, 108, 'Added new instructor account: jc (Jaime Condes)', '2025-12-02 22:33:32', '{\"new_account_id\":130,\"new_instructor_id\":36,\"username\":\"jc\",\"email\":\"annesophiasilvano20@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(143, 108, 'Added new instructor account: ja (Jamirah Abdullah)', '2025-12-02 22:36:30', '{\"new_account_id\":131,\"new_instructor_id\":37,\"username\":\"ja\",\"email\":\"jhasminelicong@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(144, 108, 'Added new instructor account: car (Chito Antonio Rallos)', '2025-12-02 22:38:34', '{\"new_account_id\":132,\"new_instructor_id\":38,\"username\":\"car\",\"email\":\"venusaltheaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(145, 108, 'Added new instructor account: jl (Jotham Lopez)', '2025-12-02 22:40:51', '{\"new_account_id\":133,\"new_instructor_id\":39,\"username\":\"jl\",\"email\":\"venusalthea.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(146, 133, 'Email verified via OTP: Jotham Lopez', '2025-12-02 22:42:07', '{\"account_id\":133,\"email\":\"venusalthea.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(147, 108, 'Deleted account: car (Chito Antonio Rallos)', '2025-12-02 22:46:55', '{\"deleted_account_id\":132,\"deleted_username\":\"car\",\"deleted_email\":\"altheaslvn@gmail.com\",\"deleted_by\":108}'),
(148, 131, 'Email verified via OTP: Jamirah Abdullah', '2025-12-02 22:47:42', '{\"account_id\":131,\"email\":\"jhasminelicong@gmail.com\",\"verification_method\":\"OTP\"}'),
(149, 130, 'Email verified via OTP: Jaime Condes', '2025-12-02 22:54:57', '{\"account_id\":130,\"email\":\"annesophiasilvano20@gmail.com\",\"verification_method\":\"OTP\"}'),
(150, 129, 'Email verified via OTP: Nena Divina Fevidal', '2025-12-02 22:56:27', '{\"account_id\":129,\"email\":\"annesophiasilvano407@gmail.com\",\"verification_method\":\"OTP\"}'),
(151, 128, 'Email verified via OTP: Romulo Joseph Jereza', '2025-12-02 22:59:18', '{\"account_id\":128,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(152, 108, 'Added new instructor account: lrt (Leo Ritchie Tugonon)', '2025-12-02 23:07:52', '{\"new_account_id\":134,\"new_instructor_id\":40,\"username\":\"lrt\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(153, 108, 'Added new instructor account: dl (Daniel Ligutan)', '2025-12-02 23:09:44', '{\"new_account_id\":135,\"new_instructor_id\":41,\"username\":\"dl\",\"email\":\"phiaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(154, 108, 'Added new instructor account: cb (Cherry Bertulfo)', '2025-12-02 23:15:36', '{\"new_account_id\":136,\"new_instructor_id\":42,\"username\":\"cb\",\"email\":\"venusaltheaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(155, 108, 'Added new instructor account: mjj (Ma. Johara Justimbaste)', '2025-12-02 23:17:54', '{\"new_account_id\":137,\"new_instructor_id\":43,\"username\":\"mjj\",\"email\":\"annesophiasilvano20@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(156, 137, 'Email verified via OTP: Ma. Johara Justimbaste', '2025-12-02 23:20:44', '{\"account_id\":137,\"email\":\"annesophiasilvano20@gmail.com\",\"verification_method\":\"OTP\"}'),
(157, 135, 'Email verified via OTP: Daniel Ligutan', '2025-12-02 23:22:19', '{\"account_id\":135,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(158, 134, 'Email verified via OTP: Leo Ritchie Tugonon', '2025-12-02 23:24:25', '{\"account_id\":134,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(159, 108, 'Deleted account: cb (Cherry Bertulfo)', '2025-12-02 23:25:39', '{\"deleted_account_id\":136,\"deleted_username\":\"cb\",\"deleted_email\":\"venusaltheaslvn@gmail.com\",\"deleted_by\":108}'),
(160, 108, 'Added new instructor account: cb (Cherry Bertulfo)', '2025-12-02 23:34:02', '{\"new_account_id\":138,\"new_instructor_id\":44,\"username\":\"cb\",\"email\":\"phiaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(161, 138, 'Email verified via OTP: Cherry Bertulfo', '2025-12-02 23:35:50', '{\"account_id\":138,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(162, 108, 'Added new instructor account: sra (Sedrick Razeal Arcenal)', '2025-12-02 23:43:54', '{\"new_account_id\":139,\"new_instructor_id\":45,\"username\":\"sra\",\"email\":\"phiaslvn@gmail.com\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(163, 139, 'Email verified via OTP: Sedrick Razeal Arcenal', '2025-12-02 23:45:47', '{\"account_id\":139,\"email\":\"phiaslvn@gmail.com\",\"verification_method\":\"OTP\"}'),
(164, 108, 'Added new instructor account: car (Chito Antonio Rallos)', '2025-12-02 23:54:03', '{\"new_account_id\":140,\"new_instructor_id\":46,\"username\":\"car\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(165, 140, 'Email verified via OTP: Chito Antonio Rallos', '2025-12-02 23:56:10', '{\"account_id\":140,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(166, 108, 'Granted room access: Room ID 19 to Department ID 2', '2025-12-03 12:05:05', '{\"room_id\":19,\"room_name\":\"ITRM2NB\",\"granted_to_dept_id\":2,\"granted_to_dept_name\":\"Industrial Technology Department\",\"granted_by_dept_id\":1,\"granted_by_dept_name\":\"Computer Studies Department\",\"granted_by\":108}'),
(167, 110, 'Room request submitted: Room ID 19 for 2025-12-03 07:00', '2025-12-03 12:11:48', '{\"room_id\":19,\"room_name\":\"ITRM2NB\",\"request_date\":\"2025-12-03 07:00:00\",\"day\":\"Mon\",\"duration\":3,\"comment\":\"can we use the room?\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"requester_dept_name\":\"Industrial Technology Department\"}'),
(168, 108, 'Approved room request: Request ID 5', '2025-12-03 12:16:33', '{\"req_id\":5,\"room_name\":\"ITRM2NB\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"status\":\"Accepted\",\"updated_by\":108}'),
(169, 108, 'Promoted rank from  to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"\",\"new_rank\":\"Instructor II\",\"action\":\"promote\"}'),
(170, 108, 'Demoted rank from Instructor II to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor I\",\"action\":\"demote\"}'),
(171, 108, 'Promoted rank from Instructor I to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor I\",\"new_rank\":\"Instructor II\",\"action\":\"promote\"}'),
(172, 108, 'Demoted rank from Instructor II to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor I\",\"action\":\"demote\"}'),
(173, 108, 'Promoted rank from  to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"\",\"new_rank\":\"Instructor II\",\"action\":\"promote\"}'),
(174, 108, 'Promoted rank from Instructor II to Instructor III', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor III\",\"action\":\"promote\"}'),
(175, 108, 'Demoted rank from Instructor III to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"Instructor III\",\"new_rank\":\"Instructor II\",\"action\":\"demote\"}'),
(176, 108, 'Demoted rank from Instructor II to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor I\",\"action\":\"demote\"}'),
(177, 1, 'Promoted role from Instructor to Moderator', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_role_id\":4,\"old_role_name\":\"Instructor\",\"new_role_id\":3,\"new_role_name\":\"Moderator\",\"action\":\"promote\"}'),
(178, 1, 'Demoted role from Moderator to Instructor', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_role_id\":3,\"old_role_name\":\"Moderator\",\"new_role_id\":4,\"new_role_name\":\"Instructor\",\"action\":\"demote\"}'),
(179, 1, 'Promoted role from Instructor to Moderator', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_role_id\":4,\"old_role_name\":\"Instructor\",\"new_role_id\":3,\"new_role_name\":\"Moderator\",\"action\":\"promote\"}'),
(180, 1, 'Demoted role from Moderator to Instructor', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_role_id\":3,\"old_role_name\":\"Moderator\",\"new_role_id\":4,\"new_role_name\":\"Instructor\",\"action\":\"demote\"}'),
(181, 108, 'Promoted rank from  to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":131,\"username\":\"ja\",\"old_rank\":\"\",\"new_rank\":\"Instructor II\",\"action\":\"promote\"}'),
(182, 108, 'Demoted rank from Instructor II to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":131,\"username\":\"ja\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor I\",\"action\":\"demote\"}'),
(183, 108, 'Promoted rank from Instructor I to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":131,\"username\":\"ja\",\"old_rank\":\"Instructor I\",\"new_rank\":\"Instructor II\",\"action\":\"promote\"}'),
(184, 108, 'Demoted rank from Instructor II to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":131,\"username\":\"ja\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor I\",\"action\":\"demote\"}'),
(185, 108, 'Promoted rank from  to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"\",\"new_rank\":\"Instructor II\",\"action\":\"promote\"}'),
(186, 108, 'Promoted rank from None to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"None\",\"new_rank\":\"Instructor I\",\"action\":\"promote\"}'),
(187, 108, 'Demoted rank from Instructor I to None', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"Instructor I\",\"new_rank\":\"None\",\"action\":\"demote\"}'),
(188, 108, 'Demoted rank from Instructor II to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor I\",\"action\":\"demote\"}'),
(189, 108, 'Demoted rank from Instructor I to None', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor I\",\"new_rank\":\"None\",\"action\":\"demote\"}'),
(190, 108, 'Promoted rank from None to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"None\",\"new_rank\":\"Instructor I\",\"action\":\"promote\"}'),
(191, 108, 'Demoted rank from Instructor I to None', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor I\",\"new_rank\":\"None\",\"action\":\"demote\"}'),
(192, 108, 'Promoted designation from None to Chairperson/Coordinator/As Officer in Faculty Association', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"None\",\"new_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"action\":\"promote\"}'),
(193, 108, 'Promoted designation from Chairperson/Coordinator/As Officer in Faculty Association to Head', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"new_designation\":\"Head\",\"action\":\"promote\"}'),
(194, 108, 'Demoted designation from Head to Chairperson/Coordinator/As Officer in Faculty Association', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"Head\",\"new_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"action\":\"demote\"}'),
(195, 108, 'Demoted designation from Chairperson/Coordinator/As Officer in Faculty Association to None', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"new_designation\":\"None\",\"action\":\"demote\"}'),
(196, 108, 'Archived room request: Request ID 4', '2025-12-09 20:33:19', '{\"req_id\":4,\"room_name\":\"ITRM1NB\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"status\":\"Archived\",\"updated_by\":108}'),
(197, 108, 'Promoted rank from None to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"None\",\"new_rank\":\"Instructor I\",\"action\":\"promote\"}'),
(198, 1, 'Promoted role from Instructor to Moderator', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_role_id\":4,\"old_role_name\":\"Instructor\",\"new_role_id\":3,\"new_role_name\":\"Moderator\",\"action\":\"promote\"}'),
(199, 108, 'Promoted rank from Instructor I to Instructor II', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor I\",\"new_rank\":\"Instructor II\",\"action\":\"promote\"}'),
(200, 108, 'Promoted designation from None to Chairperson/Coordinator/As Officer in Faculty Association', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"None\",\"new_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"action\":\"promote\"}'),
(201, 108, 'Promoted designation from Chairperson/Coordinator/As Officer in Faculty Association to Head', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"new_designation\":\"Head\",\"action\":\"promote\"}'),
(202, 108, 'Demoted designation from Head to Chairperson/Coordinator/As Officer in Faculty Association', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"Head\",\"new_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"action\":\"demote\"}'),
(203, 108, 'Demoted designation from Chairperson/Coordinator/As Officer in Faculty Association to None', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"new_designation\":\"None\",\"action\":\"demote\"}'),
(205, 1, 'Demoted role from Moderator to Instructor', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_role_id\":3,\"old_role_name\":\"Moderator\",\"new_role_id\":4,\"new_role_name\":\"Instructor\",\"action\":\"demote\"}'),
(206, 108, 'Promoted rank from None to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"None\",\"new_rank\":\"Instructor I\",\"action\":\"promote\"}'),
(207, 108, 'Demoted rank from Instructor II to Instructor I', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor II\",\"new_rank\":\"Instructor I\",\"action\":\"demote\"}'),
(208, 108, 'Demoted rank from Instructor I to None', '0000-00-00 00:00:00', '{\"acc_id\":140,\"username\":\"car\",\"old_rank\":\"Instructor I\",\"new_rank\":\"None\",\"action\":\"demote\"}'),
(209, 108, 'Demoted rank from Instructor I to None', '0000-00-00 00:00:00', '{\"acc_id\":139,\"username\":\"sra\",\"old_rank\":\"Instructor I\",\"new_rank\":\"None\",\"action\":\"demote\"}'),
(210, 108, 'Added new instructor account: lovely (Lovely Mabini)', '2025-12-12 02:16:16', '{\"new_account_id\":143,\"new_instructor_id\":49,\"username\":\"lovely\",\"email\":\"lovelyjean.mabini@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(211, 143, 'Email verified via OTP: Lovely Mabini', '2025-12-12 02:17:29', '{\"account_id\":143,\"email\":\"lovelyjean.mabini@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(212, 108, 'Promoted designation from None to Chairperson/Coordinator/As Officer in Faculty Association', '0000-00-00 00:00:00', '{\"acc_id\":143,\"username\":\"lovely\",\"old_designation\":\"None\",\"new_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"action\":\"promote\"}'),
(213, 108, 'Demoted designation from Chairperson/Coordinator/As Officer in Faculty Association to None', '0000-00-00 00:00:00', '{\"acc_id\":143,\"username\":\"lovely\",\"old_designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"new_designation\":\"None\",\"action\":\"demote\"}'),
(214, 109, 'Added new program: BEEd (Bachelor of Elementary Education) in Teacher Education Department', '2025-12-12 21:25:41', '{\"program_id\":33,\"program_code\":\"BEEd\",\"program_name\":\"Bachelor of Elementary Education\",\"effective_academic_year\":\"2025-2026\",\"program_type\":\"BEED\",\"total_units_required\":185,\"major_track\":\"\",\"program_years\":4,\"department_id\":3,\"department_name\":\"Teacher Education Department\",\"added_by\":109}'),
(215, 109, 'Added new program: BPEd (Bachelor of Physical EDucation) in Teacher Education Department', '2025-12-12 21:53:11', '{\"program_id\":34,\"program_code\":\"BPEd\",\"program_name\":\"Bachelor of Physical EDucation\",\"effective_academic_year\":\"2025-2026\",\"program_type\":\"BPE\",\"total_units_required\":194,\"major_track\":\"\",\"program_years\":4,\"department_id\":3,\"department_name\":\"Teacher Education Department\",\"added_by\":109}'),
(216, 109, 'Added new curriculum: Bachelor of Physical Education (Level 4) in Teacher Education Department', '2025-12-12 22:01:40', '{\"curriculum_id\":40,\"curriculum_code\":\"BPEd\",\"curriculum_name\":\"Bachelor of Physical Education\",\"curriculum_type\":\"BPE\",\"curriculum_version\":\"2019 Edition\",\"curriculum_level\":4,\"curriculum_year\":\"2025-2026\",\"effective_start_year\":2021,\"effective_end_year\":null,\"department_id\":3,\"total_units\":185,\"status\":\"active\",\"added_by\":109}'),
(217, 109, 'Added new curriculum: Bachelor of Elementary Education (Level 4) in Teacher Education Department', '2025-12-12 22:06:18', '{\"curriculum_id\":41,\"curriculum_code\":\"BEEd\",\"curriculum_name\":\"Bachelor of Elementary Education\",\"curriculum_type\":\"BEED\",\"curriculum_version\":\"2019 Edition\",\"curriculum_level\":4,\"curriculum_year\":\"2025-2026\",\"effective_start_year\":2025,\"effective_end_year\":null,\"department_id\":3,\"total_units\":185,\"status\":\"active\",\"added_by\":109}'),
(218, 109, 'Added new instructor account: eder (Eder Duallo)', '2025-12-12 22:37:25', '{\"new_account_id\":144,\"new_instructor_id\":50,\"username\":\"eder\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":3,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_id\":4,\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(220, 108, 'Added new curriculum: Bachelor of Science Computer Science (Level 4) in Computer Studies Department', '2025-12-13 03:45:09', '{\"curriculum_id\":42,\"curriculum_code\":\"BSCS\",\"curriculum_name\":\"Bachelor of Science Computer Science\",\"curriculum_type\":\"BS\",\"curriculum_version\":\"Prior to 2022\",\"curriculum_level\":4,\"curriculum_year\":\"2022-2023\",\"effective_start_year\":2022,\"effective_end_year\":null,\"department_id\":1,\"total_units\":189,\"status\":\"active\",\"added_by\":108}'),
(221, 108, 'Added new program: BSCE (Bachelor of Science Computer Engineering) in Computer Studies Department', '2025-12-13 03:47:16', '{\"program_id\":35,\"program_code\":\"BSCE\",\"program_name\":\"Bachelor of Science Computer Engineering\",\"effective_academic_year\":\"2022-2023\",\"program_type\":\"BS\",\"total_units_required\":189,\"major_track\":\"\",\"program_years\":4,\"department_id\":1,\"department_name\":\"Computer Studies Department\",\"added_by\":108}'),
(222, 108, 'Granted room access: Room ID 30 to Department ID 3', '2025-12-13 03:49:22', '{\"room_id\":30,\"room_name\":\"testroom1\",\"granted_to_dept_id\":3,\"granted_to_dept_name\":\"Teacher Education Department\",\"granted_by_dept_id\":1,\"granted_by_dept_name\":\"Computer Studies Department\",\"granted_by\":108}'),
(223, 109, 'Room request submitted: Room ID 30 for 2025-12-13 07:00', '2025-12-13 03:56:10', '{\"room_id\":30,\"room_name\":\"testroom1\",\"request_date\":\"2025-12-13 07:00:00\",\"day\":\"Mon\",\"duration\":3,\"comment\":\"\",\"requester\":\"Beatrice Mabitad\",\"requester_dept_id\":3,\"requester_dept_name\":\"Teacher Education Department\"}'),
(224, 108, 'Approved room request: Request ID 6', '2025-12-13 04:00:44', '{\"req_id\":6,\"room_name\":\"testroom1\",\"requester\":\"Beatrice Mabitad\",\"requester_dept_id\":3,\"status\":\"Accepted\",\"updated_by\":108}'),
(225, 110, 'Added new program: BIT (Bachelor of Industrial Technology Major in Electronics Technology) in Indust', '2025-12-14 18:38:55', '{\"program_id\":36,\"program_code\":\"BIT\",\"program_name\":\"Bachelor of Industrial Technology Major in Electronics Technology\",\"effective_academic_year\":\"2025-2026\",\"program_type\":\"Other\",\"total_units_required\":162,\"major_track\":\"Electronics Technology\",\"program_years\":4,\"department_id\":2,\"department_name\":\"Industrial Technology Department\",\"added_by\":110}'),
(226, 110, 'Room request automatically approved: Room ID 18 for 2026-01-02 16:00', '2026-01-02 13:10:59', '{\"room_id\":18,\"room_name\":\"ITRM1NB\",\"request_date\":\"2026-01-02 16:00:00\",\"day\":\"Thu\",\"duration\":3,\"comment\":\"\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"requester_dept_name\":\"Industrial Technology Department\",\"status\":\"Accepted\"}'),
(227, 110, 'Room request automatically approved: Room ID 18 for 2026-01-02 19:00', '2026-01-02 14:03:40', '{\"room_id\":18,\"room_name\":\"ITRM1NB\",\"request_date\":\"2026-01-02 19:00:00\",\"day\":\"Thu\",\"duration\":1.5,\"comment\":\"\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"requester_dept_name\":\"Industrial Technology Department\",\"status\":\"Accepted\"}'),
(228, 110, 'Room request automatically approved: Room ID 18 for 2026-01-02 13:00', '2026-01-02 14:05:24', '{\"room_id\":18,\"room_name\":\"ITRM1NB\",\"request_date\":\"2026-01-02 13:00:00\",\"day\":\"Mon\",\"duration\":2,\"comment\":\"\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"requester_dept_name\":\"Industrial Technology Department\",\"status\":\"Accepted\"}'),
(229, 110, 'Room request automatically approved: Room ID 18 for 2026-01-02 07:00', '2026-01-02 14:07:22', '{\"room_id\":18,\"room_name\":\"ITRM1NB\",\"request_date\":\"2026-01-02 07:00:00\",\"day\":\"Mon\",\"duration\":2,\"comment\":\"\",\"requester\":\"Allan Reynaldo Mabitad\",\"requester_dept_id\":2,\"requester_dept_name\":\"Industrial Technology Department\",\"status\":\"Accepted\"}'),
(230, 108, 'Added new curriculum: BSCSCUR (Level 4) in Computer Studies Department', '2026-01-04 17:48:09', '{\"curriculum_id\":43,\"curriculum_code\":\"\",\"curriculum_name\":\"BSCSCUR\",\"curriculum_type\":\"BS\",\"curriculum_version\":\"\",\"curriculum_level\":\"4\",\"curriculum_year\":\"2026-2027\",\"effective_start_year\":2022,\"effective_end_year\":null,\"department_id\":1,\"total_units\":null,\"status\":\"active\",\"added_by\":108}'),
(232, 108, 'Added new instructor account: jas (Jhasmine Licong)', '2026-01-05 09:11:56', '{\"new_account_id\":146,\"new_instructor_id\":53,\"username\":\"jas\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":1,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(234, 108, 'Updated curriculum mapping for year level(s) 1 in program 31 to use curriculum \'Prior 2018 Curriculu', '2026-01-05 15:58:33', '{\"program_id\":31,\"year_levels\":[1],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(235, 108, 'Updated curriculum mapping for year level(s) 1 in program 31 to use curriculum \'Prior 2018 Curriculu', '2026-01-05 15:58:54', '{\"program_id\":31,\"year_levels\":[1],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(236, 110, 'Added new instructor account: arm (Allan Reynaldo Mabitad)', '2026-01-06 04:48:25', '{\"new_account_id\":147,\"new_instructor_id\":54,\"username\":\"arm\",\"email\":\"venusalthea.silvano@evsu.edu\",\"department_id\":2,\"instructor_status\":\"Regular\",\"academic_rank\":\"Assistant Professor I\",\"designation\":\"Head\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(237, 124, 'Room request automatically approved: Room ID 19 for 2026-01-06 10:00', '2026-01-06 07:06:50', '{\"room_id\":19,\"room_name\":\"ITRM2NB\",\"request_date\":\"2026-01-06 10:00:00\",\"day\":\"Mon\",\"duration\":2,\"comment\":\"Make up class\",\"requester\":\"Edward Bertulfo\",\"requester_dept_id\":1,\"requester_dept_name\":\"Computer Studies Department\",\"status\":\"Accepted\"}'),
(238, 124, 'Room request automatically approved: Room ID 19 for 2026-01-07 07:00', '2026-01-06 07:15:34', '{\"room_id\":19,\"room_name\":\"ITRM2NB\",\"request_date\":\"2026-01-07 07:00:00\",\"day\":\"Wed\",\"duration\":1.5,\"comment\":\"for make up class\",\"requester\":\"Edward Bertulfo\",\"requester_dept_id\":1,\"requester_dept_name\":\"Computer Studies Department\",\"status\":\"Accepted\"}'),
(239, 110, 'Granted room access: Room ID 32 to Department ID 1', '2026-01-06 07:23:09', '{\"room_id\": 32, \"room_name\": \"TechRoom1\", \"granted_to_dept_id\": 1, \"granted_to_dept_name\": \"Computer Studies Department\", \"granted_by_dept_id\": 2, \"granted_by_dept_name\": \"Industrial Technology Department\", \"granted_by\": 110, \"marked_as_read\": \"2026-01-16 11:32:48\"}'),
(240, 124, 'Room request automatically approved: Room ID 32 for 2026-01-06 08:00', '2026-01-06 07:24:59', '{\"room_id\":32,\"room_name\":\"TechRoom1\",\"request_date\":\"2026-01-06 08:00:00\",\"day\":\"Wed\",\"duration\":2,\"comment\":\"Makeup Class\",\"requester\":\"Edward Bertulfo\",\"requester_dept_id\":1,\"requester_dept_name\":\"Computer Studies Department\",\"status\":\"Accepted\"}'),
(241, 109, 'Added new program: BSED-Math (Bachelor of Secondary Education Major in Mathematics) in Teacher Educa', '2026-01-06 11:50:50', '{\"program_id\":37,\"program_code\":\"BSED-Math\",\"program_name\":\"Bachelor of Secondary Education Major in Mathematics\",\"effective_academic_year\":\"2026-2027\",\"program_type\":\"BSED\",\"total_units_required\":174,\"major_track\":\"Major in Math\",\"program_years\":4,\"department_id\":3,\"department_name\":\"Teacher Education Department\",\"added_by\":109}'),
(242, 109, 'Added new program: BSED-Science (Bachelor of Secondary Education Major in Science) in Teacher Educat', '2026-01-06 11:52:58', '{\"program_id\":38,\"program_code\":\"BSED-Science\",\"program_name\":\"Bachelor of Secondary Education Major in Science\",\"effective_academic_year\":\"2026-2027\",\"program_type\":\"BSED\",\"total_units_required\":174,\"major_track\":\"Major in Science\",\"program_years\":4,\"department_id\":3,\"department_name\":\"Teacher Education Department\",\"added_by\":109}'),
(243, 109, 'Added new curriculum: New Curriculum (Level 4) in Teacher Education Department', '2026-01-06 12:14:09', '{\"curriculum_id\":44,\"curriculum_code\":\"\",\"curriculum_name\":\"New Curriculum\",\"curriculum_type\":\"BEED\",\"curriculum_version\":\"\",\"curriculum_level\":\"4\",\"curriculum_year\":\"2026-2027\",\"effective_start_year\":2019,\"effective_end_year\":null,\"department_id\":3,\"total_units\":null,\"status\":\"active\",\"added_by\":109}'),
(244, 110, 'Archived room request: Request ID 13', '2026-01-06 12:21:45', '{\"req_id\":13,\"room_name\":\"TechRoom1\",\"requester\":\"Edward Bertulfo\",\"requester_dept_id\":1,\"status\":\"Archived\",\"updated_by\":110}'),
(245, 109, 'Added new curriculum: Old Curriculum (Level 4) in Teacher Education Department', '2026-01-06 14:01:13', '{\"curriculum_id\":45,\"curriculum_code\":\"\",\"curriculum_name\":\"Old Curriculum\",\"curriculum_type\":\"BPE\",\"curriculum_version\":\"\",\"curriculum_level\":\"4\",\"curriculum_year\":\"2026-2027\",\"effective_start_year\":2019,\"effective_end_year\":null,\"department_id\":3,\"total_units\":null,\"status\":\"active\",\"added_by\":109}'),
(246, 110, 'Added new instructor account: allan (ALAN REYNALDO MABITAD)', '2026-01-06 14:03:40', '{\"new_account_id\":148,\"new_instructor_id\":55,\"username\":\"allan\",\"email\":\"venusalthea.silvano@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Regular\",\"academic_rank\":\"Assistant Professor I\",\"designation\":\"Head\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(247, 148, 'Email verified via OTP: ALAN REYNALDO MABITAD', '2026-01-06 14:06:55', '{\"account_id\":148,\"email\":\"venusalthea.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(248, 109, 'Added new program: BTVTEd (Bachelor of Technical-Vocational Teacher Education (BTVTEd) Major in Food', '2026-01-06 14:11:30', '{\"program_id\":39,\"program_code\":\"BTVTEd\",\"program_name\":\"Bachelor of Technical-Vocational Teacher Education (BTVTEd) Major in Food & Services Management (FSM)\",\"effective_academic_year\":\"2026-2027\",\"program_type\":\"BTVTED\",\"total_units_required\":176,\"major_track\":\"Major in Food & Services Management\",\"program_years\":4,\"department_id\":3,\"department_name\":\"Teacher Education Department\",\"added_by\":109}'),
(249, 110, 'Added new instructor account: mjb (Mary Joy Baltonado)', '2026-01-06 14:16:57', '{\"new_account_id\":149,\"new_instructor_id\":56,\"username\":\"mjb\",\"email\":\"annesophia.silvano@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Regular\",\"academic_rank\":\"Associate Professor IV\",\"designation\":\"Chairperson\\/Coordinator\\/As Officer in Faculty Association\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(250, 149, 'Email verified via OTP: Mary Joy Baltonado', '2026-01-06 16:40:13', '{\"account_id\":149,\"email\":\"annesophia.silvano@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(251, 1, 'Added new instructor account: rl (Rosita Lariosa)', '2026-01-06 16:48:49', '{\"new_account_id\":150,\"new_instructor_id\":57,\"username\":\"rl\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(252, 150, 'Email verified via OTP: Rosita Lariosa', '2026-01-06 16:49:43', '{\"account_id\":150,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(253, 110, 'Added new instructor account: jta (Jesiel Arcillas)', '2026-01-06 16:54:59', '{\"new_account_id\":151,\"new_instructor_id\":58,\"username\":\"jta\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(254, 151, 'Email verified via OTP: Jesiel Arcillas', '2026-01-06 16:56:17', '{\"account_id\":151,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(255, 110, 'Added new instructor account: rbb (Rustico Badilla)', '2026-01-06 16:59:23', '{\"new_account_id\":152,\"new_instructor_id\":59,\"username\":\"rbb\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":4,\"instructor_status\":\"Regular\",\"academic_rank\":\"Associate Professor V\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(257, 110, 'Added new instructor account: dsc (Dionisio Cecilio)', '2026-01-06 17:03:35', '{\"new_account_id\":153,\"new_instructor_id\":60,\"username\":\"dsc\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(258, 153, 'Email verified via OTP: Dionisio Cecilio', '2026-01-06 17:04:21', '{\"account_id\":153,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(259, 110, 'Added new instructor account: agp (Arnel Pepito)', '2026-01-06 17:06:52', '{\"new_account_id\":154,\"new_instructor_id\":61,\"username\":\"agp\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(260, 154, 'Email verified via OTP: Arnel Pepito', '2026-01-06 17:07:33', '{\"account_id\":154,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(261, 110, 'Added new instructor account: mmr (Marvin Rosario)', '2026-01-06 17:11:13', '{\"new_account_id\":155,\"new_instructor_id\":62,\"username\":\"mmr\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(262, 155, 'Email verified via OTP: Marvin Rosario', '2026-01-06 17:12:30', '{\"account_id\":155,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(263, 110, 'Added new instructor account: jjt (Jasper Jim Tajos)', '2026-01-06 17:21:03', '{\"new_account_id\":156,\"new_instructor_id\":63,\"username\":\"jjt\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(264, 156, 'Email verified via OTP: Jasper Jim Tajos', '2026-01-06 17:21:52', '{\"account_id\":156,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(265, 110, 'Added new instructor account: gpb (Generoso Banagbanag)', '2026-01-06 17:24:10', '{\"new_account_id\":157,\"new_instructor_id\":64,\"username\":\"gpb\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(266, 157, 'Email verified via OTP: Generoso Banagbanag', '2026-01-06 17:25:03', '{\"account_id\":157,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(267, 110, 'Added new instructor account: mpc (Marimel Caagay)', '2026-01-06 17:26:46', '{\"new_account_id\":158,\"new_instructor_id\":65,\"username\":\"mpc\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(268, 158, 'Email verified via OTP: Marimel Caagay', '2026-01-06 17:27:23', '{\"account_id\":158,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(269, 110, 'Added new instructor account: ldt (Lorraine Taypa)', '2026-01-06 17:30:14', '{\"new_account_id\":159,\"new_instructor_id\":66,\"username\":\"ldt\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(270, 159, 'Email verified via OTP: Lorraine Taypa', '2026-01-06 17:30:51', '{\"account_id\":159,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(271, 110, 'Added new instructor account: smo (Shaina Mae Ompad)', '2026-01-06 17:33:07', '{\"new_account_id\":160,\"new_instructor_id\":67,\"username\":\"smo\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(272, 160, 'Email verified via OTP: Shaina Mae Ompad', '2026-01-06 17:33:47', '{\"account_id\":160,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(273, 110, 'Added new instructor account: rbs (Romelyn Sasing)', '2026-01-06 17:39:46', '{\"new_account_id\":161,\"new_instructor_id\":68,\"username\":\"rbs\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":2,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(274, 161, 'Email verified via OTP: Romelyn Sasing', '2026-01-06 17:44:13', '{\"account_id\":161,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(275, 109, 'Updated curriculum mapping for year level(s) 1 in program 37 to use curriculum \'New Curriculum\'', '2026-01-06 17:53:04', '{\"program_id\":37,\"year_levels\":[1],\"curriculum_id\":44,\"curriculum_name\":\"New Curriculum\",\"old_mappings\":[]}'),
(276, 110, 'Added new curriculum: Bachelor Industiral Technology Major in Electronics Technology (Level 4) in In', '2026-01-06 21:59:03', '{\"curriculum_id\":46,\"curriculum_code\":\"\",\"curriculum_name\":\"Bachelor Industiral Technology Major in Electronics Technology\",\"curriculum_type\":\"BS\",\"curriculum_version\":\"\",\"curriculum_level\":\"4\",\"curriculum_year\":\"2026-2027\",\"effective_start_year\":2018,\"effective_end_year\":null,\"department_id\":2,\"total_units\":null,\"status\":\"active\",\"added_by\":110}'),
(277, 124, 'Room request automatically approved: Room ID 32 for 2026-01-07 08:00', '2026-01-07 00:04:04', '{\"room_id\": 32, \"room_name\": \"Electronics Laboratory\", \"request_date\": \"2026-01-07 08:00:00\", \"day\": \"Wed\", \"duration\": 0.5, \"class_type\": \"Make Up Class\", \"comment\": \"\", \"requester\": \"Edward Bertulfo\", \"requester_dept_id\": 1, \"requester_dept_name\": \"Computer Studies Department\", \"status\": \"Accepted\", \"marked_as_read\": \"2026-01-10 10:14:54\"}');
INSERT INTO `audit_log` (`log_id`, `acc_id`, `action`, `log_date`, `details`) VALUES
(278, 108, 'Room request submitted: Room ID 32 for 2026-01-08 07:00 (automatically approved)', '2026-01-08 19:38:03', '{\"room_id\": 32, \"room_name\": \"Electronics Laboratory\", \"request_date\": \"2026-01-08 07:00:00\", \"day\": \"Wed\", \"duration\": 1, \"class_type\": \"Regular Class\", \"comment\": \"\", \"requester\": \"Joseph Jaymel Morpos\", \"requester_dept_id\": 1, \"requester_dept_name\": \"Computer Studies Department\", \"status\": \"Accepted\", \"auto_approved\": true, \"marked_as_read\": \"2026-01-08 19:38:42\"}'),
(279, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'BSCSCUR\'', '2026-01-09 11:44:19', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"BSCSCUR\",\"old_mappings\":{\"1\":38}}'),
(280, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-09 11:44:43', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":{\"1\":43}}'),
(281, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'Revise 2018 Curricul', '2026-01-09 11:47:05', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"Revise 2018 Curriculum\",\"old_mappings\":{\"1\":38}}'),
(282, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 10:23:10', '{\"program_id\":30,\"year_levels\":[4],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(283, 108, 'Updated curriculum mapping for year level(s) 2 in program 31 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 10:23:22', '{\"program_id\":31,\"year_levels\":[2],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(284, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 10:23:29', '{\"program_id\":30,\"year_levels\":[2],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(285, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 10:23:29', '{\"program_id\":30,\"year_levels\":[3],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(286, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-10 10:23:29', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(287, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 10:23:29', '{\"program_id\":30,\"year_levels\":[4],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(288, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 18:31:40', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(289, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 18:31:40', '{\"program_id\":30,\"year_levels\":[3],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(290, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 18:31:40', '{\"program_id\":30,\"year_levels\":[2],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(291, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-10 18:31:40', '{\"program_id\":30,\"year_levels\":[4],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(292, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-14 11:37:35', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"1\":38}}'),
(293, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-14 11:39:14', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(294, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-14 11:57:02', '{\"program_id\":30,\"year_levels\":[2],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"2\":38}}'),
(295, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-14 11:57:25', '{\"program_id\":30,\"year_levels\":[2],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(296, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-14 12:10:53', '{\"program_id\":30,\"year_levels\":[3],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"3\":38}}'),
(297, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-14 12:11:32', '{\"program_id\":30,\"year_levels\":[4],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"4\":38}}'),
(298, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-14 12:46:55', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":{\"1\":43}}'),
(299, 108, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4, 5 in program 30 to use curriculum \'Prior 20', '2026-01-15 13:56:15', '{\"program_id\":30,\"year_levels\":[1,2,3,4,5],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":{\"2\":43,\"3\":43,\"4\":43}}'),
(300, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-15 13:58:08', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"1\":38}}'),
(301, 108, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4 in program 30 to use curriculum \'2018 Revise', '2026-01-15 14:11:06', '{\"program_id\":30,\"year_levels\":[1,2,3,4],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"2\":38,\"3\":38,\"4\":38}}'),
(302, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-15 14:12:50', '{\"program_id\":30,\"year_levels\":[3],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":{\"3\":43}}'),
(303, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-01-15 14:15:54', '{\"program_id\":30,\"year_levels\":[4],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":{\"4\":43}}'),
(304, 108, 'Updated curriculum mapping for year level(s) 3, 4 in program 30 to use curriculum \'2018 Revise Curri', '2026-01-15 14:16:47', '{\"program_id\":30,\"year_levels\":[3,4],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"3\":38,\"4\":38}}'),
(305, 108, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4 in program 30 to use curriculum \'Prior 2018 ', '2026-01-15 14:29:11', '{\"program_id\":30,\"year_levels\":[1,2,3,4],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":{\"1\":43,\"2\":43,\"3\":43,\"4\":43}}'),
(306, 108, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4 in program 30 to use curriculum \'2018 Revise', '2026-01-15 14:31:54', '{\"program_id\":30,\"year_levels\":[1,2,3,4],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":{\"1\":38,\"2\":38,\"3\":38,\"4\":38}}'),
(307, 108, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4 in program 30 to use curriculum \'2018 Revise', '2026-01-16 09:36:02', '{\"program_id\":30,\"year_levels\":[1,2,3,4],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(308, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-01-16 09:45:04', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(309, 162, 'Email verified via OTP: Engineering Department', '2026-01-16 10:10:29', '{\"account_id\":162,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(310, 109, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4 in program 34 to use curriculum \'Old Curricu', '2026-01-24 13:27:39', '{\"program_id\":34,\"year_levels\":[1,2,3,4],\"curriculum_id\":45,\"curriculum_name\":\"Old Curriculum of BPED\",\"old_mappings\":{\"1\":40}}'),
(311, 109, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4 in program 34 to use curriculum \'New Curricu', '2026-01-24 13:28:55', '{\"program_id\":34,\"year_levels\":[1,2,3,4],\"curriculum_id\":40,\"curriculum_name\":\"New Curriculum of BPED\",\"old_mappings\":{\"1\":45,\"2\":45,\"3\":45,\"4\":45}}'),
(312, 109, 'Added new instructor account: gmo (Georgina Orbeta)', '2026-01-25 05:48:25', '{\"new_account_id\":163,\"new_instructor_id\":71,\"username\":\"gmo\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":3,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(313, 163, 'Email verified via OTP: Georgina Orbeta', '2026-01-25 05:49:21', '{\"account_id\": 163, \"email\": \"ferlyann.samson@evsu.edu.ph\", \"verification_method\": \"OTP\", \"marked_as_read\": \"2026-01-25 05:49:44\"}'),
(314, 109, 'Added new instructor account: jfa (Julito Acebron)', '2026-01-25 05:53:16', '{\"new_account_id\":164,\"new_instructor_id\":72,\"username\":\"jfa\",\"email\":\"ferlyann.samson@evsu.edu.ph\",\"department_id\":3,\"instructor_status\":\"Regular\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1}'),
(315, 164, 'Email verified via OTP: Julito Acebron', '2026-01-25 05:53:50', '{\"account_id\":164,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(316, 1, 'create_department', '2026-03-01 21:12:39', 'Created department: Business Department (ID: 6) | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(317, 108, 'Room request submitted: Room ID 32 for 2026-03-06 08:00 (automatically approved)', '2026-03-06 23:51:31', '{\"room_id\":32,\"room_name\":\"Electronics Laboratory\",\"request_date\":\"2026-03-06 08:00:00\",\"day\":\"Thu\",\"duration\":1,\"class_type\":\"Make Up Class\",\"comment\":\"\",\"requester\":\"Joseph Jaymel Morpos\",\"requester_dept_id\":1,\"requester_dept_name\":\"Computer Studies Department\",\"status\":\"Accepted\",\"auto_approved\":true}'),
(318, 1, 'update_role_permissions', '2026-03-07 02:19:10', 'Updated system role permissions | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(319, 124, 'Room request submitted: Room ID 30 for 2026-03-19 07:00 (automatically approved)', '2026-03-19 12:20:16', '{\"room_id\":30,\"room_name\":\"testroom1\",\"request_date\":\"2026-03-19 07:00:00\",\"day\":\"Tue\",\"duration\":1,\"class_type\":\"Make Up Class\",\"comment\":\"\",\"requester\":\"Edward Bertulfo\",\"requester_dept_id\":1,\"requester_dept_name\":\"Computer Studies Department\",\"status\":\"Accepted\",\"auto_approved\":true}'),
(320, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-03-19 12:22:11', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(321, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'2018 Revise Curricul', '2026-03-19 12:22:11', '{\"program_id\":30,\"year_levels\":[2],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(322, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'2018 Revise Curricul', '2026-03-19 12:22:11', '{\"program_id\":30,\"year_levels\":[3],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(323, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'2018 Revise Curricul', '2026-03-19 12:22:11', '{\"program_id\":30,\"year_levels\":[4],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(324, 1, 'update_role_permissions', '2026-03-19 12:25:04', 'Updated system role permissions | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(325, 1, 'update_role_permissions', '2026-03-19 12:33:53', 'Updated system role permissions | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(326, 108, 'Room request submitted: Room ID 19 for 2026-03-19 08:00 (automatically approved)', '2026-03-19 13:55:25', '{\"room_id\": 19, \"room_name\": \"ITRM2NB\", \"request_date\": \"2026-03-19 08:00:00\", \"day\": \"Sat\", \"duration\": 1, \"class_type\": \"Make Up Class\", \"comment\": \"\", \"requester\": \"Joseph Jaymel Morpos\", \"requester_dept_id\": 1, \"requester_dept_name\": \"Computer Studies Department\", \"status\": \"Accepted\", \"auto_approved\": true, \"marked_as_read\": \"2026-03-22 07:53:55\"}'),
(327, 129, 'Room request submitted: Room ID 19 for 2026-03-19 13:00 (automatically approved)', '2026-03-19 14:19:04', '{\"room_id\": 19, \"room_name\": \"ITRM2NB\", \"request_date\": \"2026-03-19 13:00:00\", \"day\": \"Mon\", \"duration\": 1, \"class_type\": \"Make Up Class\", \"comment\": \"\", \"requester\": \"Nena Divina Fevidal\", \"requester_dept_id\": 1, \"requester_dept_name\": \"Computer Studies Department\", \"status\": \"Accepted\", \"auto_approved\": true, \"marked_as_read\": \"2026-03-22 07:53:52\"}'),
(328, 1, 'update_user_permissions', '2026-03-22 14:07:54', 'Updated permissions for acc_id 121 | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(329, 1, 'update_role_permissions', '2026-03-22 14:23:37', 'Updated system role permissions | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(330, 1, 'update_user_permissions', '2026-03-22 14:24:27', 'Updated permissions for acc_id 121 | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(331, 1, 'update_user_permissions', '2026-03-22 14:26:30', 'Updated permissions for acc_id 121 | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(332, 1, 'update_user_permissions', '2026-03-22 14:27:57', 'Updated permissions for acc_id 121 | IP: ::1 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(333, 109, 'Added existing instructor to department: Wilson Pogosa to Teacher Education Department', '2026-04-04 22:49:32', '{\"instructor_id\": 33, \"instructor_name\": \"Wilson Pogosa\", \"account_id\": 127, \"department_id\": 3, \"department_name\": \"Teacher Education Department\", \"marked_as_read\": \"2026-04-19 07:24:00\"}'),
(334, 108, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4, 5 in program 30 to use curriculum \'2018 Rev', '2026-04-05 00:35:22', '{\"program_id\": 30, \"year_levels\": [1, 2, 3, 4, 5], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": {\"5\": 38}, \"marked_as_read\": \"2026-04-19 07:22:48\"}'),
(335, 109, 'Added new instructor account: jury (Jurybels Catingub)', '2026-04-18 20:25:21', '{\"account_id\":166,\"instructor_id\":74,\"username\":\"jury\",\"email\":\"jurymae.catingub@evsu.edu.ph\",\"department_id\":3,\"instructor_status\":\"Part-Time\",\"academic_rank\":\"Instructor I\",\"designation\":\"None\",\"role_ids\":[4],\"account_status\":\"Pending\",\"workload_policy\":1,\"school_year\":1,\"is_adding_department\":false,\"new_account\":true}'),
(336, 166, 'Email verified via OTP: Jurybels Catingub', '2026-04-18 20:26:03', '{\"account_id\":166,\"email\":\"jurymae.catingub@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(337, 108, 'Added new curriculum: Bachelor of Science in Information Technology (Level 4) in Computer Studies De', '2026-04-28 10:35:24', '{\"curriculum_id\": 47, \"curriculum_code\": \"\", \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"curriculum_type\": \"\", \"curriculum_version\": \"\", \"curriculum_level\": \"4\", \"curriculum_year\": \"2026-2027\", \"effective_start_year\": 2025, \"effective_end_year\": null, \"department_id\": 1, \"total_units\": null, \"status\": \"active\", \"added_by\": 108, \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(338, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'2018 Revise Curricul', '2026-04-29 03:02:21', '{\"program_id\": 30, \"year_levels\": [3], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-04-29 19:15:43\"}'),
(339, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'Bachelor of Science ', '2026-04-29 03:02:21', '{\"program_id\": 30, \"year_levels\": [1], \"curriculum_id\": 47, \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"old_mappings\": {\"1\": 43}, \"marked_as_read\": \"2026-04-29 19:15:47\"}'),
(340, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'Bachelor of Science ', '2026-04-29 03:02:21', '{\"program_id\": 30, \"year_levels\": [2], \"curriculum_id\": 47, \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"old_mappings\": {\"2\": 43}, \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(341, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'2018 Revise Curricul', '2026-04-29 03:02:21', '{\"program_id\": 30, \"year_levels\": [4], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(342, 1, 'create_department', '2026-05-01 14:25:33', 'Created department: Hospitality Management Department (ID: 7) | IP: 122.3.205.162 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(343, 1, 'create_user', '2026-05-01 14:27:22', 'Created new Admin account: Hospitality Management (HM) | IP: 122.3.205.162 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36'),
(344, 167, 'Email verified via OTP: Hospitality Management', '2026-05-01 14:29:25', '{\"account_id\":167,\"email\":\"ferlyann.samson@evsu.edu.ph\",\"verification_method\":\"OTP\"}'),
(345, 167, 'Added new curriculum: Prior 2018 Curriculum (Level 4) in Hospitality Management Department', '2026-05-10 08:19:49', '{\"curriculum_id\":48,\"curriculum_code\":\"\",\"curriculum_name\":\"Prior 2018 Curriculum\",\"curriculum_type\":\"\",\"curriculum_version\":\"\",\"curriculum_level\":\"4\",\"curriculum_year\":\"2026-2027\",\"effective_start_year\":2018,\"effective_end_year\":null,\"department_id\":7,\"total_units\":null,\"status\":\"active\",\"added_by\":167}'),
(346, 167, 'Added new program: BSHM (BACHELOR OFSCIENCE IN HOSPITALITY MANAGEMENT) in Hospitality Management Dep', '2026-05-10 08:24:52', '{\"program_id\":40,\"program_code\":\"BSHM\",\"program_name\":\"BACHELOR OFSCIENCE IN HOSPITALITY MANAGEMENT\",\"effective_academic_year\":\"2026-2027\",\"program_type\":\"BS\",\"total_units_required\":120,\"major_track\":\"\",\"program_years\":4,\"department_id\":7,\"department_name\":\"Hospitality Management Department\",\"added_by\":167}'),
(347, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'Bachelor of Science ', '2026-05-10 08:31:31', '{\"program_id\": 30, \"year_levels\": [1], \"curriculum_id\": 47, \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(348, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'Bachelor of Science ', '2026-05-10 08:31:31', '{\"program_id\": 30, \"year_levels\": [2], \"curriculum_id\": 47, \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(349, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-10 08:31:31', '{\"program_id\": 30, \"year_levels\": [3], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(350, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-10 08:31:31', '{\"program_id\": 30, \"year_levels\": [4], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(351, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'Bachelor of Science ', '2026-05-10 08:32:22', '{\"program_id\": 30, \"year_levels\": [1], \"curriculum_id\": 47, \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(352, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'Bachelor of Science ', '2026-05-10 08:32:22', '{\"program_id\": 30, \"year_levels\": [2], \"curriculum_id\": 47, \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(353, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-05-10 08:32:22', '{\"program_id\": 30, \"year_levels\": [4], \"curriculum_id\": 38, \"curriculum_name\": \"Prior 2018 Curriculum\", \"old_mappings\": {\"4\": 43}, \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(354, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-05-10 08:32:22', '{\"program_id\": 30, \"year_levels\": [3], \"curriculum_id\": 38, \"curriculum_name\": \"Prior 2018 Curriculum\", \"old_mappings\": {\"3\": 43}, \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(355, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-10 08:33:04', '{\"program_id\": 30, \"year_levels\": [1], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": {\"1\": 47}, \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(356, 108, 'Updated curriculum mapping for year level(s) 1, 2, 3, 4, 5 in program 31 to use curriculum \'2018 Rev', '2026-05-10 08:35:00', '{\"program_id\": 31, \"year_levels\": [1, 2, 3, 4, 5], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": {\"1\": 38, \"2\": 38}, \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(357, 1, 'delete_user', '2026-05-10 09:43:08', 'Archived user: Linda Walker (ID: 165) | IP: 126.209.78.195 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(358, 1, 'delete_user', '2026-05-10 09:43:42', 'Archived user: Linda Walker (ID: 165) | IP: 126.209.78.195 | UA: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36'),
(359, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-11 00:10:48', '{\"program_id\": 30, \"year_levels\": [1], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(360, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-11 00:10:58', '{\"program_id\": 30, \"year_levels\": [1], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(361, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'Bachelor of Science ', '2026-05-11 00:10:58', '{\"program_id\": 30, \"year_levels\": [2], \"curriculum_id\": 47, \"curriculum_name\": \"Bachelor of Science in Information Technology\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(362, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 00:10:58', '{\"program_id\": 30, \"year_levels\": [3], \"curriculum_id\": 38, \"curriculum_name\": \"Prior 2018 Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(363, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 00:10:58', '{\"program_id\": 30, \"year_levels\": [4], \"curriculum_id\": 38, \"curriculum_name\": \"Prior 2018 Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(364, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-11 00:12:05', '{\"program_id\": 30, \"year_levels\": [2], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": {\"2\": 47}, \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(365, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-11 00:12:55', '{\"program_id\": 30, \"year_levels\": [2], \"curriculum_id\": 43, \"curriculum_name\": \"2018 Revise Curriculum\", \"old_mappings\": [], \"marked_as_read\": \"2026-05-11 01:45:27\"}'),
(366, 167, 'Updated curriculum mapping for year level(s) 1 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:19:23', '{\"program_id\":40,\"year_levels\":[1],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(367, 167, 'Updated curriculum mapping for year level(s) 4 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:19:28', '{\"program_id\":40,\"year_levels\":[4],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(368, 167, 'Updated curriculum mapping for year level(s) 2 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:19:28', '{\"program_id\":40,\"year_levels\":[2],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(369, 167, 'Updated curriculum mapping for year level(s) 3 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:19:28', '{\"program_id\":40,\"year_levels\":[3],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(370, 167, 'Updated curriculum mapping for year level(s) 1 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:19:28', '{\"program_id\":40,\"year_levels\":[1],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(371, 167, 'Updated curriculum mapping for year level(s) 4 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:20:17', '{\"program_id\":40,\"year_levels\":[4],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(372, 167, 'Updated curriculum mapping for year level(s) 1 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:20:17', '{\"program_id\":40,\"year_levels\":[1],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(373, 167, 'Updated curriculum mapping for year level(s) 3 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:20:17', '{\"program_id\":40,\"year_levels\":[3],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(374, 167, 'Updated curriculum mapping for year level(s) 2 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:20:17', '{\"program_id\":40,\"year_levels\":[2],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(375, 167, 'Updated curriculum mapping for year level(s) 1 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:21:23', '{\"program_id\":40,\"year_levels\":[1],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(376, 167, 'Updated curriculum mapping for year level(s) 2 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:21:23', '{\"program_id\":40,\"year_levels\":[2],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(377, 167, 'Updated curriculum mapping for year level(s) 4 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:21:23', '{\"program_id\":40,\"year_levels\":[4],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(378, 167, 'Updated curriculum mapping for year level(s) 3 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:21:23', '{\"program_id\":40,\"year_levels\":[3],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(379, 108, 'Updated curriculum mapping for year level(s) 1 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-11 10:25:09', '{\"program_id\":30,\"year_levels\":[1],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(380, 108, 'Updated curriculum mapping for year level(s) 2 in program 30 to use curriculum \'2018 Revise Curricul', '2026-05-11 10:25:09', '{\"program_id\":30,\"year_levels\":[2],\"curriculum_id\":43,\"curriculum_name\":\"2018 Revise Curriculum\",\"old_mappings\":[]}'),
(381, 108, 'Updated curriculum mapping for year level(s) 4 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:25:09', '{\"program_id\":30,\"year_levels\":[4],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(382, 108, 'Updated curriculum mapping for year level(s) 3 in program 30 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 10:25:09', '{\"program_id\":30,\"year_levels\":[3],\"curriculum_id\":38,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(383, 167, 'Updated curriculum mapping for year level(s) 1 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 15:20:31', '{\"program_id\":40,\"year_levels\":[1],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(384, 167, 'Updated curriculum mapping for year level(s) 2 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 15:20:31', '{\"program_id\":40,\"year_levels\":[2],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(385, 167, 'Updated curriculum mapping for year level(s) 3 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 15:20:31', '{\"program_id\":40,\"year_levels\":[3],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}'),
(386, 167, 'Updated curriculum mapping for year level(s) 4 in program 40 to use curriculum \'Prior 2018 Curriculu', '2026-05-11 15:20:31', '{\"program_id\":40,\"year_levels\":[4],\"curriculum_id\":48,\"curriculum_name\":\"Prior 2018 Curriculum\",\"old_mappings\":[]}');

-- --------------------------------------------------------

--
-- Table structure for table `building`
--

CREATE TABLE `building` (
  `bd_id` int(11) NOT NULL,
  `bd_desc` varchar(100) NOT NULL,
  `bd_status` enum('Used','Unused') NOT NULL,
  `dept_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `building`
--

INSERT INTO `building` (`bd_id`, `bd_desc`, `bd_status`, `dept_id`) VALUES
(9, 'New Building', 'Used', NULL),
(10, 'IT Rooms', 'Used', NULL),
(11, 'School Grounds', 'Used', NULL),
(12, 'Virtual', 'Used', NULL),
(14, 'testbuilding', 'Used', NULL),
(15, 'Technology Building', 'Used', NULL),
(17, 'Old Technology Building', 'Used', NULL),
(18, 'New Technology Building', 'Used', NULL),
(19, 'Technology Hall Building', 'Used', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

CREATE TABLE `class` (
  `class_id` int(11) NOT NULL,
  `sy_id` int(11) NOT NULL,
  `curr_id` int(11) NOT NULL,
  `class_term` int(11) NOT NULL,
  `class_lvl` int(11) NOT NULL,
  `class_secno` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`class_id`, `sy_id`, `curr_id`, `class_term`, `class_lvl`, `class_secno`) VALUES
(10, 8, 38, 1, 1, 4),
(11, 9, 38, 1, 1, 4),
(12, 9, 38, 1, 3, 4),
(13, 8, 38, 2, 2, 4),
(14, 8, 38, 2, 3, 4),
(15, 8, 39, 1, 1, 4),
(16, 8, 38, 2, 1, 4),
(17, 8, 39, 2, 2, 3),
(18, 8, 39, 2, 3, 4),
(19, 8, 40, 2, 1, 3),
(20, 8, 39, 2, 4, 4),
(21, 8, 40, 1, 1, 4),
(22, 8, 39, 2, 1, 3),
(23, 10, 43, 1, 1, 3),
(24, 11, 43, 2, 1, 3),
(25, 11, 43, 1, 1, 1),
(26, 9, 43, 2, 1, 1),
(27, 9, 43, 1, 1, 1),
(28, 9, 40, 1, 1, 3),
(29, 9, 40, 1, 2, 3),
(30, 9, 40, 1, 3, 2),
(31, 9, 40, 1, 4, 2),
(32, 9, 40, 2, 1, 3),
(33, 8, 43, 2, 1, 3),
(34, 11, 43, 2, 2, 1),
(35, 10, 43, 2, 1, 3),
(36, 8, 44, 2, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `college`
--

CREATE TABLE `college` (
  `college_id` int(11) NOT NULL,
  `college_name` varchar(100) NOT NULL,
  `college_desc` text DEFAULT NULL,
  `college_code` varchar(10) DEFAULT NULL,
  `college_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `college`
--

INSERT INTO `college` (`college_id`, `college_name`, `college_desc`, `college_code`, `college_status`, `created_at`, `updated_at`) VALUES
(1, 'College of Computer Studies', 'College of Computer Studies offering various computer science programs', 'COCS', 'Active', '2025-09-20 13:47:18', '2025-09-22 16:43:23'),
(2, 'College of Technology', 'College of Technology focusing on technical and vocational programs', 'COT', 'Active', '2025-09-20 13:47:18', '2025-09-22 16:44:20'),
(3, 'College of Education', 'College of Education for teacher training programs', 'COED', 'Active', '2025-09-20 13:47:18', '2025-09-22 16:44:46'),
(4, 'College of Engineering', 'College of Engineering with various category such as Mechanical, Electrical and Civil Engineering', 'COE', 'Active', '2025-12-11 17:21:00', '2025-12-11 17:32:41'),
(5, 'College of Business', NULL, 'COB', 'Inactive', '2026-01-18 14:11:23', '2026-01-18 14:11:38');

-- --------------------------------------------------------

--
-- Table structure for table `conflict`
--

CREATE TABLE `conflict` (
  `conflict_id` int(11) NOT NULL,
  `schd_id` int(11) NOT NULL,
  `conflict_type` enum('Time','Room','Instructor') NOT NULL,
  `conflict_desc` text DEFAULT NULL,
  `detected_on` datetime NOT NULL,
  `resolved` enum('Yes','No') DEFAULT 'No'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `curriculum`
--

CREATE TABLE `curriculum` (
  `curr_id` int(11) NOT NULL,
  `curr_code` varchar(255) DEFAULT NULL,
  `curr_type` varchar(50) DEFAULT NULL,
  `curr_version` varchar(20) DEFAULT NULL,
  `curr_name` varchar(100) NOT NULL,
  `curr_desc` text DEFAULT NULL,
  `curr_objective` text DEFAULT NULL,
  `curr_yr` varchar(20) NOT NULL,
  `curr_effective_start_year` year(4) DEFAULT NULL,
  `curr_effective_end_year` year(4) DEFAULT NULL,
  `curr_lvl` varchar(20) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `program_id` int(11) DEFAULT NULL,
  `curr_status` varchar(20) DEFAULT NULL,
  `curr_total_units` int(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `curriculum`
--

INSERT INTO `curriculum` (`curr_id`, `curr_code`, `curr_type`, `curr_version`, `curr_name`, `curr_desc`, `curr_objective`, `curr_yr`, `curr_effective_start_year`, `curr_effective_end_year`, `curr_lvl`, `dept_id`, `program_id`, `curr_status`, `curr_total_units`) VALUES
(38, NULL, 'BS', NULL, 'Prior 2018 Curriculum', NULL, NULL, '', '2018', NULL, '', 1, 30, 'active', NULL),
(39, NULL, 'BS', NULL, 'Prior 2018 Curriculum', NULL, NULL, '', '2018', NULL, '', 2, 32, 'active', NULL),
(40, NULL, 'BPE', NULL, 'New Curriculum of BPED', NULL, NULL, '', '2021', NULL, '', 3, 34, 'active', NULL),
(41, NULL, 'BEED', NULL, 'Old Curriculum of BEED', NULL, NULL, '', '2018', NULL, '', 3, 33, 'active', NULL),
(43, NULL, 'BS', NULL, '2018 Revise Curriculum', NULL, NULL, '', '2022', NULL, '', 1, 30, 'active', NULL),
(44, NULL, 'BEED', NULL, 'New Curriculum of BEED', NULL, NULL, '', '2025', NULL, '', 3, 33, 'active', NULL),
(45, NULL, 'BPE', NULL, 'Old Curriculum of BPED', NULL, NULL, '', '2018', NULL, '', 3, 34, 'active', NULL),
(46, NULL, '', NULL, 'Bachelor Industiral Technology Major in Electronics Technology', NULL, NULL, '', '2018', '2026', '', 2, NULL, 'active', NULL),
(47, NULL, '', NULL, 'Bachelor of Science in Information Technology', NULL, NULL, '2026-2027', '2025', NULL, '4', 1, NULL, 'active', NULL),
(48, NULL, '', NULL, 'Prior 2018 Curriculum', NULL, NULL, '2026-2027', '2018', NULL, '4', 7, NULL, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `dept_id` int(11) NOT NULL,
  `college_id` int(11) NOT NULL,
  `dept_name` varchar(100) NOT NULL,
  `dept_code` varchar(10) DEFAULT NULL,
  `dept_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`dept_id`, `college_id`, `dept_name`, `dept_code`, `dept_status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Computer Studies Department', 'CS', 'Active', '2025-09-20 13:47:19', '2025-10-12 10:22:22'),
(2, 2, 'Industrial Technology Department', 'INDTECH', 'Active', '2025-09-20 13:47:19', '2025-10-12 10:22:40'),
(3, 3, 'Teacher Education Department', 'TE', 'Active', '2025-09-20 13:47:19', '2025-10-12 10:23:11'),
(4, 4, 'Engineering Departmenrt', 'COE', 'Active', '2025-12-11 17:22:04', '2025-12-11 17:22:04'),
(5, 1, 'Computer Engineering', NULL, 'Active', '2026-01-11 05:24:31', '2026-01-11 05:24:31'),
(6, 1, 'Business Department', NULL, 'Active', '2026-03-01 13:12:39', '2026-03-01 13:12:39'),
(7, 1, 'Hospitality Management Department', 'HM', 'Active', '2026-05-01 14:25:33', '2026-05-01 14:25:33');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_workload`
--

CREATE TABLE `faculty_workload` (
  `workload_id` int(11) NOT NULL,
  `inst_id` int(11) NOT NULL,
  `sy_id` int(11) NOT NULL,
  `total_minutes` int(11) NOT NULL,
  `overload_flag` enum('Yes','No') DEFAULT 'No',
  `underload_flag` enum('Yes','No') DEFAULT 'No',
  `updated_on` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `instructor`
--

CREATE TABLE `instructor` (
  `inst_id` int(11) NOT NULL,
  `inst_user` varchar(50) NOT NULL,
  `inst_lname` varchar(50) NOT NULL,
  `inst_fname` varchar(50) NOT NULL,
  `inst_mname` varchar(50) DEFAULT NULL,
  `inst_suffix` varchar(10) DEFAULT NULL,
  `inst_status` enum('Regular','Part-Time','Contractual') NOT NULL,
  `inst_working_hours` int(255) NOT NULL,
  `administration_hours` int(11) DEFAULT 0,
  `instruction_hours` int(11) DEFAULT 0,
  `research_hours` int(11) DEFAULT 0,
  `extension_hours` int(11) DEFAULT 0,
  `instructional_functions_hours` int(11) DEFAULT 0,
  `consultation_hours` int(11) DEFAULT 0,
  `dept_id` int(11) DEFAULT NULL,
  `rank` enum('University Professor','Professor I','Professor II','Professor III','Professor IV','Professor V','Professor VI','Associate Professor I','Associate Professor II','Associate Professor III','Associate Professor IV','Associate Professor V','Assistant Professor I','Assistant Professor II','Assistant Professor III','Assistant Professor IV','Instructor I','Instructor II','Instructor III') DEFAULT NULL,
  `designation` enum('Vice President','Campus Director','Dean','Director','Head','Chairperson/Coordinator/As Officer in Faculty Association','None') DEFAULT 'None',
  `program_id` int(11) DEFAULT NULL,
  `inst_email` varchar(100) DEFAULT NULL,
  `inst_phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instructor`
--

INSERT INTO `instructor` (`inst_id`, `inst_user`, `inst_lname`, `inst_fname`, `inst_mname`, `inst_suffix`, `inst_status`, `inst_working_hours`, `administration_hours`, `instruction_hours`, `research_hours`, `extension_hours`, `instructional_functions_hours`, `consultation_hours`, `dept_id`, `rank`, `designation`, `program_id`, `inst_email`, `inst_phone`, `created_at`, `updated_at`) VALUES
(24, 'jjm', 'Morpos', 'Joseph Jaymel', 'S', NULL, 'Regular', 40, 24, 9, 2, 1, 1, 3, 1, 'Assistant Professor III', 'Head', 30, '', '+639512457313', '2025-11-29 14:02:11', '2025-12-02 01:24:54'),
(25, 'pje', 'Edosma', 'Phil John', 'T', NULL, 'Part-Time', 20, 0, 2, 0, 0, 4, 4, 1, '', 'None', 30, '', '+639512457313', '2025-11-29 14:07:22', '2025-12-02 02:05:23'),
(26, 'technodept', 'Head', 'Department', '', '', 'Regular', 0, 0, 40, 6, 6, 3, 7, 2, 'Instructor I', 'None', 32, '', '', '2025-12-01 12:16:50', '2026-01-06 04:35:10'),
(27, 'rbe', 'Esolana', 'Rose Bell', 'D', NULL, 'Regular', 0, 0, 40, 0, 0, 0, 0, 1, '', 'None', 31, '', '09512457313', '2025-12-01 16:11:36', '2025-12-01 16:24:59'),
(28, 'wjp', 'Perante', 'Wilferd Jude', 'A', NULL, 'Regular', 0, 5, 15, 8, 4, 4, 4, 1, 'Assistant Professor III', 'Chairperson/Coordinator/As Officer in Faculty Association', 31, '', '+639512457313', '2025-12-01 16:23:55', '2025-12-02 01:24:21'),
(29, 'mfa', 'Aseo', 'Fritz Marc', 'Y', NULL, 'Regular', 0, 0, 18, 4, 8, 4, 6, 1, '', 'None', 30, '', '09512457313', '2025-12-02 01:15:33', '2025-12-02 13:12:53'),
(30, 'eb', 'Bertulfo', 'Edward', 'B', NULL, 'Regular', 0, 5, 9, 8, 4, 4, 4, 1, 'Instructor III', 'None', 31, '', '09512457313', '2025-12-02 01:43:08', '2025-12-02 05:49:26'),
(31, 'pm', 'Miro', 'Pol', 'A', NULL, 'Regular', 0, 0, 18, 4, 8, 4, 6, 1, 'Instructor I', 'None', 30, '', '+639317609172', '2025-12-02 12:55:02', '2025-12-02 13:13:44'),
(32, 'jar', 'Ramas', 'Jude Alexes', 'M', NULL, 'Regular', 0, 24, 9, 2, 1, 1, 3, 1, 'Associate Professor III', 'None', 30, '', '+639317609172', '2025-12-02 12:57:44', '2025-12-02 13:14:17'),
(33, 'wp', 'Pogosa', 'Wilson', 'A', 'Jr.', 'Part-Time', 0, 0, 14, 0, 0, 4, 4, 1, 'Instructor I', 'None', 30, '', '+639317609172', '2025-12-02 13:31:44', '2025-12-02 14:10:04'),
(34, 'rjj', 'Jereza', 'Romulo Joseph', 'M', 'IV', 'Regular', 0, 24, 9, 2, 1, 4, 4, 1, 'Instructor I', 'None', 30, '', '+639317609172', '2025-12-02 14:12:26', '2025-12-02 15:00:42'),
(35, 'ndf', 'Fevidal', 'Nena Divina', 'D', '', 'Regular', 0, 24, 9, 2, 1, 4, 4, 1, 'Instructor I', 'None', 30, '', '+639317609172', '2025-12-02 14:15:35', '2025-12-02 15:01:26'),
(36, 'jc', 'Condes', 'Jaime', 'S', '', 'Part-Time', 0, 0, 15, 0, 0, 3, 4, 1, 'Instructor I', 'None', 31, '', '+639317609172', '2025-12-02 14:33:23', '2025-12-02 15:02:00'),
(37, 'ja', 'Abdullah', 'Jamirah', 'L', '', 'Part-Time', 0, 0, 15, 0, 0, 3, 4, 1, 'Instructor I', 'None', 30, '', '+639317609172', '2025-12-02 14:36:26', '2025-12-09 07:44:02'),
(39, 'jl', 'Lopez', 'Jotham', 'P', '', 'Part-Time', 0, 0, 9, 0, 0, 3, 4, 1, '', 'None', 30, '', '+639317609172', '2025-12-02 14:40:45', '2025-12-02 14:44:39'),
(40, 'lrt', 'Tugonon', 'Leo Ritchie', 'M', '', 'Part-Time', 0, 0, 9, 0, 0, 3, 0, 1, '', 'None', 30, '', '+639317609172', '2025-12-02 15:07:45', '2025-12-02 15:32:17'),
(41, 'dl', 'Ligutan', 'Daniel', 'V', '', 'Part-Time', 0, 0, 10, 0, 0, 3, 4, 1, '', 'None', 30, '', '+639317609172', '2025-12-02 15:09:24', '2025-12-02 15:31:35'),
(43, 'mjj', 'Justimbaste', 'Ma. Johara', 'V', '', 'Part-Time', 0, 0, 9, 0, 0, 3, 4, 1, '', 'None', 30, '', '+639317609172', '2025-12-02 15:17:33', '2025-12-02 15:30:23'),
(44, 'cb', 'Bertulfo', 'Cherry', 'C', '', 'Part-Time', 0, 0, 9, 0, 0, 3, 4, 1, '', 'None', 30, '', '+639317609172', '2025-12-02 15:33:49', '2025-12-03 01:10:52'),
(45, 'sra', 'Arcenal', 'Sedrick Razeal', 'R', '', 'Part-Time', 0, 0, 15, 0, 0, 3, 4, 1, '', 'None', 30, '', '+639317609172', '2025-12-02 15:43:49', '2025-12-11 12:08:40'),
(46, 'car', 'Rallos', 'Chito Antonio', 'A', '', 'Part-Time', 0, 0, 21, 0, 0, 3, 4, 1, '', 'None', 30, '', '+639317609172', '2025-12-02 15:53:59', '2025-12-11 12:08:33'),
(47, 'nhkwhekh', 'hh', 'khkhkh', 'h', NULL, 'Regular', 0, 0, 0, 0, 0, 0, 0, NULL, '', 'None', NULL, NULL, NULL, '2025-12-11 02:15:40', '2025-12-11 02:15:40'),
(48, 'chan', 'yanix', 'chanchan', 'l', NULL, 'Regular', 0, 0, 5, 0, 0, 0, 0, NULL, '', 'None', NULL, NULL, '', '2025-12-11 02:57:03', '2025-12-11 03:36:41'),
(49, 'lovely', 'Mabini', 'Lovely', 'J', '', 'Regular', 0, 0, 8, 0, 0, 0, 0, 1, 'Instructor I', 'None', 30, NULL, '', '2025-12-11 18:16:10', '2025-12-11 18:38:07'),
(50, 'eder', 'Duallo', 'Eder', '', '', 'Part-Time', 0, 0, 27, 0, 0, 0, 0, 3, '', 'None', NULL, NULL, '', '2025-12-12 22:37:22', '2025-12-12 22:37:22'),
(51, 'educdept', 'Department', 'Techer Education', '', '', 'Regular', 0, 0, 40, 6, 6, 3, 7, 3, 'Instructor I', 'None', 33, '', '', '2025-12-13 03:56:10', '2026-01-16 10:13:24'),
(52, 'fas', 'Samson', 'Ferly Ann', '', NULL, 'Regular', 0, 12, 9, 10, 3, 3, 3, NULL, 'Assistant Professor I', 'Head', NULL, NULL, NULL, '2026-01-05 07:13:23', '2026-01-05 07:13:23'),
(53, 'jas', 'Licong', 'Jhasmine', '', '', 'Regular', 0, 0, 18, 6, 6, 3, 7, 1, 'Instructor I', 'None', 31, NULL, '', '2026-01-05 09:11:53', '2026-01-05 09:11:53'),
(54, 'arm', 'Mabitad', 'Allan Reynaldo', 'E', '', 'Regular', 0, 24, 9, 2, 1, 9, 4, 2, 'Assistant Professor I', 'Head', 32, NULL, '', '2026-01-06 04:48:21', '2026-01-06 04:48:21'),
(55, 'allan', 'MABITAD', 'ALAN REYNALDO', 'E', '', 'Regular', 0, 24, 9, 2, 1, 9, 4, 2, 'Assistant Professor I', 'Head', 32, NULL, '', '2026-01-06 14:03:37', '2026-01-06 14:03:37'),
(56, 'mjb', 'Baltonado', 'Mary Joy', 'B', '', 'Regular', 0, 9, 15, 12, 8, 18, 7, 2, 'Associate Professor IV', 'None', 32, '', '', '2026-01-06 14:16:54', '2026-01-06 16:40:53'),
(57, 'rl', 'Lariosa', 'Rosita', 'D', '', 'Regular', 0, 0, 18, 6, 6, 3, 7, 2, 'Instructor I', 'None', 32, '', '', '2026-01-06 16:48:46', '2026-01-06 16:51:59'),
(58, 'jta', 'Arcillas', 'Jesiel', 't', '', 'Regular', 0, 0, 18, 6, 6, 3, 7, 2, 'Instructor I', 'None', 32, '', '', '2026-01-06 16:54:56', '2026-01-06 16:57:33'),
(59, 'rbb', 'Badilla', 'Rustico', 'b', '', 'Regular', 0, 0, 12, 9, 9, 3, 7, 0, 'Instructor I', 'None', 36, '', '', '2026-01-06 16:59:19', '2026-01-06 17:01:36'),
(60, 'dsc', 'Cecilio', 'Dionisio', 's', 'Jr.', 'Regular', 0, 0, 18, 6, 6, 3, 7, 2, 'Instructor I', 'None', 36, '', '', '2026-01-06 17:03:32', '2026-01-06 17:05:11'),
(61, 'agp', 'Pepito', 'Arnel', 'G', '', 'Regular', 0, 0, 18, 6, 6, 3, 7, 2, 'Instructor I', 'None', 36, '', '', '2026-01-06 17:06:49', '2026-01-06 17:08:23'),
(62, 'mmr', 'Rosario', 'Marvin', 'M', '', 'Part-Time', 0, 0, 8, 0, 0, 0, 0, 2, '', 'None', 32, '', '', '2026-01-06 17:11:10', '2026-01-06 17:17:37'),
(63, 'jjt', 'Tajos', 'Jasper Jim', 'P', '', 'Part-Time', 0, 0, 18, 0, 0, 0, 0, 2, '', 'None', 36, '', '', '2026-01-06 17:21:00', '2026-01-06 17:22:37'),
(64, 'gpb', 'Banagbanag', 'Generoso', 'P', 'Jr', 'Part-Time', 0, 0, 13, 0, 0, 0, 0, 2, '', 'None', 36, '', '', '2026-01-06 17:24:07', '2026-01-06 17:25:34'),
(65, 'mpc', 'Caagay', 'Marimel', 'P', '', 'Part-Time', 0, 0, 10, 0, 0, 0, 0, 2, '', 'None', 36, '', '', '2026-01-06 17:26:43', '2026-01-06 17:27:59'),
(66, 'ldt', 'Taypa', 'Lorraine', 'D', '', 'Part-Time', 0, 0, 8, 0, 0, 0, 0, 2, '', 'None', 36, '', '', '2026-01-06 17:30:11', '2026-01-06 17:31:35'),
(67, 'smo', 'Ompad', 'Shaina Mae', 'c', '', 'Part-Time', 0, 0, 8, 0, 0, 0, 0, 2, '', 'None', 32, '', '', '2026-01-06 17:33:05', '2026-01-06 17:35:51'),
(68, 'rbs', 'Sasing', 'Romelyn', 'B', '', 'Part-Time', 0, 0, 15, 0, 0, 0, 0, 2, '', 'None', 32, NULL, '', '2026-01-06 17:39:44', '2026-01-06 17:39:44'),
(69, 'comdept', 'Morpos', 'Joseph Jaymel', 'S', NULL, 'Regular', 0, 0, 40, 0, 0, 0, 0, 1, 'Assistant Professor III', 'Head', 30, NULL, '', '2026-01-08 19:38:03', '2026-01-08 22:37:09'),
(70, 'engdept', 'Department', 'Engineering', '', NULL, 'Regular', 0, 0, 0, 0, 0, 0, 0, NULL, '', 'None', NULL, NULL, '', '2026-01-25 05:44:42', '2026-01-25 05:44:42'),
(71, 'gmo', 'Orbeta', 'Georgina', 'M', '', 'Regular', 0, 0, 18, 4, 8, 4, 6, 3, 'Instructor I', 'None', 34, '', '', '2026-01-25 05:48:22', '2026-01-25 05:50:16'),
(72, 'jfa', 'Acebron', 'Julito', 'f', '', 'Regular', 0, 0, 18, 4, 8, 4, 6, 3, 'Instructor I', 'None', 39, '', '', '2026-01-25 05:53:11', '2026-01-25 05:54:21'),
(73, 'lw', 'Walker', 'Linda', 'A', NULL, '', 0, 0, 0, 0, 0, 0, 0, NULL, '', 'None', NULL, NULL, NULL, '2026-03-01 12:47:57', '2026-03-01 12:47:57'),
(74, 'jury', 'Catingub', 'Jurybels', '', '', 'Part-Time', 0, 0, 18, 6, 6, 3, 7, 3, 'Instructor I', 'None', NULL, NULL, '', '2026-04-18 12:25:08', '2026-04-18 12:25:08'),
(75, 'HM', 'Management', 'Hospitality', '', NULL, '', 0, 0, 0, 0, 0, 0, 0, NULL, '', 'None', NULL, NULL, NULL, '2026-05-01 14:27:22', '2026-05-01 14:27:22');

-- --------------------------------------------------------

--
-- Table structure for table `instructor_department_appointment`
--

CREATE TABLE `instructor_department_appointment` (
  `id` int(11) NOT NULL,
  `inst_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `appointment_status` enum('Regular','Part-Time','Contractual') NOT NULL DEFAULT 'Regular',
  `instruction_hours` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instructor_department_appointment`
--

INSERT INTO `instructor_department_appointment` (`id`, `inst_id`, `dept_id`, `appointment_status`, `instruction_hours`, `created_at`, `updated_at`) VALUES
(1, 24, 1, 'Regular', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(2, 25, 1, 'Part-Time', 2, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(3, 26, 2, 'Regular', 40, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(4, 27, 1, 'Regular', 40, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(5, 28, 1, 'Regular', 15, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(6, 29, 1, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(7, 30, 1, 'Regular', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(8, 31, 1, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(9, 32, 1, 'Regular', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(10, 33, 1, 'Part-Time', 14, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(11, 34, 1, 'Regular', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(12, 35, 1, 'Regular', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(13, 36, 1, 'Part-Time', 15, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(14, 37, 1, 'Part-Time', 15, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(15, 39, 1, 'Part-Time', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(16, 40, 1, 'Part-Time', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(17, 41, 1, 'Part-Time', 10, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(18, 43, 1, 'Part-Time', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(19, 44, 1, 'Part-Time', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(20, 45, 1, 'Part-Time', 15, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(21, 46, 1, 'Part-Time', 21, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(22, 49, 1, 'Regular', 8, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(23, 50, 3, 'Part-Time', 27, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(24, 51, 3, 'Regular', 40, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(25, 53, 1, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(26, 54, 2, 'Regular', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(27, 55, 2, 'Regular', 9, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(28, 56, 2, 'Regular', 15, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(29, 57, 2, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(30, 58, 2, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(31, 60, 2, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(32, 61, 2, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(33, 62, 2, 'Part-Time', 8, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(34, 63, 2, 'Part-Time', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(35, 64, 2, 'Part-Time', 13, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(36, 65, 2, 'Part-Time', 10, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(37, 66, 2, 'Part-Time', 8, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(38, 67, 2, 'Part-Time', 8, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(39, 68, 2, 'Part-Time', 15, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(40, 69, 1, 'Regular', 40, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(41, 71, 3, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(42, 72, 3, 'Regular', 18, '2026-04-04 16:03:27', '2026-04-04 16:03:27'),
(43, 74, 3, 'Part-Time', 18, '2026-04-18 12:25:08', '2026-04-18 12:25:08');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(100) DEFAULT NULL,
  `permission_name` varchar(50) NOT NULL,
  `permission_display_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `permission_display_name`, `description`, `module`) VALUES
(1, 'manage_users', 'manage_users', 'Manage Users', 'Create, update, and delete user accounts', 'users'),
(2, 'manage_roles', 'manage_roles', 'Manage Roles', 'Assign and modify user roles', 'users'),
(3, 'view_users', 'view_users', 'View Users', 'View user information and lists', 'users'),
(4, 'manage_colleges', 'manage_colleges', 'Manage Colleges', 'Create and manage colleges', 'academic'),
(5, 'manage_departments', 'manage_departments', 'Manage Departments', 'Create and manage departments', 'academic'),
(6, 'manage_programs', 'manage_programs', 'Manage Programs', 'Create and manage academic programs', 'academic'),
(7, 'manage_subjects', 'manage_subjects', 'Manage Subjects', 'Create and manage subjects', 'academic'),
(8, 'manage_sections', 'manage_sections', 'Manage Sections', 'Create and manage class sections', 'academic'),
(9, 'manage_schedules', 'manage_schedules', 'Manage Schedules', 'Create and modify class schedules', 'scheduling'),
(10, 'view_schedules', 'view_schedules', 'View Schedules', 'View class schedules', 'scheduling'),
(11, 'approve_schedules', 'approve_schedules', 'Approve Schedules', 'Approve or reject schedule changes', 'scheduling'),
(12, 'manage_rooms', 'manage_rooms', 'Manage Rooms', 'Create and manage rooms and buildings', 'rooms'),
(13, 'view_rooms', 'view_rooms', 'View Rooms', 'View Rooms', 'rooms'),
(14, 'manage_conflicts', 'manage_conflicts', 'Manage Conflicts', 'Resolve scheduling conflicts', 'conflicts'),
(15, 'view_conflicts', 'view_conflicts', 'View Conflicts', 'View scheduling conflicts', 'conflicts'),
(16, 'manage_workloads', 'manage_workloads', 'Manage Workloads', 'Manage faculty workloads', 'workloads'),
(17, 'view_workloads', 'view_workloads', 'View Workloads', 'View faculty workload information', 'workloads'),
(18, 'generate_reports', 'generate_reports', 'Generate Reports', 'Generate system reports', 'reports'),
(19, 'view_reports', 'view_reports', 'View Reports', 'View system reports', 'reports'),
(20, 'manage_settings', 'manage_settings', 'Manage Settings', 'Modify system settings', 'system'),
(21, 'view_audit_logs', 'view_audit_logs', 'View Audit Logs', 'View system audit logs', 'system'),
(22, 'view_notifications', 'view_notifications', 'View Notifications', 'View system notifications', 'system'),
(23, 'manage_curriculum', 'manage_curriculum', 'Manage Curriculum', 'Create and manage curriculum', 'academic'),
(24, 'assign_schedules', 'assign_schedules', 'Assign Schedules', 'Assign and edit class schedules (department scope)', 'scheduling'),
(25, 'approve_room_requests', 'approve_room_requests', 'Approve Room Requests', 'Approve or deny room booking requests', 'rooms'),
(26, 'view_own_schedule', 'view_own_schedule', 'View Own Schedule', 'View personal teaching schedule', 'scheduling');

-- --------------------------------------------------------

--
-- Table structure for table `permission_content`
--

CREATE TABLE `permission_content` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `component_name` varchar(100) NOT NULL,
  `component_path` varchar(255) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'bi-circle',
  `module` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permission_content`
--

INSERT INTO `permission_content` (`id`, `permission_key`, `component_name`, `component_path`, `display_name`, `icon`, `module`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'manage_users', 'user_management', 'components/user_management.php', 'User Management', 'bi-people', 'users', 1, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45'),
(2, 'manage_roles', 'role_management', 'components/role_management.php', 'Role Management', 'bi-shield-gear', 'users', 2, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45'),
(3, 'manage_schedules', 'schedule_management', 'components/schedule_management.php', 'Schedule Management', 'bi-calendar-week', 'scheduling', 3, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45'),
(4, 'manage_subjects', 'subject_management', 'components/subject_management.php', 'Subject Management', 'bi-book', 'academic', 4, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45'),
(5, 'manage_rooms', 'room_management', 'components/room_management.php', 'Room Management', 'bi-building', 'rooms', 5, 1, '2025-09-28 18:49:45', '2025-10-25 13:25:00'),
(6, 'manage_programs', 'program_management', 'components/program_management.php', 'Program Management', 'bi-mortarboard', 'academic', 6, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45'),
(7, 'manage_departments', 'department_management', 'components/department_management.php', 'Department Management', 'bi-building-gear', 'academic', 7, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45'),
(8, 'view_reports', 'reports', 'components/reports.php', 'Reports', 'bi-bar-chart', 'analytics', 8, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45'),
(9, 'manage_settings', 'settings', 'components/settings.php', 'Settings', 'bi-gear', 'system', 9, 1, '2025-09-28 18:49:45', '2025-09-28 18:49:45');

-- --------------------------------------------------------

--
-- Table structure for table `program`
--

CREATE TABLE `program` (
  `program_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `program_code` varchar(20) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `effective_academic_year` varchar(20) DEFAULT NULL,
  `program_type` varchar(50) DEFAULT NULL,
  `total_units_required` int(11) DEFAULT NULL,
  `major_track` varchar(100) DEFAULT NULL,
  `program_desc` text DEFAULT NULL,
  `program_years` tinyint(2) UNSIGNED NOT NULL DEFAULT 4,
  `program_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program`
--

INSERT INTO `program` (`program_id`, `dept_id`, `program_code`, `program_name`, `effective_academic_year`, `program_type`, `total_units_required`, `major_track`, `program_desc`, `program_years`, `program_status`, `created_at`, `updated_at`) VALUES
(30, 1, 'BSIT', 'Bachelor of Science in Information Technology', '2025-2026', 'BS', 120, 'Information Technology', '', 4, 'Active', '2025-11-29 12:30:18', '2025-11-29 12:30:18'),
(31, 1, 'BSCS', 'Bachelor of Science in Computer Science', '2025-2026', 'BS', 176, 'Computer Science', '', 4, 'Active', '2025-11-30 04:15:57', '2026-01-16 06:32:09'),
(32, 2, 'BindTech', 'Bachelor of Industrial Technology Major in Culinary Technology', '2025-2026', 'BS', 120, 'Culinary Technology', '', 4, 'Active', '2025-12-01 12:41:38', '2025-12-01 12:41:38'),
(33, 3, 'BEEd', 'Bachelor of Elementary Education', '2025-2026', 'BEED', 185, '', '', 4, 'Active', '2025-12-12 21:25:41', '2025-12-12 21:25:41'),
(34, 3, 'BPEd', 'Bachelor of Physical EDucation', '2025-2026', 'BPE', 194, '', '', 4, 'Active', '2025-12-12 21:53:11', '2025-12-12 21:53:11'),
(36, 2, 'BIT', 'Bachelor of Industrial Technology Major in Electronics Technology', '2025-2026', 'Other', 162, 'Electronics Technology', '', 4, 'Active', '2025-12-14 18:38:55', '2025-12-14 18:38:55'),
(37, 3, 'BSED-Math', 'Bachelor of Secondary Education Major in Mathematics', '2026-2027', 'BSED', 174, 'Major in Math', '', 4, 'Active', '2026-01-06 11:50:50', '2026-01-06 11:50:50'),
(38, 3, 'BSED-Science', 'Bachelor of Secondary Education Major in Science', '2026-2027', 'BSED', 174, 'Major in Science', '', 4, 'Active', '2026-01-06 11:52:58', '2026-01-06 11:52:58'),
(39, 3, 'BTVTEd', 'Bachelor of Technical-Vocational Teacher Education (BTVTEd) Major in Food & Services Management (FSM', '2026-2027', 'BTVTED', 176, 'Major in Food & Services Management', '', 4, 'Active', '2026-01-06 14:11:30', '2026-01-06 14:11:30'),
(40, 7, 'BSHM', 'BACHELOR OF SCIENCE IN HOSPITALITY MANAGEMENT', '2026-2027', 'BS', 120, '', '', 4, 'Active', '2026-05-10 08:24:52', '2026-05-10 08:44:55');

-- --------------------------------------------------------

--
-- Table structure for table `program_year_level_curriculum`
--

CREATE TABLE `program_year_level_curriculum` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `year_level` tinyint(4) NOT NULL COMMENT 'Year level (1-5)',
  `curr_id` int(11) NOT NULL COMMENT 'Curriculum ID that this year level uses',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Maps which curriculum each year level uses for each program';

--
-- Dumping data for table `program_year_level_curriculum`
--

INSERT INTO `program_year_level_curriculum` (`id`, `program_id`, `year_level`, `curr_id`, `created_at`, `updated_at`) VALUES
(1, 30, 1, 43, '2026-01-01 15:44:08', '2026-05-11 10:25:09'),
(2, 30, 2, 43, '2026-01-01 15:44:08', '2026-05-11 10:25:09'),
(3, 30, 3, 38, '2026-01-01 15:44:08', '2026-05-11 10:25:09'),
(4, 32, 1, 39, '2026-01-01 15:44:08', '2026-01-01 15:44:08'),
(5, 33, 1, 41, '2026-01-01 15:44:08', '2026-01-01 15:44:08'),
(6, 33, 2, 41, '2026-01-01 15:44:08', '2026-01-01 15:44:08'),
(7, 34, 1, 40, '2026-01-01 15:44:08', '2026-01-24 13:28:55'),
(8, 36, 1, 39, '2026-01-01 15:44:08', '2026-01-01 15:44:08'),
(9, 36, 2, 39, '2026-01-01 15:44:08', '2026-01-01 15:44:08'),
(16, 31, 1, 43, '2026-01-05 15:58:33', '2026-05-10 08:35:00'),
(18, 37, 1, 44, '2026-01-06 17:53:04', '2026-01-06 17:53:04'),
(22, 30, 4, 38, '2026-01-10 10:23:10', '2026-05-11 10:25:09'),
(23, 31, 2, 43, '2026-01-10 10:23:22', '2026-05-10 08:35:00'),
(43, 30, 5, 43, '2026-01-15 13:56:15', '2026-04-04 16:35:22'),
(67, 34, 2, 40, '2026-01-24 13:27:39', '2026-01-24 13:28:55'),
(68, 34, 3, 40, '2026-01-24 13:27:39', '2026-01-24 13:28:55'),
(69, 34, 4, 40, '2026-01-24 13:27:39', '2026-01-24 13:28:55'),
(98, 31, 3, 43, '2026-05-10 08:35:00', '2026-05-10 08:35:00'),
(99, 31, 4, 43, '2026-05-10 08:35:00', '2026-05-10 08:35:00'),
(100, 31, 5, 43, '2026-05-10 08:35:00', '2026-05-10 08:35:00'),
(108, 40, 1, 48, '2026-05-11 10:19:23', '2026-05-11 15:20:31'),
(109, 40, 4, 48, '2026-05-11 10:19:28', '2026-05-11 15:20:31'),
(110, 40, 2, 48, '2026-05-11 10:19:28', '2026-05-11 15:20:31'),
(111, 40, 3, 48, '2026-05-11 10:19:28', '2026-05-11 15:20:31');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_display_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `role_display_name`, `description`) VALUES
(1, 'Admin support', 'System Administrator', 'Full system access and role management'),
(2, 'Admin', 'Department Head', 'Department management and user creation'),
(3, 'Moderator', 'Schedule Moderator', 'Schedule management and conflict resolution'),
(4, 'User', 'Instructor', 'Class management and schedule viewing');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(2, 1),
(1, 2),
(1, 3),
(2, 3),
(3, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(3, 9),
(2, 10),
(3, 10),
(4, 10),
(2, 11),
(3, 11),
(2, 12),
(2, 13),
(4, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(4, 17),
(1, 20),
(2, 20),
(1, 21),
(2, 21),
(1, 22),
(2, 22),
(4, 22),
(2, 23),
(3, 24),
(4, 26);

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `rm_id` int(11) NOT NULL,
  `bd_id` int(11) NOT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `rm_name` varchar(50) NOT NULL,
  `rm_type` enum('Lab','Lec','Special') NOT NULL,
  `rm_status` enum('Used','Unused') NOT NULL,
  `rm_capacity` int(11) NOT NULL,
  `rm_features` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`rm_id`, `bd_id`, `dept_id`, `rm_name`, `rm_type`, `rm_status`, `rm_capacity`, `rm_features`) VALUES
(18, 10, 1, 'ITRM1NB', 'Lab', 'Used', 40, ''),
(19, 10, 1, 'ITRM2NB', 'Lec', 'Used', 40, ''),
(20, 10, 1, 'ITRM3NB', 'Lab', 'Used', 40, ''),
(21, 10, 1, 'ITRM4NB', 'Lec', 'Used', 40, ''),
(22, 10, 1, 'ITRM5NB', 'Lec', 'Used', 40, ''),
(23, 9, 1, 'I-Lab-1 (Computer Lab)', 'Lab', 'Used', 40, ''),
(24, 9, 1, 'I-Lab-2 (Computer Lab)', 'Lab', 'Used', 40, ''),
(25, 9, 1, 'I-Lab-3 (Computer Lab)', 'Lec', 'Used', 40, ''),
(26, 11, 1, '1', 'Special', 'Used', 50, ''),
(27, 11, 1, 'Stage', 'Special', 'Used', 50, ''),
(28, 10, 1, 'Room 6', 'Lec', 'Used', 50, ''),
(29, 12, 1, '1', 'Lec', 'Used', 50, ''),
(30, 14, 1, 'testroom1', 'Lec', 'Used', 40, ''),
(31, 14, 1, 'testroom2', 'Lec', 'Used', 50, ''),
(32, 15, 2, 'Electronics Laboratory', 'Lab', 'Used', 40, ''),
(33, 15, 2, 'Culinary Arts Demo Room', 'Lab', 'Used', 50, ''),
(34, 15, 2, 'Culinary Arts Kitchen Laboratory', 'Lab', 'Used', 40, ''),
(35, 15, 2, 'Make Shift 1 Room', 'Lec', 'Used', 40, ''),
(36, 15, 2, 'Make Shift 2 Room', 'Lec', 'Used', 40, ''),
(37, 15, 2, 'Make Shift 3 Room', 'Lec', 'Used', 40, ''),
(38, 15, 2, 'Make Shift 4 Room', 'Lec', 'Used', 40, ''),
(39, 17, 2, 'ROOM 5', 'Lec', 'Used', 40, ''),
(40, 17, 2, 'ROOM 8A', 'Lec', 'Used', 40, ''),
(41, 18, 2, 'Kitchen Extension Laboratory and Lecture', 'Lec', 'Used', 15, ''),
(42, 17, 2, 'Room 8B', 'Lec', 'Used', 40, ''),
(43, 19, 2, 'FUNCTION HALL A', 'Lec', 'Used', 40, ''),
(44, 19, 2, 'FUNCTION HALL B', 'Lec', 'Used', 40, ''),
(45, 19, 2, '2nd Floor Room 3', 'Lec', 'Used', 40, '');

-- --------------------------------------------------------

--
-- Table structure for table `room_access`
--

CREATE TABLE `room_access` (
  `access_id` int(11) NOT NULL,
  `rm_id` int(11) NOT NULL,
  `granted_to_dept_id` int(11) NOT NULL COMMENT 'Department that has been granted access',
  `granted_by_dept_id` int(11) NOT NULL COMMENT 'Department that owns the room and granted access',
  `granted_by_acc_id` int(11) DEFAULT NULL COMMENT 'Admin who granted the access',
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Revoked') NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_access`
--

INSERT INTO `room_access` (`access_id`, `rm_id`, `granted_to_dept_id`, `granted_by_dept_id`, `granted_by_acc_id`, `granted_at`, `status`) VALUES
(7, 18, 2, 1, 108, '2025-12-01 11:27:27', 'Active'),
(8, 18, 3, 1, 108, '2025-12-01 13:41:34', 'Active'),
(9, 19, 2, 1, 108, '2025-12-03 04:05:05', 'Active'),
(10, 30, 3, 1, 108, '2025-12-13 03:49:22', 'Active'),
(11, 32, 1, 2, 110, '2026-01-06 07:23:09', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `room_request`
--

CREATE TABLE `room_request` (
  `req_id` int(11) NOT NULL,
  `rm_id` int(11) NOT NULL,
  `inst_id` int(11) NOT NULL,
  `req_date` datetime NOT NULL,
  `schd_day` enum('Mon','Tue','Wed','Thu','Fri','Sat','Sun') DEFAULT NULL,
  `schd_start` time DEFAULT NULL,
  `schd_end` time DEFAULT NULL,
  `req_status` enum('Pending','Accepted','Declined') DEFAULT 'Pending',
  `req_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_request`
--

INSERT INTO `room_request` (`req_id`, `rm_id`, `inst_id`, `req_date`, `schd_day`, `schd_start`, `schd_end`, `req_status`, `req_comment`) VALUES
(4, 18, 26, '2025-12-01 09:00:00', 'Mon', '09:00:00', '12:00:00', '', 'Can we use this hehehe'),
(5, 19, 26, '2025-12-03 07:00:00', 'Mon', '07:00:00', '10:00:00', 'Accepted', 'can we use the room?'),
(6, 30, 51, '2025-12-13 07:00:00', 'Mon', '07:00:00', '10:00:00', 'Accepted', ''),
(7, 18, 26, '2026-01-02 16:00:00', 'Thu', '16:00:00', '19:00:00', 'Accepted', ''),
(8, 18, 26, '2026-01-02 19:00:00', 'Thu', '19:00:00', '20:30:00', 'Accepted', ''),
(9, 18, 26, '2026-01-02 13:00:00', 'Mon', '13:00:00', '15:00:00', 'Accepted', ''),
(10, 18, 26, '2026-01-02 07:00:00', 'Mon', '07:00:00', '09:00:00', 'Accepted', ''),
(11, 19, 30, '2026-01-06 10:00:00', 'Mon', '10:00:00', '12:00:00', 'Accepted', 'Make up class'),
(12, 19, 30, '2026-01-07 07:00:00', 'Wed', '07:00:00', '08:30:00', 'Accepted', 'for make up class'),
(13, 32, 30, '2026-01-06 08:00:00', 'Wed', '08:00:00', '10:00:00', '', 'Makeup Class'),
(14, 32, 30, '2026-01-07 08:00:00', 'Wed', '08:00:00', '08:30:00', 'Accepted', '[Class Type: Make Up Class] [Expires: 2026-01-12]'),
(15, 32, 69, '2026-01-08 07:00:00', 'Wed', '07:00:00', '08:00:00', 'Accepted', '[Class Type: Regular Class]'),
(16, 32, 69, '2026-03-06 08:00:00', 'Thu', '08:00:00', '09:00:00', 'Accepted', '[Class Type: Make Up Class] [Expires: 2026-03-09]'),
(17, 30, 30, '2026-03-19 07:00:00', 'Tue', '07:00:00', '08:00:00', 'Accepted', '[Class Type: Make Up Class] [Expires: 2026-03-23]'),
(18, 19, 69, '2026-03-19 08:00:00', 'Sat', '08:00:00', '09:00:00', 'Accepted', '[Class Type: Make Up Class] [Expires: 2026-03-23]'),
(19, 19, 35, '2026-03-19 13:00:00', 'Mon', '13:00:00', '14:00:00', 'Accepted', '[Class Type: Make Up Class] [Expires: 2026-03-23]');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `schd_id` int(11) NOT NULL,
  `sy_id` int(11) NOT NULL,
  `subj_id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `year_level` int(11) DEFAULT NULL,
  `dept_id` int(11) DEFAULT NULL,
  `sec_id` int(11) NOT NULL,
  `inst_id` int(11) NOT NULL,
  `rm_id` int(11) NOT NULL,
  `schd_type` varchar(20) DEFAULT NULL,
  `schd_term` int(11) NOT NULL,
  `schd_day` enum('Sun','Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL,
  `schd_start` time NOT NULL,
  `schd_end` time NOT NULL,
  `schd_min` int(11) NOT NULL,
  `schd_status` varchar(10) DEFAULT 'Active',
  `is_overtime` enum('No','Yes') NOT NULL DEFAULT 'No'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`schd_id`, `sy_id`, `subj_id`, `program_id`, `year_level`, `dept_id`, `sec_id`, `inst_id`, `rm_id`, `schd_type`, `schd_term`, `schd_day`, `schd_start`, `schd_end`, `schd_min`, `schd_status`, `is_overtime`) VALUES
(145, 8, 160, 30, 3, 1, 48, 24, 18, 'Lab', 2, 'Sat', '07:00:00', '20:00:00', 780, 'Deleted', 'No'),
(146, 8, 160, 30, 3, 1, 48, 24, 18, 'Lec', 2, 'Tue', '06:00:00', '20:00:00', 840, 'Deleted', 'Yes'),
(147, 8, 159, 30, 3, 1, 48, 25, 19, 'Lec', 2, 'Fri', '16:00:00', '20:30:00', 270, 'Deleted', 'No'),
(148, 8, 159, 30, 3, 1, 49, 25, 18, 'Lec', 2, 'Mon', '15:30:00', '20:30:00', 300, 'Deleted', 'No'),
(149, 8, 159, 30, 3, 1, 50, 25, 20, 'Lec', 2, 'Wed', '15:00:00', '20:30:00', 330, 'Deleted', 'No'),
(150, 8, 159, 30, 3, 1, 51, 25, 21, 'Lec', 2, 'Tue', '15:30:00', '20:30:00', 300, 'Deleted', 'No'),
(151, 8, 160, 30, 3, 1, 50, 24, 26, 'Lec', 2, 'Mon', '06:00:00', '20:00:00', 840, 'Deleted', 'No'),
(152, 8, 160, 30, 3, 1, 48, 25, 18, 'Lec', 2, 'Mon', '07:00:00', '09:00:00', 120, 'Deleted', 'No'),
(153, 8, 159, 30, 3, 1, 49, 24, 22, 'Lec', 2, 'Mon', '07:00:00', '17:00:00', 600, 'Deleted', 'Yes'),
(154, 8, 161, 32, 1, 2, 52, 26, 18, 'Lec', 1, 'Tue', '09:00:00', '12:00:00', 180, 'Deleted', 'No'),
(155, 8, 161, 32, 1, 2, 52, 26, 18, 'Lec', 1, 'Mon', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(156, 8, 167, 30, 3, 1, 48, 30, 23, 'Lab', 2, 'Mon', '13:00:00', '16:00:00', 180, 'Deleted', 'No'),
(157, 8, 167, 30, 3, 1, 48, 30, 21, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Deleted', 'No'),
(158, 8, 164, 30, 3, 1, 48, 30, 21, 'Lab', 2, 'Tue', '15:00:00', '17:00:00', 120, 'Deleted', 'No'),
(159, 8, 164, 30, 3, 1, 48, 30, 23, 'Lab', 2, 'Sat', '17:30:00', '20:30:00', 180, 'Deleted', 'Yes'),
(160, 8, 159, 30, 3, 1, 48, 24, 22, 'Lec', 2, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(161, 8, 159, 30, 3, 1, 49, 24, 22, 'Lec', 2, 'Mon', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(162, 8, 159, 30, 3, 1, 50, 24, 22, 'Lec', 2, 'Tue', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(163, 8, 159, 30, 3, 1, 48, 24, 22, 'Lec', 2, 'Wed', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(164, 8, 159, 30, 3, 1, 49, 24, 22, 'Lec', 2, 'Wed', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(165, 8, 159, 30, 3, 1, 50, 24, 22, 'Lec', 2, 'Thu', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(166, 8, 160, 30, 3, 1, 48, 24, 22, 'Lec', 2, 'Tue', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(167, 8, 160, 30, 3, 1, 49, 24, 22, 'Lec', 2, 'Tue', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(168, 8, 160, 30, 3, 1, 50, 24, 22, 'Lec', 2, 'Mon', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(169, 8, 160, 30, 3, 1, 48, 24, 22, 'Lec', 2, 'Thu', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(170, 8, 160, 30, 3, 1, 49, 24, 22, 'Lec', 2, 'Thu', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(171, 8, 160, 30, 3, 1, 50, 24, 22, 'Lec', 2, 'Wed', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(172, 8, 163, 30, 1, 1, 57, 28, 24, 'Lab', 2, 'Mon', '09:00:00', '12:00:00', 180, 'Deleted', 'No'),
(173, 8, 163, 30, 1, 1, 56, 28, 19, 'Lab', 2, 'Tue', '09:00:00', '12:00:00', 180, 'Deleted', 'No'),
(174, 8, 163, 30, 1, 1, 58, 28, 24, 'Lec', 2, 'Wed', '09:00:00', '12:00:00', 180, 'Deleted', 'No'),
(175, 8, 163, 30, 1, 1, 56, 28, 24, 'Lab', 2, 'Mon', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(176, 8, 163, 30, 1, 1, 57, 28, 24, 'Lab', 2, 'Tue', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(177, 8, 163, 30, 1, 1, 58, 28, 24, 'Lab', 2, 'Wed', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(178, 8, 163, 30, 1, 1, 59, 28, 24, 'Lab', 2, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(179, 8, 164, 30, 3, 1, 50, 28, 18, 'Lab', 2, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(180, 8, 164, 30, 3, 1, 50, 28, 22, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'Yes'),
(181, 8, 163, 30, 1, 1, 56, 28, 22, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(182, 8, 163, 30, 1, 1, 57, 28, 22, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(183, 8, 163, 30, 1, 1, 58, 28, 22, 'Lec', 2, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'Yes'),
(184, 8, 163, 30, 1, 1, 59, 28, 21, 'Lec', 2, 'Wed', '17:30:00', '19:30:00', 120, 'Active', 'Yes'),
(185, 8, 165, 30, 3, 1, 48, 29, 24, 'Lab', 2, 'Thu', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(186, 8, 165, 30, 3, 1, 49, 29, 23, 'Lab', 2, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(187, 8, 165, 30, 3, 1, 50, 29, 25, 'Lab', 2, 'Mon', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(188, 8, 166, 30, 3, 1, 48, 29, 24, 'Lab', 2, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(189, 8, 166, 30, 3, 1, 49, 29, 24, 'Lab', 2, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(190, 8, 166, 30, 3, 1, 50, 29, 23, 'Lab', 2, 'Fri', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(191, 8, 164, 30, 3, 1, 48, 29, 24, 'Lec', 2, 'Sat', '17:00:00', '19:00:00', 120, 'Deleted', 'Yes'),
(192, 8, 164, 30, 3, 1, 48, 29, 19, 'Lec', 2, 'Wed', '17:30:00', '19:30:00', 120, 'Deleted', 'Yes'),
(193, 8, 165, 30, 3, 1, 48, 29, 19, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Deleted', 'Yes'),
(194, 8, 166, 30, 3, 1, 49, 29, 24, 'Lec', 2, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'Yes'),
(195, 8, 165, 30, 3, 1, 48, 29, 19, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(196, 8, 165, 30, 3, 1, 49, 29, 24, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(197, 8, 165, 30, 3, 1, 50, 29, 24, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(198, 8, 167, 30, 3, 1, 48, 30, 23, 'Lab', 2, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(199, 8, 167, 30, 3, 1, 49, 30, 23, 'Lab', 2, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(200, 8, 167, 30, 3, 1, 50, 30, 23, 'Lab', 2, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(201, 8, 164, 30, 3, 1, 48, 30, 21, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(202, 8, 167, 30, 3, 1, 48, 30, 21, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'Yes'),
(203, 8, 167, 30, 3, 1, 49, 30, 21, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(204, 8, 167, 30, 3, 1, 50, 30, 21, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(205, 8, 168, 30, 3, 1, 48, 31, 25, 'Lab', 2, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(206, 8, 168, 30, 3, 1, 49, 31, 25, 'Lab', 2, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(207, 8, 168, 30, 3, 1, 50, 31, 25, 'Lab', 2, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(208, 8, 169, 30, 3, 1, 48, 31, 25, 'Lec', 2, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'No'),
(209, 8, 169, 30, 3, 1, 49, 31, 25, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(210, 8, 168, 30, 3, 1, 48, 31, 25, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(211, 8, 168, 30, 3, 1, 49, 31, 25, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(212, 8, 168, 30, 3, 1, 50, 31, 25, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(219, 8, 174, 30, 1, 1, 32, 33, 26, 'Lec', 1, 'Tue', '17:30:00', '19:30:00', 120, 'Deleted', 'No'),
(220, 8, 174, 30, 1, 1, 56, 33, 26, 'Lec', 2, 'Tue', '17:30:00', '19:30:00', 120, 'Active', 'No'),
(221, 8, 174, 30, 1, 1, 57, 33, 26, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(222, 8, 174, 30, 1, 1, 58, 33, 26, 'Lec', 2, 'Thu', '17:00:00', '19:00:00', 120, 'Active', 'No'),
(223, 8, 174, 30, 1, 1, 59, 33, 26, 'Lec', 2, 'Mon', '17:30:00', '19:30:00', 120, 'Active', 'No'),
(224, 8, 173, 30, 2, 1, 44, 33, 26, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(225, 8, 173, 30, 2, 1, 45, 33, 26, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(226, 8, 173, 30, 2, 1, 46, 33, 26, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(227, 8, 176, 30, 2, 1, 44, 34, 28, 'Lec', 2, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(228, 8, 176, 30, 2, 1, 44, 34, 28, 'Lec', 2, 'Thu', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(229, 8, 176, 30, 2, 1, 45, 34, 28, 'Lec', 2, 'Mon', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(230, 8, 176, 30, 2, 1, 45, 34, 28, 'Lec', 2, 'Wed', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(231, 8, 176, 30, 2, 1, 46, 34, 28, 'Lec', 2, 'Tue', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(232, 8, 176, 30, 2, 1, 46, 34, 28, 'Lec', 2, 'Thu', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(233, 8, 180, 30, 1, 1, 56, 34, 28, 'Lec', 2, 'Mon', '19:00:00', '20:30:00', 90, 'Deleted', 'Yes'),
(234, 8, 180, 30, 1, 1, 56, 34, 28, 'Lec', 2, 'Wed', '19:00:00', '20:30:00', 90, 'Deleted', 'Yes'),
(235, 8, 180, 30, 1, 1, 57, 34, 22, 'Lec', 2, 'Tue', '07:00:00', '08:30:00', 90, 'Deleted', 'Yes'),
(236, 8, 180, 30, 1, 1, 57, 34, 22, 'Lec', 2, 'Thu', '07:00:00', '08:30:00', 90, 'Deleted', 'Yes'),
(237, 8, 180, 30, 1, 1, 58, 34, 28, 'Lec', 2, 'Sat', '13:00:00', '16:00:00', 180, 'Deleted', 'Yes'),
(238, 8, 175, 30, 2, 1, 44, 35, 28, 'Lec', 2, 'Mon', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(239, 8, 175, 30, 2, 1, 44, 35, 28, 'Lec', 2, 'Wed', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(240, 8, 175, 30, 2, 1, 45, 35, 28, 'Lec', 2, 'Tue', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(241, 8, 175, 30, 2, 1, 45, 35, 28, 'Lec', 2, 'Thu', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(242, 8, 175, 30, 2, 1, 46, 35, 28, 'Lec', 2, 'Tue', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(243, 8, 175, 30, 2, 1, 46, 35, 28, 'Lec', 2, 'Thu', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(244, 8, 183, 30, 1, 1, 56, 35, 28, 'Lec', 2, 'Mon', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(245, 8, 183, 30, 1, 1, 56, 35, 28, 'Lec', 2, 'Wed', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(246, 8, 183, 30, 1, 1, 57, 35, 28, 'Lec', 2, 'Tue', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(247, 8, 183, 30, 1, 1, 57, 35, 28, 'Lec', 2, 'Thu', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(248, 8, 183, 30, 1, 1, 58, 35, 28, 'Lec', 2, 'Tue', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(249, 8, 183, 30, 1, 1, 58, 35, 28, 'Lec', 2, 'Thu', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(250, 8, 171, 30, 2, 1, 44, 36, 21, 'Lec', 2, 'Thu', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(251, 8, 171, 30, 2, 1, 46, 36, 21, 'Lec', 2, 'Fri', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(252, 8, 171, 30, 2, 1, 46, 36, 25, 'Lab', 2, 'Fri', '14:30:00', '17:30:00', 180, 'Active', 'No'),
(253, 8, 179, 30, 1, 1, 56, 37, 21, 'Lec', 2, 'Thu', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(254, 8, 179, 30, 1, 1, 56, 37, 24, 'Lab', 2, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(255, 8, 179, 30, 1, 1, 57, 37, 21, 'Lec', 2, 'Mon', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(256, 8, 179, 30, 1, 1, 57, 37, 25, 'Lab', 2, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(257, 8, 179, 30, 1, 1, 58, 37, 21, 'Lec', 2, 'Wed', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(258, 8, 179, 30, 1, 1, 58, 37, 25, 'Lab', 2, 'Wed', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(259, 8, 185, 30, 1, 1, 32, 46, 22, 'Lec', 1, 'Mon', '14:30:00', '16:00:00', 90, 'Deleted', 'No'),
(260, 8, 185, 30, 1, 1, 56, 46, 22, 'Lec', 2, 'Mon', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(261, 8, 185, 30, 1, 1, 56, 46, 22, 'Lec', 2, 'Wed', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(262, 8, 185, 30, 1, 1, 57, 46, 22, 'Lec', 2, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(263, 8, 185, 30, 1, 1, 57, 46, 22, 'Lec', 2, 'Thu', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(264, 8, 185, 30, 1, 1, 58, 46, 22, 'Lec', 2, 'Tue', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(265, 8, 185, 30, 1, 1, 58, 46, 22, 'Lec', 2, 'Thu', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(266, 8, 185, 30, 1, 1, 59, 46, 28, 'Lec', 2, 'Mon', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(267, 8, 185, 30, 1, 1, 59, 46, 28, 'Lec', 2, 'Wed', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(268, 8, 172, 30, 2, 1, 44, 46, 22, 'Lec', 2, 'Tue', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(269, 8, 172, 30, 2, 1, 44, 46, 22, 'Lec', 2, 'Thu', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(270, 8, 172, 30, 2, 1, 46, 46, 22, 'Lec', 2, 'Mon', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(271, 8, 172, 30, 2, 1, 46, 46, 22, 'Lec', 2, 'Wed', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(272, 8, 179, 30, 1, 1, 33, 37, 21, 'Lec', 1, 'Mon', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(273, 8, 179, 30, 1, 1, 33, 37, 25, 'Lab', 1, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(274, 8, 180, 30, 1, 1, 33, 34, 22, 'Lec', 1, 'Tue', '07:00:00', '08:30:00', 90, 'Deleted', 'No'),
(275, 8, 163, 30, 1, 1, 33, 28, 24, 'Lab', 1, 'Tue', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(276, 8, 185, 30, 1, 1, 33, 46, 22, 'Lec', 1, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(277, 8, 185, 30, 1, 1, 33, 46, 22, 'Lec', 1, 'Thu', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(278, 8, 174, 30, 1, 1, 33, 33, 26, 'Lec', 1, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(279, 8, 180, 30, 1, 1, 33, 34, 28, 'Lec', 1, 'Tue', '19:00:00', '20:30:00', 90, 'Deleted', 'No'),
(280, 8, 180, 30, 1, 1, 33, 34, 22, 'Lec', 1, 'Tue', '19:00:00', '20:30:00', 90, 'Deleted', 'No'),
(281, 8, 180, 30, 1, 1, 33, 34, 22, 'Lec', 1, 'Thu', '19:00:00', '20:30:00', 90, 'Deleted', 'No'),
(282, 8, 180, 30, 1, 1, 33, 34, 22, 'Lec', 1, 'Tue', '19:00:00', '20:30:00', 90, 'Deleted', 'No'),
(283, 8, 180, 30, 1, 1, 56, 34, 28, 'Lec', 2, 'Mon', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(284, 8, 180, 30, 1, 1, 56, 34, 28, 'Lec', 2, 'Wed', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(285, 8, 180, 30, 1, 1, 33, 34, 22, 'Lec', 1, 'Thu', '19:00:00', '20:30:00', 90, 'Deleted', 'No'),
(286, 8, 180, 30, 1, 1, 34, 34, 28, 'Lec', 1, 'Sat', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(287, 8, 180, 30, 1, 1, 33, 34, 22, 'Lec', 1, 'Tue', '19:00:00', '20:30:00', 90, 'Active', 'No'),
(288, 8, 181, 30, 1, 1, 56, 39, 23, 'Lec', 2, 'Thu', '18:30:00', '20:00:00', 90, 'Active', 'No'),
(289, 8, 181, 30, 1, 1, 57, 39, 23, 'Lec', 2, 'Wed', '18:30:00', '20:00:00', 90, 'Active', 'No'),
(290, 8, 181, 30, 1, 1, 58, 39, 29, 'Lec', 2, 'Mon', '17:00:00', '18:30:00', 90, 'Active', 'No'),
(291, 8, 181, 30, 1, 1, 58, 39, 29, 'Lec', 2, 'Wed', '17:00:00', '18:30:00', 90, 'Active', 'No'),
(292, 8, 186, 30, 1, 1, 56, 40, 23, 'Lec', 2, 'Thu', '17:30:00', '18:30:00', 60, 'Active', 'No'),
(293, 8, 186, 30, 1, 1, 58, 40, 23, 'Lec', 2, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(294, 8, 179, 30, 1, 1, 59, 41, 25, 'Lab', 2, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(295, 8, 164, 30, 3, 1, 49, 41, 25, 'Lec', 2, 'Tue', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(296, 8, 188, 30, 2, 1, 44, 44, 24, 'Lec', 2, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(297, 8, 188, 30, 2, 1, 45, 44, 28, 'Lec', 2, 'Sat', '16:30:00', '19:30:00', 180, 'Active', 'No'),
(298, 8, 188, 30, 2, 1, 46, 44, 21, 'Lec', 2, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(299, 8, 180, 30, 1, 1, 59, 43, 21, 'Lec', 2, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(300, 8, 180, 30, 1, 1, 59, 43, 21, 'Lec', 2, 'Wed', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(301, 8, 183, 30, 1, 1, 59, 43, 28, 'Lec', 2, 'Wed', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(302, 8, 183, 30, 1, 1, 59, 43, 28, 'Lec', 2, 'Fri', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(303, 8, 186, 30, 1, 1, 59, 43, 28, 'Lec', 2, 'Tue', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(304, 8, 186, 30, 1, 1, 59, 43, 28, 'Lec', 2, 'Thu', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(305, 8, 178, 30, 2, 1, 44, 45, 21, 'Lec', 2, 'Wed', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(306, 8, 178, 30, 2, 1, 44, 45, 24, 'Lab', 2, 'Wed', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(307, 8, 178, 30, 2, 1, 45, 45, 21, 'Lec', 2, 'Tue', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(308, 8, 178, 30, 2, 1, 45, 45, 24, 'Lab', 2, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(309, 8, 178, 30, 2, 1, 46, 45, 21, 'Lec', 2, 'Thu', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(310, 8, 178, 30, 2, 1, 46, 45, 24, 'Lab', 2, 'Thu', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(311, 8, 172, 30, 2, 1, 45, 46, 28, 'Lec', 2, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(312, 8, 187, 30, 1, 1, 32, 33, 26, 'Lec', 1, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(313, 9, 159, NULL, NULL, NULL, 48, 24, 22, 'Lec', 1, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(314, 9, 159, NULL, NULL, NULL, 49, 24, 22, 'Lec', 1, 'Mon', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(315, 9, 159, NULL, NULL, NULL, 50, 24, 22, 'Lec', 1, 'Tue', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(316, 9, 159, NULL, NULL, NULL, 48, 24, 22, 'Lec', 1, 'Wed', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(317, 9, 159, NULL, NULL, NULL, 49, 24, 22, 'Lec', 1, 'Wed', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(318, 9, 159, NULL, NULL, NULL, 50, 24, 22, 'Lec', 1, 'Thu', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(319, 9, 160, NULL, NULL, NULL, 48, 24, 22, 'Lec', 1, 'Tue', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(320, 9, 160, NULL, NULL, NULL, 49, 24, 22, 'Lec', 1, 'Tue', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(321, 9, 160, NULL, NULL, NULL, 50, 24, 22, 'Lec', 1, 'Mon', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(322, 9, 160, NULL, NULL, NULL, 48, 24, 22, 'Lec', 1, 'Thu', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(323, 9, 160, NULL, NULL, NULL, 49, 24, 22, 'Lec', 1, 'Thu', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(324, 9, 160, NULL, NULL, NULL, 50, 24, 22, 'Lec', 1, 'Wed', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(325, 9, 163, NULL, NULL, NULL, 56, 28, 24, 'Lab', 1, 'Mon', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(326, 9, 163, NULL, NULL, NULL, 57, 28, 24, 'Lab', 1, 'Tue', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(327, 9, 163, NULL, NULL, NULL, 58, 28, 24, 'Lab', 1, 'Wed', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(328, 9, 163, NULL, NULL, NULL, 59, 28, 24, 'Lab', 1, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(329, 9, 164, NULL, NULL, NULL, 50, 28, 18, 'Lab', 1, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(330, 9, 164, NULL, NULL, NULL, 50, 28, 22, 'Lec', 1, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'Yes'),
(331, 9, 163, NULL, NULL, NULL, 56, 28, 22, 'Lec', 1, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(332, 9, 163, NULL, NULL, NULL, 57, 28, 22, 'Lec', 1, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(333, 9, 163, NULL, NULL, NULL, 58, 28, 22, 'Lec', 1, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'Yes'),
(334, 9, 163, NULL, NULL, NULL, 59, 28, 21, 'Lec', 1, 'Wed', '17:30:00', '19:30:00', 120, 'Active', 'Yes'),
(335, 9, 165, NULL, NULL, NULL, 48, 29, 24, 'Lab', 1, 'Thu', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(336, 9, 165, NULL, NULL, NULL, 49, 29, 23, 'Lab', 1, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(337, 9, 165, NULL, NULL, NULL, 50, 29, 25, 'Lab', 1, 'Mon', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(338, 9, 166, NULL, NULL, NULL, 48, 29, 24, 'Lab', 1, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(339, 9, 166, NULL, NULL, NULL, 49, 29, 24, 'Lab', 1, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(340, 9, 166, NULL, NULL, NULL, 50, 29, 23, 'Lab', 1, 'Fri', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(341, 9, 166, NULL, NULL, NULL, 49, 29, 24, 'Lec', 1, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'Yes'),
(342, 9, 165, NULL, NULL, NULL, 48, 29, 19, 'Lec', 1, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(343, 9, 165, NULL, NULL, NULL, 49, 29, 24, 'Lec', 1, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(344, 9, 165, NULL, NULL, NULL, 50, 29, 24, 'Lec', 1, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(345, 9, 167, NULL, NULL, NULL, 48, 30, 23, 'Lab', 1, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(346, 9, 167, NULL, NULL, NULL, 49, 30, 23, 'Lab', 1, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(347, 9, 167, NULL, NULL, NULL, 50, 30, 23, 'Lab', 1, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(348, 9, 164, NULL, NULL, NULL, 48, 30, 21, 'Lec', 1, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(349, 9, 167, NULL, NULL, NULL, 48, 30, 21, 'Lec', 1, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'Yes'),
(350, 9, 167, NULL, NULL, NULL, 49, 30, 21, 'Lec', 1, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(351, 9, 167, NULL, NULL, NULL, 50, 30, 21, 'Lec', 1, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(352, 9, 168, NULL, NULL, NULL, 48, 31, 25, 'Lab', 1, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(353, 9, 168, NULL, NULL, NULL, 49, 31, 25, 'Lab', 1, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(354, 9, 168, NULL, NULL, NULL, 50, 31, 25, 'Lab', 1, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(355, 9, 169, NULL, NULL, NULL, 48, 31, 25, 'Lec', 1, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'No'),
(356, 9, 169, NULL, NULL, NULL, 49, 31, 25, 'Lec', 1, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(357, 9, 168, NULL, NULL, NULL, 48, 31, 25, 'Lec', 1, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(358, 9, 168, NULL, NULL, NULL, 49, 31, 25, 'Lec', 1, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(359, 9, 168, NULL, NULL, NULL, 50, 31, 25, 'Lec', 1, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(360, 9, 174, NULL, NULL, NULL, 56, 33, 26, 'Lec', 1, 'Tue', '17:30:00', '19:30:00', 120, 'Active', 'No'),
(361, 9, 174, NULL, NULL, NULL, 57, 33, 26, 'Lec', 1, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(362, 9, 174, NULL, NULL, NULL, 58, 33, 26, 'Lec', 1, 'Thu', '17:00:00', '19:00:00', 120, 'Active', 'No'),
(363, 9, 174, NULL, NULL, NULL, 59, 33, 26, 'Lec', 1, 'Mon', '17:30:00', '19:30:00', 120, 'Active', 'No'),
(364, 9, 173, NULL, NULL, NULL, 44, 33, 26, 'Lec', 1, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(365, 9, 173, NULL, NULL, NULL, 45, 33, 26, 'Lec', 1, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(366, 9, 173, NULL, NULL, NULL, 46, 33, 26, 'Lec', 1, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(367, 9, 176, NULL, NULL, NULL, 44, 34, 28, 'Lec', 1, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(368, 9, 176, NULL, NULL, NULL, 44, 34, 28, 'Lec', 1, 'Thu', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(369, 9, 176, NULL, NULL, NULL, 45, 34, 28, 'Lec', 1, 'Mon', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(370, 9, 176, NULL, NULL, NULL, 45, 34, 28, 'Lec', 1, 'Wed', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(371, 9, 176, NULL, NULL, NULL, 46, 34, 28, 'Lec', 1, 'Tue', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(372, 9, 176, NULL, NULL, NULL, 46, 34, 28, 'Lec', 1, 'Thu', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(373, 9, 175, NULL, NULL, NULL, 44, 35, 28, 'Lec', 1, 'Mon', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(374, 9, 175, NULL, NULL, NULL, 44, 35, 28, 'Lec', 1, 'Wed', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(375, 9, 175, NULL, NULL, NULL, 45, 35, 28, 'Lec', 1, 'Tue', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(376, 9, 175, NULL, NULL, NULL, 45, 35, 28, 'Lec', 1, 'Thu', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(377, 9, 175, NULL, NULL, NULL, 46, 35, 28, 'Lec', 1, 'Tue', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(378, 9, 175, NULL, NULL, NULL, 46, 35, 28, 'Lec', 1, 'Thu', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(379, 9, 183, NULL, NULL, NULL, 56, 35, 28, 'Lec', 1, 'Mon', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(380, 9, 183, NULL, NULL, NULL, 56, 35, 28, 'Lec', 1, 'Wed', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(381, 9, 183, NULL, NULL, NULL, 57, 35, 28, 'Lec', 1, 'Tue', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(382, 9, 183, NULL, NULL, NULL, 57, 35, 28, 'Lec', 1, 'Thu', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(383, 9, 183, NULL, NULL, NULL, 58, 35, 28, 'Lec', 1, 'Tue', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(384, 9, 183, NULL, NULL, NULL, 58, 35, 28, 'Lec', 1, 'Thu', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(385, 9, 171, NULL, NULL, NULL, 44, 36, 21, 'Lec', 1, 'Thu', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(386, 9, 171, NULL, NULL, NULL, 46, 36, 21, 'Lec', 1, 'Fri', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(387, 9, 171, NULL, NULL, NULL, 46, 36, 25, 'Lab', 1, 'Fri', '14:30:00', '17:30:00', 180, 'Active', 'No'),
(388, 9, 179, NULL, NULL, NULL, 56, 37, 21, 'Lec', 1, 'Thu', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(389, 9, 179, NULL, NULL, NULL, 56, 37, 24, 'Lab', 1, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(390, 9, 179, NULL, NULL, NULL, 57, 37, 21, 'Lec', 1, 'Mon', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(391, 9, 179, NULL, NULL, NULL, 57, 37, 25, 'Lab', 1, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(392, 9, 179, NULL, NULL, NULL, 58, 37, 21, 'Lec', 1, 'Wed', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(393, 9, 179, NULL, NULL, NULL, 58, 37, 25, 'Lab', 1, 'Wed', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(394, 9, 185, NULL, NULL, NULL, 56, 46, 22, 'Lec', 1, 'Mon', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(395, 9, 185, NULL, NULL, NULL, 56, 46, 22, 'Lec', 1, 'Wed', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(396, 9, 185, NULL, NULL, NULL, 57, 46, 22, 'Lec', 1, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(397, 9, 185, NULL, NULL, NULL, 57, 46, 22, 'Lec', 1, 'Thu', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(398, 9, 185, NULL, NULL, NULL, 58, 46, 22, 'Lec', 1, 'Tue', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(399, 9, 185, NULL, NULL, NULL, 58, 46, 22, 'Lec', 1, 'Thu', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(400, 9, 185, NULL, NULL, NULL, 59, 46, 28, 'Lec', 1, 'Mon', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(401, 9, 185, NULL, NULL, NULL, 59, 46, 28, 'Lec', 1, 'Wed', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(402, 9, 172, NULL, NULL, NULL, 44, 46, 22, 'Lec', 1, 'Tue', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(403, 9, 172, NULL, NULL, NULL, 44, 46, 22, 'Lec', 1, 'Thu', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(404, 9, 172, NULL, NULL, NULL, 46, 46, 22, 'Lec', 1, 'Mon', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(405, 9, 172, NULL, NULL, NULL, 46, 46, 22, 'Lec', 1, 'Wed', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(406, 9, 180, NULL, NULL, NULL, 56, 34, 28, 'Lec', 1, 'Mon', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(407, 9, 180, NULL, NULL, NULL, 56, 34, 28, 'Lec', 1, 'Wed', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(408, 9, 181, NULL, NULL, NULL, 56, 39, 23, 'Lec', 1, 'Thu', '18:30:00', '20:00:00', 90, 'Active', 'No'),
(409, 9, 181, NULL, NULL, NULL, 57, 39, 23, 'Lec', 1, 'Wed', '18:30:00', '20:00:00', 90, 'Active', 'No'),
(410, 9, 181, NULL, NULL, NULL, 58, 39, 29, 'Lec', 1, 'Mon', '17:00:00', '18:30:00', 90, 'Active', 'No'),
(411, 9, 181, NULL, NULL, NULL, 58, 39, 29, 'Lec', 1, 'Wed', '17:00:00', '18:30:00', 90, 'Active', 'No'),
(412, 9, 186, NULL, NULL, NULL, 56, 40, 23, 'Lec', 1, 'Thu', '17:30:00', '18:30:00', 60, 'Active', 'No'),
(413, 9, 186, NULL, NULL, NULL, 58, 40, 23, 'Lec', 1, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(414, 9, 179, NULL, NULL, NULL, 59, 41, 25, 'Lab', 1, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(415, 9, 164, NULL, NULL, NULL, 49, 41, 25, 'Lec', 1, 'Tue', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(416, 9, 188, NULL, NULL, NULL, 44, 44, 24, 'Lec', 1, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(417, 9, 188, NULL, NULL, NULL, 45, 44, 28, 'Lec', 1, 'Sat', '16:30:00', '19:30:00', 180, 'Active', 'No'),
(418, 9, 188, NULL, NULL, NULL, 46, 44, 21, 'Lec', 1, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(419, 9, 180, NULL, NULL, NULL, 59, 43, 21, 'Lec', 1, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(420, 9, 180, NULL, NULL, NULL, 59, 43, 21, 'Lec', 1, 'Wed', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(421, 9, 183, NULL, NULL, NULL, 59, 43, 28, 'Lec', 1, 'Wed', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(422, 9, 183, NULL, NULL, NULL, 59, 43, 28, 'Lec', 1, 'Fri', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(423, 9, 186, NULL, NULL, NULL, 59, 43, 28, 'Lec', 1, 'Tue', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(424, 9, 186, NULL, NULL, NULL, 59, 43, 28, 'Lec', 1, 'Thu', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(425, 9, 178, NULL, NULL, NULL, 44, 45, 21, 'Lec', 1, 'Wed', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(426, 9, 178, NULL, NULL, NULL, 44, 45, 24, 'Lab', 1, 'Wed', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(427, 9, 178, NULL, NULL, NULL, 45, 45, 21, 'Lec', 1, 'Tue', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(428, 9, 178, NULL, NULL, NULL, 45, 45, 24, 'Lab', 1, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(429, 9, 178, NULL, NULL, NULL, 46, 45, 21, 'Lec', 1, 'Thu', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(430, 9, 178, NULL, NULL, NULL, 46, 45, 24, 'Lab', 1, 'Thu', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(431, 9, 172, NULL, NULL, NULL, 45, 46, 28, 'Lec', 1, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(432, 9, 159, NULL, NULL, NULL, 48, 24, 22, 'Lec', 2, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(433, 9, 159, NULL, NULL, NULL, 49, 24, 22, 'Lec', 2, 'Mon', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(434, 9, 159, NULL, NULL, NULL, 50, 24, 22, 'Lec', 2, 'Tue', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(435, 9, 159, NULL, NULL, NULL, 48, 24, 22, 'Lec', 2, 'Wed', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(436, 9, 159, NULL, NULL, NULL, 49, 24, 22, 'Lec', 2, 'Wed', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(437, 9, 159, NULL, NULL, NULL, 50, 24, 22, 'Lec', 2, 'Thu', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(438, 9, 160, NULL, NULL, NULL, 48, 24, 22, 'Lec', 2, 'Tue', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(439, 9, 160, NULL, NULL, NULL, 49, 24, 22, 'Lec', 2, 'Tue', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(440, 9, 160, NULL, NULL, NULL, 50, 24, 22, 'Lec', 2, 'Mon', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(441, 9, 160, NULL, NULL, NULL, 48, 24, 22, 'Lec', 2, 'Thu', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(442, 9, 160, NULL, NULL, NULL, 49, 24, 22, 'Lec', 2, 'Thu', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(443, 9, 160, NULL, NULL, NULL, 50, 24, 22, 'Lec', 2, 'Wed', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(444, 9, 163, NULL, NULL, NULL, 56, 28, 24, 'Lab', 2, 'Mon', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(445, 9, 163, NULL, NULL, NULL, 57, 28, 24, 'Lab', 2, 'Tue', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(446, 9, 163, NULL, NULL, NULL, 58, 28, 24, 'Lab', 2, 'Wed', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(447, 9, 163, NULL, NULL, NULL, 59, 28, 24, 'Lab', 2, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(448, 9, 164, NULL, NULL, NULL, 50, 28, 18, 'Lab', 2, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(449, 9, 164, NULL, NULL, NULL, 50, 28, 22, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'Yes'),
(450, 9, 163, NULL, NULL, NULL, 56, 28, 22, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(451, 9, 163, NULL, NULL, NULL, 57, 28, 22, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(452, 9, 163, NULL, NULL, NULL, 58, 28, 22, 'Lec', 2, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'Yes'),
(453, 9, 163, NULL, NULL, NULL, 59, 28, 21, 'Lec', 2, 'Wed', '17:30:00', '19:30:00', 120, 'Active', 'Yes'),
(454, 9, 165, NULL, NULL, NULL, 48, 29, 24, 'Lab', 2, 'Thu', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(455, 9, 165, NULL, NULL, NULL, 49, 29, 23, 'Lab', 2, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(456, 9, 165, NULL, NULL, NULL, 50, 29, 25, 'Lab', 2, 'Mon', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(457, 9, 166, NULL, NULL, NULL, 48, 29, 24, 'Lab', 2, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(458, 9, 166, NULL, NULL, NULL, 49, 29, 24, 'Lab', 2, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(459, 9, 166, NULL, NULL, NULL, 50, 29, 23, 'Lab', 2, 'Fri', '09:00:00', '12:00:00', 180, 'Active', 'No'),
(460, 9, 166, NULL, NULL, NULL, 49, 29, 24, 'Lec', 2, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'Yes'),
(461, 9, 165, NULL, NULL, NULL, 48, 29, 19, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(462, 9, 165, NULL, NULL, NULL, 49, 29, 24, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(463, 9, 165, NULL, NULL, NULL, 50, 29, 24, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(464, 9, 167, NULL, NULL, NULL, 48, 30, 23, 'Lab', 2, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(465, 9, 167, NULL, NULL, NULL, 49, 30, 23, 'Lab', 2, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(466, 9, 167, NULL, NULL, NULL, 50, 30, 23, 'Lab', 2, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(467, 9, 164, NULL, NULL, NULL, 48, 30, 21, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'Yes'),
(468, 9, 167, NULL, NULL, NULL, 48, 30, 21, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'Yes'),
(469, 9, 167, NULL, NULL, NULL, 49, 30, 21, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(470, 9, 167, NULL, NULL, NULL, 50, 30, 21, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'Yes'),
(471, 9, 168, NULL, NULL, NULL, 48, 31, 25, 'Lab', 2, 'Tue', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(472, 9, 168, NULL, NULL, NULL, 49, 31, 25, 'Lab', 2, 'Mon', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(473, 9, 168, NULL, NULL, NULL, 50, 31, 25, 'Lab', 2, 'Wed', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(474, 9, 169, NULL, NULL, NULL, 48, 31, 25, 'Lec', 2, 'Sat', '17:00:00', '19:00:00', 120, 'Active', 'No'),
(475, 9, 169, NULL, NULL, NULL, 49, 31, 25, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(476, 9, 168, NULL, NULL, NULL, 48, 31, 25, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(477, 9, 168, NULL, NULL, NULL, 49, 31, 25, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(478, 9, 168, NULL, NULL, NULL, 50, 31, 25, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'Yes'),
(479, 9, 174, NULL, NULL, NULL, 56, 33, 26, 'Lec', 2, 'Tue', '17:30:00', '19:30:00', 120, 'Active', 'No'),
(480, 9, 174, NULL, NULL, NULL, 57, 33, 26, 'Lec', 2, 'Sat', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(481, 9, 174, NULL, NULL, NULL, 58, 33, 26, 'Lec', 2, 'Thu', '17:00:00', '19:00:00', 120, 'Active', 'No'),
(482, 9, 174, NULL, NULL, NULL, 59, 33, 26, 'Lec', 2, 'Mon', '17:30:00', '19:30:00', 120, 'Active', 'No'),
(483, 9, 173, NULL, NULL, NULL, 44, 33, 26, 'Lec', 2, 'Sat', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(484, 9, 173, NULL, NULL, NULL, 45, 33, 26, 'Lec', 2, 'Sat', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(485, 9, 173, NULL, NULL, NULL, 46, 33, 26, 'Lec', 2, 'Sat', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(486, 9, 176, NULL, NULL, NULL, 44, 34, 28, 'Lec', 2, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(487, 9, 176, NULL, NULL, NULL, 44, 34, 28, 'Lec', 2, 'Thu', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(488, 9, 176, NULL, NULL, NULL, 45, 34, 28, 'Lec', 2, 'Mon', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(489, 9, 176, NULL, NULL, NULL, 45, 34, 28, 'Lec', 2, 'Wed', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(490, 9, 176, NULL, NULL, NULL, 46, 34, 28, 'Lec', 2, 'Tue', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(491, 9, 176, NULL, NULL, NULL, 46, 34, 28, 'Lec', 2, 'Thu', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(492, 9, 175, NULL, NULL, NULL, 44, 35, 28, 'Lec', 2, 'Mon', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(493, 9, 175, NULL, NULL, NULL, 44, 35, 28, 'Lec', 2, 'Wed', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(494, 9, 175, NULL, NULL, NULL, 45, 35, 28, 'Lec', 2, 'Tue', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(495, 9, 175, NULL, NULL, NULL, 45, 35, 28, 'Lec', 2, 'Thu', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(496, 9, 175, NULL, NULL, NULL, 46, 35, 28, 'Lec', 2, 'Tue', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(497, 9, 175, NULL, NULL, NULL, 46, 35, 28, 'Lec', 2, 'Thu', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(498, 9, 183, NULL, NULL, NULL, 56, 35, 28, 'Lec', 2, 'Mon', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(499, 9, 183, NULL, NULL, NULL, 56, 35, 28, 'Lec', 2, 'Wed', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(500, 9, 183, NULL, NULL, NULL, 57, 35, 28, 'Lec', 2, 'Tue', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(501, 9, 183, NULL, NULL, NULL, 57, 35, 28, 'Lec', 2, 'Thu', '17:30:00', '19:00:00', 90, 'Active', 'Yes'),
(502, 9, 183, NULL, NULL, NULL, 58, 35, 28, 'Lec', 2, 'Tue', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(503, 9, 183, NULL, NULL, NULL, 58, 35, 28, 'Lec', 2, 'Thu', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(504, 9, 171, NULL, NULL, NULL, 44, 36, 21, 'Lec', 2, 'Thu', '08:00:00', '10:00:00', 120, 'Active', 'No'),
(505, 9, 171, NULL, NULL, NULL, 46, 36, 21, 'Lec', 2, 'Fri', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(506, 9, 171, NULL, NULL, NULL, 46, 36, 25, 'Lab', 2, 'Fri', '14:30:00', '17:30:00', 180, 'Active', 'No'),
(507, 9, 179, NULL, NULL, NULL, 56, 37, 21, 'Lec', 2, 'Thu', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(508, 9, 179, NULL, NULL, NULL, 56, 37, 24, 'Lab', 2, 'Thu', '13:00:00', '16:00:00', 180, 'Active', 'No'),
(509, 9, 179, NULL, NULL, NULL, 57, 37, 21, 'Lec', 2, 'Mon', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(510, 9, 179, NULL, NULL, NULL, 57, 37, 25, 'Lab', 2, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(511, 9, 179, NULL, NULL, NULL, 58, 37, 21, 'Lec', 2, 'Wed', '13:00:00', '15:00:00', 120, 'Active', 'No'),
(512, 9, 179, NULL, NULL, NULL, 58, 37, 25, 'Lab', 2, 'Wed', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(513, 9, 185, NULL, NULL, NULL, 56, 46, 22, 'Lec', 2, 'Mon', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(514, 9, 185, NULL, NULL, NULL, 56, 46, 22, 'Lec', 2, 'Wed', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(515, 9, 185, NULL, NULL, NULL, 57, 46, 22, 'Lec', 2, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(516, 9, 185, NULL, NULL, NULL, 57, 46, 22, 'Lec', 2, 'Thu', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(517, 9, 185, NULL, NULL, NULL, 58, 46, 22, 'Lec', 2, 'Tue', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(518, 9, 185, NULL, NULL, NULL, 58, 46, 22, 'Lec', 2, 'Thu', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(519, 9, 185, NULL, NULL, NULL, 59, 46, 28, 'Lec', 2, 'Mon', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(520, 9, 185, NULL, NULL, NULL, 59, 46, 28, 'Lec', 2, 'Wed', '10:30:00', '12:00:00', 90, 'Active', 'No'),
(521, 9, 172, NULL, NULL, NULL, 44, 46, 22, 'Lec', 2, 'Tue', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(522, 9, 172, NULL, NULL, NULL, 44, 46, 22, 'Lec', 2, 'Thu', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(523, 9, 172, NULL, NULL, NULL, 46, 46, 22, 'Lec', 2, 'Mon', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(524, 9, 172, NULL, NULL, NULL, 46, 46, 22, 'Lec', 2, 'Wed', '13:00:00', '14:30:00', 90, 'Active', 'No'),
(525, 9, 180, NULL, NULL, NULL, 56, 34, 28, 'Lec', 2, 'Mon', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(526, 9, 180, NULL, NULL, NULL, 56, 34, 28, 'Lec', 2, 'Wed', '19:00:00', '20:30:00', 90, 'Active', 'Yes'),
(527, 9, 181, NULL, NULL, NULL, 56, 39, 23, 'Lec', 2, 'Thu', '18:30:00', '20:00:00', 90, 'Active', 'No'),
(528, 9, 181, NULL, NULL, NULL, 57, 39, 23, 'Lec', 2, 'Wed', '18:30:00', '20:00:00', 90, 'Active', 'No'),
(529, 9, 181, NULL, NULL, NULL, 58, 39, 29, 'Lec', 2, 'Mon', '17:00:00', '18:30:00', 90, 'Active', 'No'),
(530, 9, 181, NULL, NULL, NULL, 58, 39, 29, 'Lec', 2, 'Wed', '17:00:00', '18:30:00', 90, 'Active', 'No'),
(531, 9, 186, NULL, NULL, NULL, 56, 40, 23, 'Lec', 2, 'Thu', '17:30:00', '18:30:00', 60, 'Active', 'No'),
(532, 9, 186, NULL, NULL, NULL, 58, 40, 23, 'Lec', 2, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(533, 9, 179, NULL, NULL, NULL, 59, 41, 25, 'Lab', 2, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(534, 9, 164, NULL, NULL, NULL, 49, 41, 25, 'Lec', 2, 'Tue', '10:00:00', '12:00:00', 120, 'Active', 'No'),
(535, 9, 188, NULL, NULL, NULL, 44, 44, 24, 'Lec', 2, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(536, 9, 188, NULL, NULL, NULL, 45, 44, 28, 'Lec', 2, 'Sat', '16:30:00', '19:30:00', 180, 'Active', 'No'),
(537, 9, 188, NULL, NULL, NULL, 46, 44, 21, 'Lec', 2, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(538, 9, 180, NULL, NULL, NULL, 59, 43, 21, 'Lec', 2, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(539, 9, 180, NULL, NULL, NULL, 59, 43, 21, 'Lec', 2, 'Wed', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(540, 9, 183, NULL, NULL, NULL, 59, 43, 28, 'Lec', 2, 'Wed', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(541, 9, 183, NULL, NULL, NULL, 59, 43, 28, 'Lec', 2, 'Fri', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(542, 9, 186, NULL, NULL, NULL, 59, 43, 28, 'Lec', 2, 'Tue', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(543, 9, 186, NULL, NULL, NULL, 59, 43, 28, 'Lec', 2, 'Thu', '07:30:00', '09:00:00', 90, 'Active', 'No'),
(544, 9, 178, NULL, NULL, NULL, 44, 45, 21, 'Lec', 2, 'Wed', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(545, 9, 178, NULL, NULL, NULL, 44, 45, 24, 'Lab', 2, 'Wed', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(546, 9, 178, NULL, NULL, NULL, 45, 45, 21, 'Lec', 2, 'Tue', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(547, 9, 178, NULL, NULL, NULL, 45, 45, 24, 'Lab', 2, 'Tue', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(548, 9, 178, NULL, NULL, NULL, 46, 45, 21, 'Lec', 2, 'Thu', '15:00:00', '17:00:00', 120, 'Active', 'No'),
(549, 9, 178, NULL, NULL, NULL, 46, 45, 24, 'Lab', 2, 'Thu', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(550, 9, 172, NULL, NULL, NULL, 45, 46, 28, 'Lec', 2, 'Mon', '09:00:00', '10:30:00', 90, 'Active', 'No'),
(562, 11, 188, 30, 2, 1, 111, 44, 24, 'Lec', 2, 'Mon', '17:30:00', '20:30:00', 180, 'Active', 'No'),
(563, 11, 185, 30, 1, 1, 117, 46, 22, 'Lec', 2, 'Mon', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(564, 11, 185, 30, 1, 1, 118, 46, 22, 'Lec', 2, 'Tue', '14:30:00', '16:00:00', 90, 'Active', 'No'),
(566, 9, 219, 33, 1, 3, 92, 72, 30, 'Lec', 1, 'Tue', '08:00:00', '09:30:00', 90, 'Active', 'No'),
(567, 9, 280, 30, 1, 1, 36, 40, 31, 'Lec', 1, 'Mon', '07:00:00', '10:00:00', 180, 'Active', 'No'),
(568, 8, 183, 30, 1, 1, 32, 34, 21, 'Lec', 1, 'Tue', '08:00:00', '09:30:00', 90, 'Active', 'No'),
(569, 8, 250, 33, 1, 3, 120, 33, 30, 'Lec', 2, 'Tue', '07:00:00', '09:00:00', 120, 'Active', 'No');

-- --------------------------------------------------------

--
-- Table structure for table `schoolyear`
--

CREATE TABLE `schoolyear` (
  `sy_id` int(11) NOT NULL,
  `curr_def` int(11) NOT NULL,
  `sy_year` varchar(12) NOT NULL,
  `sy_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schoolyear`
--

INSERT INTO `schoolyear` (`sy_id`, `curr_def`, `sy_year`, `sy_name`) VALUES
(8, 1, '2025 - 2026', '2025 - 2026 - 2nd Semester'),
(9, 1, '2025 - 2026', '2025 - 2026 - 1st Semester'),
(10, 1, '2026 - 2027', '2026 - 2027 - 1st Semester'),
(11, 1, '2026 - 2027', '2026 - 2027 - 2nd Semester');

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `sec_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `program_id` int(11) DEFAULT NULL,
  `sec_num` int(11) NOT NULL,
  `sec_name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section`
--

INSERT INTO `section` (`sec_id`, `class_id`, `program_id`, `sec_num`, `sec_name`) VALUES
(32, 10, 30, 1, 'BSIT 1-A'),
(33, 10, 30, 2, 'BSIT 1-B'),
(34, 10, 30, 3, 'BSIT 1-C'),
(35, 10, 30, 4, 'BSIT 1-D'),
(36, 11, 30, 1, 'BSIT 1-A'),
(37, 11, 30, 2, 'BSIT 1-B'),
(38, 11, 30, 3, 'BSIT 1-C'),
(39, 11, 30, 4, 'BSIT 1-D'),
(40, 12, 30, 1, 'BSIT 3-A'),
(41, 12, 30, 2, 'BSIT 3-B'),
(42, 12, 30, 3, 'BSIT 3-C'),
(43, 12, 30, 4, 'BSIT 3-D'),
(44, 13, 30, 1, 'BSIT 2-A'),
(45, 13, 30, 2, 'BSIT 2-B'),
(46, 13, 30, 3, 'BSIT 2-C'),
(47, 13, 30, 4, 'BSIT 2-D'),
(48, 14, 30, 1, 'BSIT 3-A'),
(49, 14, 30, 2, 'BSIT 3-B'),
(50, 14, 30, 3, 'BSIT 3-C'),
(51, 14, 30, 4, 'BSIT 3-D'),
(52, 15, 32, 1, 'BindTech 1-A'),
(53, 15, 32, 2, 'BindTech 1-B'),
(54, 15, 32, 3, 'BindTech 1-C'),
(55, 15, 32, 4, 'BindTech 1-D'),
(56, 16, 30, 1, 'BSIT 1-A'),
(57, 16, 30, 2, 'BSIT 1-B'),
(58, 16, 30, 3, 'BSIT 1-C'),
(59, 16, 30, 4, 'BSIT 1-D'),
(60, 17, 32, 1, 'BindTech 2-A'),
(61, 17, 32, 2, 'BindTech 2-B'),
(62, 17, 32, 3, 'BindTech 2-C'),
(63, 18, 32, 1, 'BindTech 3-A'),
(64, 18, 32, 2, 'BindTech 3-B'),
(65, 18, 32, 3, 'BindTech 3-C'),
(66, 18, 32, 4, 'BindTech 3-D'),
(67, 19, 34, 1, 'BPEd 1-A'),
(68, 19, 34, 2, 'BPEd 1-B'),
(69, 19, 34, 3, 'BPEd 1-C'),
(70, 20, 32, 1, 'BindTech 4-A'),
(71, 20, 32, 2, 'BindTech 4-B'),
(72, 20, 32, 3, 'BindTech 4-C'),
(73, 20, 32, 4, 'BindTech 4-D'),
(74, 21, 34, 1, 'BPEd 1-A'),
(75, 21, 34, 2, 'BPEd 1-B'),
(76, 21, 34, 3, 'BPEd 1-C'),
(77, 21, 34, 4, 'BPEd 1-D'),
(78, 22, 36, 1, 'BIT 1-A'),
(79, 22, 36, 2, 'BIT 1-B'),
(80, 22, 36, 3, 'BIT 1-C'),
(86, 24, 31, 1, 'BSCS 1-A'),
(87, 24, 31, 2, 'BSCS 1-B'),
(88, 24, 31, 3, 'BSCS 1-C'),
(90, 26, 30, 1, 'BSIT 1-A'),
(91, 27, 30, 5, 'BSIT 1-E'),
(92, 28, 33, 1, 'BEEd 1-A'),
(93, 28, 33, 2, 'BEEd 1-B'),
(94, 28, 33, 3, 'BEEd 1-C'),
(95, 29, 33, 1, 'BEEd 2-A'),
(96, 29, 33, 2, 'BEEd 2-B'),
(97, 29, 33, 3, 'BEEd 2-C'),
(98, 30, 33, 1, 'BEEd 3-A'),
(99, 30, 33, 2, 'BEEd 3-B'),
(100, 31, 33, 1, 'BEEd 4-A'),
(101, 31, 33, 2, 'BEEd 4-B'),
(102, 32, 34, 1, 'BPEd 1-A'),
(103, 32, 34, 2, 'BPEd 1-B'),
(104, 32, 34, 3, 'BPEd 1-C'),
(105, 27, 31, 1, 'BSCS 1-A'),
(106, 27, 31, 2, 'BSCS 1-B'),
(107, 27, 31, 3, 'BSCS 1-C'),
(108, 33, 31, 1, 'BSCS 1-A'),
(109, 33, 31, 2, 'BSCS 1-B'),
(110, 33, 31, 3, 'BSCS 1-C'),
(111, 34, 30, 1, 'BSIT 2-A'),
(112, 35, 30, 1, 'BSIT 1-A'),
(113, 35, 30, 2, 'BSIT 1-B'),
(117, 24, 30, 1, 'BSIT 1-A'),
(118, 24, 30, 2, 'BSIT 1-B'),
(120, 36, 33, 1, 'BEEd 1-A'),
(121, 36, 33, 2, 'BEEd 1-B'),
(122, 33, 30, 5, 'BSIT 1-E');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_by`, `updated_at`) VALUES
(23, 'active_semester', '2nd Semester', 167, '2026-05-11 15:20:21'),
(24, 'active_school_year_id', '8', 167, '2026-05-11 15:20:21');

-- --------------------------------------------------------

--
-- Table structure for table `subject`
--

CREATE TABLE `subject` (
  `subj_id` int(11) NOT NULL,
  `curr_id` int(11) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `subj_code` varchar(20) NOT NULL,
  `subj_desc` text NOT NULL,
  `subj_lec` int(11) NOT NULL,
  `subj_lab` int(11) NOT NULL,
  `subj_unit` int(11) NOT NULL,
  `subj_min` int(11) NOT NULL,
  `subj_lvl` int(11) NOT NULL,
  `subj_term` int(11) NOT NULL,
  `subj_category` enum('Major','Minor','GENED') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject`
--

INSERT INTO `subject` (`subj_id`, `curr_id`, `dept_id`, `program_id`, `subj_code`, `subj_desc`, `subj_lec`, `subj_lab`, `subj_unit`, `subj_min`, `subj_lvl`, `subj_term`, `subj_category`) VALUES
(159, 43, 1, 30, 'IT 323', 'Software Engineering', 3, 0, 3, 75, 3, 2, 'Major'),
(160, 43, 1, 30, 'IT 343A', 'IT Electives', 3, 0, 3, 75, 3, 2, 'Major'),
(161, 39, 2, 32, 'CUL 113', 'Occupational Safety and Health', 3, 0, 3, 75, 1, 1, 'Major'),
(162, 39, 2, 32, 'CUL 133', 'Fundamental in Culinary Technology', 2, 3, 3, 75, 1, 1, 'Major'),
(163, 43, 1, 30, 'IT 123', 'Introduction to Human Computer Interaction', 2, 3, 0, 75, 1, 2, 'Major'),
(164, 43, 1, 30, 'IT 363A', 'Application Development and Emerging Technologies', 2, 3, 0, 75, 3, 2, 'Major'),
(165, 43, 1, 30, 'IT 383', 'Integrative Programming and Technologies 2', 2, 3, 0, 75, 3, 2, 'Major'),
(166, 43, 1, 30, 'IT 383A', 'Systems Integration and Architecture 2', 2, 3, 0, 75, 3, 2, 'Major'),
(167, 43, 1, 30, 'CCNA 323', 'Connecting Networks', 2, 3, 0, 75, 3, 2, 'Major'),
(168, 43, 1, 30, 'IT 343', 'Multimedia Systems', 2, 3, 0, 75, 3, 2, 'Major'),
(169, 43, 1, 30, 'IT 363', 'Information Assurance and Security 1 (*)', 2, 3, 3, 75, 3, 2, 'Major'),
(170, 43, 1, 30, 'CCNA 223', 'Routing and Switching Essentials', 2, 3, 3, 75, 2, 2, 'Major'),
(171, 43, 1, 30, 'IT 223', 'Information Management (*)', 3, 3, 3, 75, 2, 2, 'Major'),
(172, 43, 1, 30, 'IT 243', 'Quantitative Methods', 3, 0, 3, 75, 2, 2, 'Major'),
(173, 43, 1, 30, 'PATHFIT 222', 'Dance, Sports, Group Exercise, Outdoor and Adventure Activities', 2, 0, 2, 75, 2, 2, 'Minor'),
(174, 43, 1, 30, 'PATHFIT 122', 'Fitness Training', 2, 0, 2, 75, 1, 2, 'Minor'),
(175, 43, 1, 30, 'GEN. ED 005', 'Art Appreciation', 3, 0, 3, 75, 2, 2, 'GENED'),
(176, 43, 1, 30, 'GEN. ED 008', 'Science, Technology and Society', 3, 0, 3, 75, 2, 2, 'Minor'),
(177, 43, 1, 30, 'GE. EL 003', 'General Education Electives', 3, 0, 3, 75, 2, 2, 'GENED'),
(178, 43, 1, 30, 'IT 263', 'Integrative Programming and Technologies 1 (*)', 2, 3, 3, 75, 2, 2, 'Major'),
(179, 43, 1, 30, 'IT 143', 'Computer Programming 2 (*)', 2, 3, 3, 75, 1, 2, 'Major'),
(180, 43, 1, 30, 'GEN. ED. 003', 'Readings in Philippine History', 3, 0, 3, 75, 1, 2, 'GENED'),
(181, 43, 1, 30, 'GEN. ED. 006', 'Ethics', 3, 0, 3, 75, 1, 2, 'GENED'),
(182, 38, 1, 30, 'GEN. ED. 007', 'The Contemporary World', 3, 0, 3, 75, 1, 1, 'GENED'),
(183, 43, 1, 30, 'GE. EL 002', 'General Education Electives 2', 3, 0, 3, 75, 1, 2, 'GENED'),
(184, 38, 1, 30, 'NSTP 123', 'CWTS, LTS, MTS,(NAVAL or AIRFORCE)', 3, 0, 0, 75, 1, 1, 'Minor'),
(185, 43, 1, 30, 'IT 163', 'Statics and Probability', 3, 0, 3, 75, 1, 2, 'Minor'),
(186, 43, 1, 30, 'RIZ 001', 'Rizal Life and Works', 3, 0, 3, 75, 1, 2, 'Minor'),
(187, 38, 1, 30, 'PATHFIT 112', 'Movement Competency Training', 2, 0, 2, 75, 1, 1, 'Minor'),
(188, 43, 1, 30, 'GEN. ED. 011', 'Technical Writing', 3, 0, 3, 75, 2, 2, 'GENED'),
(193, 41, 3, 33, 'Gen. Ed. 001', 'Purposive Communication', 3, 0, 3, 75, 1, 1, 'GENED'),
(194, 41, 3, 33, 'Gen. Ed. 002', 'Understanding the Self', 3, 0, 3, 75, 1, 1, 'GENED'),
(195, 41, 3, 33, 'Gen. Ed. 004', 'Mathematics in the Modern World', 3, 0, 3, 75, 1, 1, 'GENED'),
(196, 39, 2, 36, 'ELX 113', 'Occupational Safety and Health', 3, 0, 3, 75, 1, 1, 'Minor'),
(197, 39, 2, 36, 'ELX 135', 'Electronic Devices 1', 3, 6, 5, 75, 1, 1, 'Major'),
(198, 39, 2, 36, 'ELX 153', 'Electronic Communication 1', 2, 3, 3, 75, 1, 1, 'Major'),
(199, 39, 2, 36, 'LX 173', 'Electronic CAD', 1, 3, 2, 75, 1, 1, 'Major'),
(200, 39, 2, 36, 'TECH112', 'Industrial Drawing', 1, 3, 2, 75, 1, 1, 'Major'),
(201, 39, 2, 36, 'GEN. ED. 004', 'Mathematics in the Modern World', 3, 0, 3, 75, 1, 1, 'GENED'),
(202, 39, 2, 36, 'P.E112', 'PATHFIT-Movement Competency Training', 2, 0, 2, 75, 1, 1, 'Minor'),
(203, 39, 2, 36, 'NSTP 113', 'CWTS/ROTC', 3, 0, 3, 75, 1, 1, 'Minor'),
(204, 39, 2, 36, 'ELX 123', 'Electronic Devices 2', 2, 3, 3, 75, 1, 2, 'Major'),
(205, 39, 2, 36, 'ELX 143', 'Electronic Communication 2', 2, 3, 3, 75, 1, 2, 'Major'),
(206, 39, 2, 36, 'ELX 163', 'Digital Electronics', 2, 3, 3, 75, 1, 2, 'Major'),
(207, 39, 2, 36, 'MSC 001', 'Comprehensive Mathematics', 5, 0, 5, 75, 1, 2, 'Minor'),
(208, 39, 2, 36, 'MSC 002', 'Chemistry for Industrial Technology', 2, 3, 3, 75, 1, 2, 'Major'),
(209, 39, 2, 36, 'TECH 123', 'Introduction to Information Technology', 2, 3, 3, 75, 1, 2, 'Major'),
(210, 39, 2, 36, 'GEN. ED. 002', 'Understanding the Self', 3, 0, 3, 75, 1, 2, 'GENED'),
(211, 39, 2, 36, 'P.E 122', 'PATHFIT 2-Exercise-based Fitness Activities', 2, 0, 2, 75, 1, 2, 'Minor'),
(212, 39, 2, 36, 'NSTP 123', 'CWTS/ROTC', 3, 0, 3, 75, 1, 2, 'Minor'),
(213, 39, 2, 36, 'ELX 213', 'Instrumentation and Process Control', 2, 3, 3, 75, 2, 1, 'Major'),
(214, 39, 2, 36, 'ELX 233', 'Sensor Technology', 2, 3, 3, 75, 2, 1, 'Major'),
(215, 39, 2, 36, 'ELX 253', 'Electronics Laws and Standards', 3, 0, 3, 75, 2, 2, 'Minor'),
(216, 39, 2, 36, 'TECH 213', 'Computer Programming', 2, 3, 3, 75, 2, 1, 'Major'),
(217, 39, 2, 36, 'MSC 003', 'Physics for Industrial Technologies', 2, 3, 3, 75, 2, 1, 'Major'),
(218, 39, 2, 36, 'GE. EL. 001', 'Indigenous Creative Crafts', 3, 0, 3, 75, 2, 1, 'Minor'),
(219, 41, 3, 33, 'English 001', 'English Enhancement/English Plus', 3, 0, 3, 75, 1, 1, 'Minor'),
(220, 39, 2, 36, 'GEN. ED. 006', 'Ethics', 3, 0, 3, 75, 2, 1, 'GENED'),
(221, 39, 2, 36, 'GEN. ED. 008', 'Science, Technology and Society', 3, 0, 3, 75, 2, 1, 'GENED'),
(222, 39, 2, 36, 'P.E 212', 'PATHFIT 3-Sport (Badminton)', 2, 0, 2, 75, 2, 1, 'Minor'),
(223, 39, 2, 36, 'ELX 223', 'Multimedia System', 2, 3, 3, 75, 2, 2, 'Major'),
(224, 41, 3, 33, 'Prof Ed. 113', 'The Child and Adolescent Learners and Learning Principles', 3, 0, 3, 75, 1, 1, 'Major'),
(225, 39, 2, 36, 'ELX 243', 'Industrial Electronics', 2, 3, 3, 75, 2, 2, 'Major'),
(226, 39, 2, 36, 'ELX 263', 'Electro-pneumatic Systems', 2, 3, 3, 75, 2, 2, 'Major'),
(227, 39, 2, 36, 'ELX 201', 'Educational Tour/Field Trip', 2, 0, 2, 75, 2, 2, 'Minor'),
(228, 39, 2, 36, 'GEN.ED.005', 'Art Appreciation', 3, 0, 3, 75, 2, 2, 'GENED'),
(229, 41, 3, 33, 'Prof Ed. 133', 'Facilitating Learner - Centered Teaching', 3, 0, 3, 75, 1, 1, 'Major'),
(230, 39, 2, 36, 'GEN.ED.007', 'The Contemporary World', 3, 0, 3, 75, 2, 2, 'GENED'),
(231, 39, 2, 36, 'TECH 223', 'Materials Technology Management', 3, 0, 3, 75, 2, 2, 'Minor'),
(232, 41, 3, 33, 'DRR 001', 'Disaster Risk Reduction Management and Education in Emergencies', 3, 0, 3, 75, 1, 1, 'Minor'),
(233, 39, 2, 36, 'TECH 243', 'Quality Control and Assurance', 3, 0, 3, 75, 2, 2, 'Minor'),
(234, 41, 3, 33, 'PE 112', 'PATHFIT 1 (Movement Competency Training)', 2, 0, 2, 75, 1, 1, 'Minor'),
(235, 39, 2, 36, 'P.E 222', 'PATHFIT 4-Sports (Table Tennis)', 2, 0, 2, 75, 2, 2, 'Minor'),
(236, 41, 3, 33, 'NSTP 001', 'CWTS, LTS, MTS, (Naval or Air Force)', 3, 0, 3, 75, 1, 1, 'Minor'),
(237, 41, 3, 33, 'Gen. Ed. 007', 'The Contemporary World', 3, 0, 3, 75, 1, 2, 'GENED'),
(238, 39, 2, 32, 'CUL 153', 'Kitchen Essential and Basic Food Preparation', 2, 3, 3, 75, 1, 1, 'Major'),
(239, 41, 3, 33, 'Prof. Ed. 123', 'Foundation of Special and Inclusive Education', 3, 0, 3, 75, 1, 2, 'Major'),
(240, 39, 2, 32, 'CUL 173', 'Culinary Mathematics', 3, 0, 3, 75, 1, 1, 'Minor'),
(241, 41, 3, 33, 'EED SCI 123', 'Teaching Science in the Elementary Grades 1 (Biology and Chemistry)', 3, 0, 3, 75, 1, 2, 'Major'),
(242, 39, 2, 32, 'CUL 192', 'Culinary Nutrition', 3, 0, 3, 75, 1, 1, 'Minor'),
(243, 39, 2, 32, 'TECH 112', 'Industrial Drawing', 1, 3, 3, 75, 1, 1, 'Major'),
(244, 41, 3, 33, 'EED SCI 143', 'Teaching Social Studies in Elementary Grades 1 (Culture and Geometry)', 3, 0, 3, 75, 1, 2, 'Major'),
(245, 41, 3, 33, 'EED FIL 163', 'Pagtuturo ng Filipino sa Elementarya 1 (Estruktura at Gamit ng Wikang Filipino)', 3, 0, 3, 75, 1, 2, 'Major'),
(246, 39, 2, 32, 'CUL 123', 'Food Styling and Design', 2, 3, 3, 75, 1, 2, 'Major'),
(247, 39, 2, 32, 'CUL 143', 'Foundation of Professional Cooking', 2, 3, 3, 75, 1, 2, 'Major'),
(248, 41, 3, 33, 'EED VED 183', 'Good Manners and Right Conduct (Edukasyon sa Pagpapakatao)', 3, 0, 3, 75, 1, 2, 'Major'),
(249, 39, 2, 32, 'CUL 163', 'Plant Based-Cooking', 2, 3, 3, 75, 1, 2, 'Major'),
(250, 41, 3, 33, 'PE 122', 'PATHFIT 2 (Fitness Training)', 2, 0, 2, 75, 1, 2, 'Minor'),
(251, 41, 3, 33, 'NSTP 002', 'CWTS, LTS, MTS (Naval or Air Force', 3, 0, 3, 75, 1, 2, 'Minor'),
(252, 41, 3, 33, 'GE. EL 001', 'General Education Electives', 3, 0, 3, 75, 1, 2, 'GENED'),
(253, 41, 3, 33, 'Gen. Ed. 003', 'Readings in Philippine History', 3, 0, 3, 75, 2, 1, 'GENED'),
(254, 41, 3, 33, 'Rizal 001', 'The Life and Works of Jose Rizal', 3, 0, 3, 75, 2, 1, 'Minor'),
(255, 41, 3, 33, 'Prof Ed. 213', 'Principles and Methods of Teching', 3, 0, 3, 75, 2, 1, 'Major'),
(256, 43, 1, 31, 'Gen. Ed. 002', 'Understanding the Self', 3, 0, 3, 75, 1, 1, 'GENED'),
(257, 44, 3, 33, 'Gen. Ed. 001', 'Purposive Communication', 3, 0, 3, 75, 1, 1, 'Minor'),
(258, 44, 3, 33, 'GEN. Ed. 002', 'Understanding the Self', 3, 0, 3, 75, 1, 1, 'Minor'),
(259, 44, 3, 33, 'Gen. Ed. 004', 'Mathematics in the Modern World', 3, 0, 3, 75, 1, 1, 'Minor'),
(260, 44, 3, 33, 'Gen. Ed. 006', 'Ethics', 3, 0, 3, 75, 1, 1, 'Minor'),
(261, 44, 3, 33, 'Gen. Ed. 008', 'Science, Technology, and Society', 3, 0, 3, 75, 1, 1, 'Minor'),
(263, 45, 3, 39, 'Prof. Ed 233', 'Assessment Learning', 3, 0, 3, 75, 4, 2, 'Minor'),
(264, 45, 3, 37, 'PE 222', 'PATHFIT 4 (Dance, Sports, Group Exercises, Outdoor and Adventure Activities)', 3, 0, 3, 75, 3, 2, 'Minor'),
(265, 45, 3, 39, 'Tech - FSM 263', 'Food Selection and Preparation', 2, 3, 3, 75, 2, 2, 'Major'),
(266, 45, 3, 39, 'Tech -FSM 323', 'Quantity Cookery', 2, 3, 3, 75, 3, 2, 'Major'),
(267, 45, 3, 39, 'DRR 113', 'DRR & Education in Emergencies', 3, 0, 3, 75, 3, 2, 'Minor'),
(268, 45, 3, 33, 'Gen. Ed. 005', 'Art Appreciation', 3, 0, 3, 75, 2, 2, 'Minor'),
(269, 45, 3, 39, 'TECH-FSM 363', 'BARTENDING AND BAR SERVICE MANAGEMENT', 1, 3, 3, 75, 3, 2, 'Major'),
(270, 45, 3, 39, 'INTRO-TECH 163', 'ENTREPRNUERSHIP', 2, 3, 3, 75, 1, 2, 'Major'),
(271, 45, 3, 33, 'EED 353', 'TEACHING MULTI-GRADES CLASSES', 3, 0, 3, 75, 2, 2, 'Minor'),
(272, 45, 3, 33, 'LIT 002', 'CONTEMPORARY LITERATURE', 3, 0, 3, 75, 1, 2, 'Minor'),
(273, 45, 3, 33, 'Prof. Ed 426', 'Teaching Internship', 0, 6, 6, 75, 4, 2, 'Major'),
(274, 45, 3, 33, 'Prof. Ed 323', 'The Teaching Profession', 3, 0, 3, 75, 3, 2, 'Minor'),
(275, 43, 1, 30, 'IT 113', 'Introduction to Computing', 3, 3, 2, 75, 1, 1, 'Major'),
(276, 43, 1, 30, 'IT 134', 'Computer Programming 1', 3, 3, 4, 75, 1, 1, 'Major'),
(277, 43, 1, 30, 'GEN. ED. 001', 'Purposive Communication', 3, 0, 3, 75, 1, 1, 'Major'),
(278, 43, 1, 30, 'GEN. ED. 002', 'Understanding the Self', 3, 0, 3, 75, 1, 1, 'GENED'),
(279, 43, 1, 30, 'GEN. ED. 004', 'Mathematics in the Modern World', 3, 0, 3, 75, 1, 1, 'GENED'),
(280, 43, 1, 30, 'FIL 001', 'Akademiko sa Wikang Filipino', 3, 0, 3, 75, 1, 1, 'GENED'),
(281, 43, 1, 30, 'DRR 113', 'Disaster Risk Reduction and Education in Emergencies', 3, 0, 3, 75, 1, 1, 'GENED'),
(282, 43, 1, 30, 'MATH ENHANCE 1', 'College Algebra and Trigonometry', 3, 0, 3, 75, 1, 1, 'GENED'),
(283, 43, 1, 30, 'PATHFIT 112', 'Movement Competency Training', 2, 0, 2, 75, 1, 1, 'GENED'),
(284, 43, 1, 30, 'NSTP 113', 'CWTS, LTS, MTS (Naval or Air Force)', 3, 0, 3, 75, 1, 1, 'GENED'),
(285, 43, 1, 30, 'IT 453', 'Presentation Skills IT', 3, 0, 3, 75, 1, 1, 'Major'),
(286, 43, 1, 30, 'IT 213', 'Data Structures and Algorithms', 2, 3, 3, 75, 2, 1, 'Major'),
(288, 43, 1, 30, 'IT 233', 'Object Oriented Programming', 2, 3, 3, 75, 2, 1, 'Major'),
(289, 43, 1, 30, 'IT 253', 'Platform Technologies', 2, 3, 3, 75, 2, 1, 'Major'),
(290, 43, 1, 30, 'IT 273', 'Web System and Technologies 1', 2, 3, 3, 75, 2, 1, 'Major'),
(291, 43, 1, 30, 'IT 293', 'Statistic and Probability', 3, 0, 3, 75, 2, 1, 'Major'),
(292, 43, 1, 30, 'CCNA 213', 'Introduction to Networks', 2, 3, 3, 75, 2, 1, 'Major'),
(293, 43, 1, 30, 'PE 212', '(PATHFIT) Dance Sports. Group Exercises, Outdoor and Adventures Activities', 2, 0, 2, 75, 2, 1, 'GENED'),
(294, 43, 1, 30, 'IT 315', 'Advanced Database Systems', 2, 3, 3, 75, 3, 1, 'Major'),
(295, 43, 1, 30, 'IT 333', 'Systems Analysis Design', 3, 0, 3, 75, 3, 1, 'Major'),
(296, 43, 1, 30, 'IT 353', 'Data Mining and Analytics', 3, 0, 3, 75, 3, 1, 'Major'),
(297, 43, 1, 30, 'IT 353A', 'Systems Integration and Architecture 1', 2, 3, 3, 75, 3, 1, 'Major'),
(298, 43, 1, 30, 'IT 373', 'Web Systems and Technology 2', 2, 3, 3, 75, 3, 1, 'Major'),
(299, 43, 1, 30, 'IT 373A', 'Event-Driven Programming', 2, 3, 3, 75, 3, 1, 'Major'),
(300, 43, 1, 30, 'IT 393', 'Social and Professional Issues', 3, 0, 3, 75, 3, 1, 'Major'),
(301, 43, 1, 30, 'CCNA 313', 'Scaling Networks', 2, 3, 3, 75, 3, 1, 'Major'),
(302, 43, 1, 30, 'IT 413', 'System Administration and Maintenance', 2, 3, 3, 75, 4, 1, 'Major'),
(303, 43, 1, 30, 'IT 433', 'Capstone Project and Research 2', 2, 3, 3, 75, 4, 1, 'Major'),
(304, 38, 1, 30, 'IT 113', 'Computer Concepts and Fundamentals', 3, 0, 3, 75, 1, 1, 'Major'),
(305, 38, 1, 30, 'ENG 113', 'Study and Thinking Skills', 3, 0, 3, 75, 1, 1, 'Minor'),
(306, 38, 1, 30, 'FIL 113', 'Sining ng Pakikipagtalastasan', 3, 0, 3, 75, 1, 1, 'GENED'),
(307, 38, 1, 30, 'MATH 115', 'College Algebra', 5, 0, 5, 75, 1, 1, 'GENED'),
(308, 38, 1, 30, 'IT 113L', 'Office Productivity Tools', 2, 3, 3, 75, 1, 1, 'Major'),
(309, 38, 1, 30, 'IT 114', 'Computer Programming 1', 3, 3, 4, 75, 1, 1, 'Major'),
(310, 38, 1, 30, 'PE 112', 'Physical Fitness and Gymnastic', 2, 0, 3, 75, 1, 1, 'GENED'),
(311, 38, 1, 30, 'ETH 113', 'Professional Ethics and Values Formation', 3, 0, 3, 75, 1, 1, 'Minor'),
(312, 38, 1, 30, 'NSTP 11', 'National Service Training Program 1', 3, 0, 3, 75, 1, 1, 'GENED'),
(313, 38, 1, 30, 'IT 213', 'File Organization', 3, 0, 3, 75, 2, 1, 'Major'),
(314, 38, 1, 30, 'ENG 213', 'Speech Communication', 3, 0, 3, 75, 2, 1, 'GENED'),
(315, 38, 1, 30, 'ALGO 213', 'Algorithms', 3, 0, 3, 75, 2, 1, 'Major'),
(316, 38, 1, 30, 'IT 214', 'Computer Programming 3', 3, 0, 4, 75, 2, 1, 'Major'),
(317, 38, 1, 30, 'PHYS 213', 'Physics 1', 2, 3, 3, 75, 2, 1, 'Minor'),
(318, 38, 1, 30, 'LODGES 213', 'Logic design and Switching', 3, 0, 3, 75, 2, 1, 'Minor'),
(319, 38, 1, 30, 'QUALC 213', 'Quality Consciousness, Habit Process', 3, 0, 3, 75, 2, 1, 'Minor'),
(320, 38, 1, 30, 'STAT 213', 'Probability and Statistics', 3, 0, 3, 75, 2, 1, 'Major'),
(321, 38, 1, 30, 'PE 212', 'Individual/Dual and Team Sports', 2, 0, 2, 75, 2, 1, 'GENED'),
(322, 38, 1, 30, 'MATH 313', 'Calculus', 3, 0, 3, 75, 3, 1, 'Major'),
(323, 38, 1, 30, 'IT 313', 'System Analysis and Design', 3, 0, 3, 75, 3, 1, 'Major'),
(324, 38, 1, 30, 'IT 333', 'Software Engineering', 3, 0, 3, 75, 3, 1, 'Major'),
(325, 38, 1, 30, 'WEB 311', 'Web Engineering 2', 0, 3, 1, 75, 3, 1, 'Major'),
(326, 38, 1, 30, 'LIT 313', 'World Literature', 3, 0, 3, 75, 3, 1, 'Minor'),
(327, 38, 1, 30, 'ACCTG 313', 'Accounting', 3, 0, 3, 75, 3, 1, 'Minor'),
(328, 38, 1, 30, 'PHILO 313', 'Philosophy', 3, 0, 3, 75, 3, 1, 'Minor'),
(329, 38, 1, 30, 'TECH 313', 'Technical Writing', 3, 0, 3, 75, 3, 1, 'Minor'),
(330, 38, 1, 30, 'EE 313', 'Fundamentals of Electricity', 3, 3, 4, 75, 3, 1, 'Minor'),
(331, 38, 1, 30, 'IT 413', 'IT Electives I', 3, 0, 3, 75, 4, 1, 'Major'),
(332, 38, 1, 30, 'IT 433', 'Programming Languages', 3, 0, 3, 75, 4, 1, 'Major'),
(333, 38, 1, 30, 'ECON 413', 'Engineering Economics', 3, 0, 3, 75, 4, 1, 'Major'),
(334, 38, 1, 30, 'HIST 413', 'Rizal Life, Works and Teaching', 3, 0, 3, 75, 4, 1, 'GENED'),
(335, 38, 1, 30, 'IT 473', 'Modeling and Simulation', 3, 0, 3, 75, 4, 1, 'Major'),
(336, 38, 1, 30, 'IT 412', 'Thesis 1', 0, 6, 2, 75, 4, 1, 'Major'),
(337, 38, 1, 30, 'IT 453', 'Presentation Skill IT', 3, 0, 3, 75, 4, 1, 'Major'),
(338, 38, 1, 30, 'IT 224', 'Computer Organization with Assembly Language', 3, 3, 4, 75, 2, 2, 'Major'),
(339, 38, 1, 30, 'IT 234', 'Computer Programming 4', 3, 3, 4, 75, 2, 2, 'Major'),
(340, 38, 1, 30, 'PHYS 223', 'Physics 2 (Lec and Lab)', 2, 3, 4, 75, 2, 2, 'Minor'),
(341, 38, 1, 30, 'OS 223', 'Operating System', 3, 0, 3, 75, 2, 2, 'Major'),
(342, 38, 1, 30, 'DBMS 224', 'Database Management Systems', 3, 3, 4, 75, 2, 2, 'Major'),
(343, 38, 1, 30, 'PSYCH 223', 'General Psychology', 3, 0, 3, 75, 2, 2, 'GENED'),
(344, 38, 1, 30, 'WEB 221', 'Web Engineering I', 0, 3, 1, 75, 2, 2, 'Major'),
(345, 38, 1, 30, 'PE 222', 'Recreational and Leadership Training', 2, 0, 2, 75, 2, 2, 'GENED'),
(346, 43, 1, 30, 'IT303 A', 'Capstone Project and Research 1', 6, 9, 3, 75, 3, 1, 'Major'),
(347, 44, 3, 33, 'Prof. Ed. 001', 'The Child and Adolescent Learners and Learning Principles', 3, 0, 3, 75, 1, 1, 'Major'),
(348, 44, 3, 33, 'Prof. Ed. 005', 'Facilitating Learner-Centered Teaching', 3, 0, 3, 75, 1, 1, 'Major'),
(349, 44, 3, 33, 'PE 112', 'PATHFIT 1 (Movement Competency Training)', 2, 0, 2, 75, 1, 1, 'Minor'),
(350, 44, 3, 33, 'NSTP 113', 'CWTS, LTS, MTS (Naval or Air Force)', 3, 0, 3, 75, 1, 1, 'Minor'),
(351, 44, 3, 33, 'EED VED 017', 'Good Manners and Right Conduct (Edukasyon sa Pagpapakatao)', 3, 0, 3, 75, 1, 2, 'Major'),
(352, 44, 3, 33, 'EED CLE 021', 'Children\'s Literature in Education', 3, 0, 3, 75, 1, 2, 'Major'),
(353, 44, 3, 33, 'Prof. Ed. 009', 'The Teacher and the School Curriculum', 3, 0, 3, 75, 1, 2, 'Major'),
(354, 44, 3, 33, 'EED MTB-MLE 016', 'Content and Pedagogy for the Mother Tongue', 3, 0, 3, 75, 1, 2, 'Major'),
(355, 44, 3, 33, 'Prof. Ed. 004', 'Foundation of Special and Inclusive Education', 3, 0, 3, 75, 1, 2, 'Major'),
(356, 44, 3, 33, 'EED SCI 001', 'Teaching Science in the Elementary Grades (Biology and Chemistry)', 3, 0, 3, 75, 1, 2, 'Major'),
(357, 44, 3, 33, 'PE 122', 'PATHFIT 2 (Fitness Training)', 2, 0, 2, 75, 1, 2, 'Minor'),
(358, 44, 3, 33, 'NSTP 123', 'CWTS, LTS, MTS (Naval or Air Force)', 3, 0, 3, 75, 1, 2, 'Minor'),
(359, 44, 3, 33, 'GE EL 001', 'General Education Electives (Disaster Risk Reduction Management and Education in Emergencies)', 3, 0, 3, 75, 1, 2, 'GENED'),
(360, 44, 3, 33, 'EED PEH 013', 'Teaching P. E. and Health in the ELem. Grades', 3, 0, 3, 75, 2, 1, 'Major'),
(361, 44, 3, 33, 'EED FIL 005', 'Pagtuturo ng Filipino sa Elementarya 1 (Estruktura at Gamit ng Wikang Filipino)', 3, 0, 3, 75, 2, 1, 'Major'),
(362, 44, 3, 33, 'Prof. Ed. 008', 'Technology for Teaching aand Learning 1', 3, 0, 3, 75, 2, 1, 'Major'),
(363, 44, 3, 33, 'EED SSC 003', 'Teaching Social Studies in ELementary Grades 1 (Culture & Geography)', 3, 0, 3, 75, 2, 1, 'Major'),
(364, 44, 3, 33, 'EED SCI 002', 'Teaching in Elementary Grades II (Physics, Earth and Space Science)', 3, 0, 3, 75, 2, 1, 'Major'),
(365, 44, 3, 33, 'EED MATH 007', 'Teaching Math in the Primary Grades', 3, 0, 3, 75, 2, 1, 'Major'),
(366, 44, 3, 33, 'PE 212', 'PATHFIT 3 (Dance, Sports, Group Exercises, Outdoor and Adventure Activities', 2, 0, 2, 75, 2, 1, 'Minor'),
(367, 44, 3, 33, 'GE EL 002', 'General Education Electives (Living in IT Era with AI in Education)', 3, 0, 3, 75, 2, 1, 'GENED'),
(368, 44, 3, 33, 'EED TTL 020', 'Technology for Teaching and Learning in the Elementary Grades', 3, 0, 3, 75, 2, 2, 'Major'),
(369, 44, 3, 33, 'Prof. Ed. 002', 'The Teaching Profession', 3, 0, 3, 75, 2, 2, 'Major'),
(370, 44, 3, 33, 'EED ENG 014', 'Teaching English in the Elementary Grades (Language Arts)', 3, 0, 3, 75, 2, 2, 'Major'),
(371, 44, 3, 33, 'Prof. Ed. 006', 'Assessment in Learning 1', 3, 0, 3, 75, 2, 2, 'Major'),
(372, 44, 3, 33, 'EED MATH 008', 'Teaching Math in the Intermediate Grades', 3, 0, 3, 75, 2, 2, 'Major'),
(373, 44, 3, 33, 'EED SSC 004', 'Teaching Social Studies in the Elementary Grades II (Philippine History and Government)', 3, 0, 3, 75, 2, 2, 'Major'),
(374, 44, 3, 33, 'EED FIL 006', 'Pagtuturo ng Filipino sa Elementarya II (Panitikan ng Pilipinas)', 3, 0, 3, 75, 2, 2, 'Major'),
(375, 44, 3, 33, 'PE 222', 'PATHFIT 4 (Dance, Sports, Group Exercises, Outdoor and Adventure Activities)', 2, 0, 2, 75, 2, 2, 'Minor'),
(376, 44, 3, 33, 'Prof. Ed. 007', 'Assessment in Learning 2', 3, 0, 3, 75, 3, 1, 'Major'),
(377, 44, 3, 33, 'EED RES 018', 'Research in Education 1 (Proposal Writing)', 3, 0, 3, 75, 3, 1, 'Major'),
(378, 44, 3, 33, 'EED MUSIC 011', 'Teaching Music in the Elementary Grades', 3, 0, 3, 75, 3, 1, 'Major'),
(379, 44, 3, 33, 'Prof. Ed. 010', 'Building and Enhancing New Literacies Across the Curriculum', 3, 0, 3, 75, 3, 1, 'Major'),
(380, 44, 3, 33, 'EED ENG 015', 'Teaching English in the Elementary Grades through Literature', 3, 0, 3, 75, 3, 1, 'Major'),
(381, 44, 3, 33, 'EED ARTS 012', 'teaching Arts in the Elementary Grades', 3, 0, 3, 75, 3, 1, 'Major'),
(382, 44, 3, 33, 'EDD TLE 009', 'Edukasyong Pantahanan at Pangkabuhayan', 3, 0, 3, 75, 3, 1, 'Major'),
(383, 44, 3, 33, 'Prof. Ed. 003', 'The Teacher and the Community, School Culture, and Organizational Leadership', 3, 0, 3, 75, 3, 2, 'Major'),
(384, 44, 3, 33, 'Gen. Ed. 005', 'Art Appreciation', 3, 0, 3, 75, 3, 2, 'GENED'),
(385, 44, 3, 33, 'Gen. Ed. 007', 'The Contemporary World with Peace Education', 3, 0, 3, 75, 3, 2, 'GENED'),
(386, 44, 3, 33, 'EED RES 019', 'Research in Education 2 (Thesis Writing)', 3, 0, 3, 75, 3, 2, 'Major'),
(387, 44, 3, 33, 'EED TLE 010', 'Edukasyong Pantahan at Pangkabuhayan with Entrepeneurship', 3, 0, 3, 75, 3, 2, 'Major'),
(388, 44, 3, 33, 'Prof. Ed. 011', 'Field Study 1 (Observation of Teaching-Learning in Actual School Environment)', 3, 0, 3, 75, 3, 2, 'Major'),
(389, 44, 3, 33, 'Prof. Ed. 012', 'Field Study 2 (Participation and teaching Assistantship)', 3, 0, 3, 75, 3, 2, 'Major'),
(390, 44, 3, 33, 'Prof. Ed. 013', 'Teaching Internship (with Seminar on Problems Met)', 0, 18, 6, 75, 4, 1, 'Major'),
(391, 44, 3, 33, 'Gen. Ed. 003', 'Readings in the Philippine History with/ Indigenous Peoples (IP) Studies/Education', 3, 0, 3, 75, 4, 2, 'GENED'),
(392, 44, 3, 33, 'Rizal 001', 'The Life and Works of Jose Rizal', 3, 0, 3, 75, 4, 2, 'Minor'),
(393, 44, 3, 33, 'EED EL 001', 'Teaching Multi-Grade Classes', 3, 0, 3, 75, 4, 2, 'Major'),
(394, 44, 3, 33, 'GE EL 003', 'General Education Electives (Religion, Religious Experiences and Spirituality)', 3, 0, 3, 75, 4, 2, 'GENED'),
(395, 44, 3, 33, 'Prof. Ed. 014', 'Related Learning Experiences', 3, 0, 3, 75, 4, 2, 'Major'),
(396, 41, 3, 33, 'Prof. Ed. 233', 'Technology for Teaching and Learning 1', 3, 0, 3, 75, 2, 1, 'Major'),
(397, 41, 3, 33, 'EED SCI 213', 'Teaching Science in Elementary Grades II (Physics, Earth and Space Science)', 3, 0, 3, 75, 2, 1, 'Major'),
(398, 41, 3, 33, 'EED MATH 233', 'Teaching Math in the Primary Grades', 3, 0, 3, 75, 2, 1, 'Major'),
(399, 41, 3, 33, 'LIT 001', 'Introduction to Literature', 3, 0, 3, 75, 2, 1, 'Major'),
(400, 41, 3, 33, 'PE 212', 'PATHFIT 3 (Dance, Sports, Group Exercises, Outdoor and adventure Activities', 2, 0, 2, 75, 2, 1, 'Major'),
(401, 41, 3, 33, 'GE EL 002', 'General Education Electives', 3, 0, 3, 75, 2, 1, 'GENED'),
(402, 41, 3, 33, 'GEn. Ed. 005', 'Art Appreciation', 3, 0, 3, 75, 2, 2, 'GENED'),
(403, 41, 3, 33, 'Gen. Ed. 008', 'Science, Technology and Society', 3, 0, 3, 75, 2, 2, 'GENED'),
(404, 41, 3, 33, 'Gen. Ed. 006', 'Ethics', 3, 0, 3, 75, 2, 2, 'GENED'),
(405, 41, 3, 33, 'Prof. Ed. 223', 'Assessment in Learning 1', 3, 0, 3, 75, 2, 2, 'Major'),
(406, 41, 3, 33, 'EED MATH 223', 'teaching Math in Intermediate Grades', 3, 0, 3, 75, 2, 2, 'Major'),
(407, 41, 3, 33, 'LIT 002', 'Contemporary Literature', 3, 0, 3, 75, 2, 2, 'Major'),
(408, 41, 3, 33, 'EED SSC 243', 'Teaching Social Studies in the Elementary Grades (Philippine History and Government)', 3, 0, 3, 75, 2, 2, 'Major'),
(409, 41, 3, 33, 'EED FIL 222', 'Pagtuturo ng Filipino sa Elementarya II (Panitikan ng Pilipinas)', 3, 0, 3, 75, 2, 2, 'Major'),
(410, 41, 3, 33, 'PE 222', 'PATHFIT 4 (Dance, Sports, Group Exercises, Outdoor and Adventure Activities)', 2, 0, 2, 75, 2, 2, 'Major'),
(411, 41, 3, 33, 'GE EL 003', 'General Education Electives', 3, 0, 3, 75, 2, 2, 'GENED'),
(412, 41, 3, 33, 'EED TTL 313', 'Technology for teaching and Learning in the ELementary Grades', 3, 0, 3, 75, 3, 1, 'Major'),
(413, 41, 3, 33, 'Prof. Ed. 313', 'Assessment in Learning 2', 3, 0, 3, 75, 3, 1, 'Major'),
(414, 41, 3, 33, 'EED RES 333', 'Research in Education 1 (Proposal Writing)', 3, 0, 3, 75, 3, 1, 'Major'),
(415, 41, 3, 33, 'EED ENG 353', 'Teaching English in the Elementary Grades', 3, 0, 3, 75, 3, 1, 'Major'),
(416, 41, 3, 33, 'EED MUSIC 373', 'Teaching Music in the Elementary Grades', 3, 0, 3, 75, 3, 1, 'Major'),
(417, 41, 3, 33, 'Prof. Ed. 323', 'Building and Enhancing New Literacies Across the  Curriculum', 3, 0, 3, 75, 3, 1, 'Major'),
(418, 41, 3, 33, 'EED ENG 383', 'Teaching English in the Elementary Grades through Literature', 3, 0, 3, 75, 3, 1, 'Major'),
(419, 41, 3, 33, 'EED ARTS 393', 'Teaching Arts in the Elementary Grades', 3, 0, 3, 75, 3, 1, 'Major'),
(420, 41, 3, 33, 'EED TLE 3113', 'Edukasyong Pantahanan at Pangkabuhayan', 3, 0, 3, 75, 3, 1, 'Major'),
(421, 40, 3, 34, 'Ged. Ed 001', 'Purposive Communication', 3, 0, 3, 75, 1, 1, 'Minor'),
(422, 40, 3, 34, 'Ged. Ed 002', 'Understanding the Self', 3, 0, 3, 75, 1, 1, 'Minor'),
(423, 40, 3, 34, 'PED 001', 'Philosophical and Socio-anthropological Foundations of Physical Education', 3, 0, 3, 75, 1, 1, 'Minor'),
(424, 40, 3, 34, 'PED 002', 'Anatomy and Physiology of Human Movement', 3, 0, 3, 75, 1, 1, 'Minor'),
(425, 40, 3, 34, 'PE 112', 'PATHFIT 1(Movement Competency Training', 2, 0, 2, 75, 1, 1, 'Minor'),
(426, 40, 3, 34, 'ProfEd 004', 'Foundation of Special Education and Inclusive Education', 3, 0, 3, 75, 1, 1, 'Minor'),
(427, 40, 3, 34, 'ProfEd 001', 'The Child and Adolescent Learners and Learners and Learning Principles', 3, 0, 3, 75, 1, 1, 'Minor'),
(428, 40, 3, 34, 'PED 019', 'Personal, Community and Environmental Health', 3, 0, 3, 75, 1, 1, 'Minor'),
(429, 40, 3, 34, 'NSTP 001', 'CWTS, LTS, MTS (Navy or Air Force)', 3, 0, 3, 75, 1, 1, 'Minor'),
(430, 48, 7, 40, 'Gen Ed', 'Purposive Communication', 3, 0, 3, 75, 1, 1, 'GENED'),
(431, 48, 7, 40, 'Gen Ed 002', 'Understanding the Self', 3, 0, 3, 75, 1, 1, 'GENED'),
(432, 48, 7, 40, 'Gen Ed 007', 'The Contemporary World', 3, 0, 3, 75, 1, 1, 'GENED'),
(433, 48, 7, 40, 'Fil 003', 'Mabisang Pagpapahayag', 3, 0, 0, 75, 1, 1, 'Minor'),
(434, 48, 7, 40, 'THC 113', 'Macro Perspective of Tourism and Hospitality', 3, 0, 3, 75, 1, 1, 'Major'),
(435, 48, 7, 40, 'THC 133', 'Risk Management as Applied to Safety, Security, and Sanitation', 3, 0, 3, 75, 1, 1, 'Major'),
(436, 48, 7, 40, 'BME 113', 'Accounting and Finance in Tourism and Hosp.', 3, 0, 3, 75, 1, 1, 'Major'),
(437, 48, 7, 40, 'PATHFIT 112', 'Movement Competency Training', 2, 0, 2, 75, 1, 1, 'GENED'),
(438, 48, 7, 40, 'NSTP 113', 'CWTS, LTS, MTS, (Naval or Air Force)', 3, 0, 3, 75, 1, 1, 'GENED'),
(439, 48, 7, 40, 'Gen Ed 004', 'Mathematics in the Modern World', 3, 0, 3, 75, 2, 1, 'GENED'),
(440, 48, 7, 40, 'Gen Ed 003', 'Readings in Philippine History', 3, 0, 3, 75, 2, 1, 'GENED'),
(441, 48, 7, 40, 'Lit 001', 'Philippine Literature', 3, 0, 3, 75, 2, 1, 'Minor'),
(442, 48, 7, 40, 'Fil 001', 'Akademiko sa Wikang Filipino', 3, 0, 3, 75, 2, 1, 'Minor'),
(443, 48, 7, 40, 'BME 213', 'Business Org. and Management', 3, 0, 3, 75, 2, 1, 'Minor'),
(444, 48, 7, 40, 'HPC 213', 'Applied Business Tools & Tech. (PMPS) w/ lab', 2, 3, 3, 75, 2, 1, 'Major'),
(445, 48, 7, 40, 'HPC 233', 'Supply Chain Mgt. in Hosp. Industry with Applied Economics', 3, 0, 3, 75, 2, 1, 'Major'),
(446, 48, 7, 40, 'HPC 253', 'Foreign Language', 3, 0, 3, 75, 2, 1, 'Minor'),
(447, 48, 7, 40, 'PATHFIT 212', 'Dance, Sports, Group Exercise, Outdoor and Adventure Activities', 2, 0, 2, 75, 2, 1, 'Minor'),
(448, 48, 7, 40, 'HPC 202', 'Educational Tour/Field Trip', 2, 0, 2, 75, 2, 3, 'Major'),
(449, 48, 7, 40, 'Rizal 001', 'Rizal\'s Life and Works', 3, 0, 3, 75, 3, 1, 'Minor'),
(450, 48, 7, 40, 'Gen Ed 008', 'Science, Technology, and Society', 3, 0, 3, 75, 3, 1, 'GENED'),
(451, 48, 7, 40, 'HMPE 313', 'Elective Course / Bar and Beverage Management with Lab', 3, 0, 3, 75, 3, 1, 'Major'),
(452, 48, 7, 40, 'HMPE 333', 'Elective Course / Asian Cuisine', 3, 0, 3, 75, 3, 1, 'Major'),
(453, 48, 7, 40, 'BME 313', 'Operations Mgt. in Tourism and Hosp. Ind.', 3, 0, 3, 75, 3, 1, 'Major'),
(454, 48, 7, 40, 'HPC 313', 'Ergonomics and Facilities Planning for the Hospitality Industry', 3, 0, 3, 75, 3, 1, 'Major'),
(455, 48, 7, 40, 'HPC 333', 'Research in Hospitality 1', 3, 0, 3, 75, 3, 1, 'Major'),
(456, 48, 7, 40, 'THC 313', 'Professional Development and Applied Ethics', 3, 0, 3, 75, 3, 1, 'Major'),
(457, 48, 7, 40, 'HM 417', 'Practicum - 700 Hrs.', 0, 7, 7, 75, 4, 1, 'Major');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `acc_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `acc_id`, `permission_id`, `allowed`) VALUES
(694, 121, 4, 0),
(695, 121, 23, 0),
(696, 121, 5, 0),
(697, 121, 6, 0),
(698, 121, 8, 0),
(699, 121, 7, 0),
(700, 121, 14, 0),
(701, 121, 15, 0),
(702, 121, 18, 0),
(703, 121, 19, 0),
(704, 121, 25, 0),
(705, 121, 12, 0),
(706, 121, 13, 0),
(707, 121, 11, 0),
(708, 121, 24, 0),
(709, 121, 9, 0),
(710, 121, 26, 1),
(711, 121, 10, 1),
(712, 121, 20, 0),
(713, 121, 21, 0),
(714, 121, 22, 0),
(715, 121, 2, 0),
(716, 121, 1, 0),
(717, 121, 3, 1),
(718, 121, 16, 0),
(719, 121, 17, 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `acc_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`acc_id`, `role_id`) VALUES
(1, 1),
(108, 2),
(109, 2),
(110, 2),
(162, 2),
(165, 2),
(167, 2),
(121, 3),
(131, 3),
(111, 4),
(112, 4),
(122, 4),
(123, 4),
(124, 4),
(125, 4),
(126, 4),
(127, 4),
(128, 4),
(129, 4),
(130, 4),
(133, 4),
(134, 4),
(135, 4),
(137, 4),
(138, 4),
(139, 4),
(140, 4),
(143, 4),
(148, 4),
(149, 4),
(150, 4),
(151, 4),
(153, 4),
(154, 4),
(155, 4),
(156, 4),
(157, 4),
(158, 4),
(159, 4),
(160, 4),
(161, 4),
(163, 4),
(164, 4),
(166, 4);

-- --------------------------------------------------------

--
-- Table structure for table `workload_policy`
--

CREATE TABLE `workload_policy` (
  `policy_id` int(11) NOT NULL,
  `policy_type` enum('Rank','Designation') NOT NULL,
  `name` enum('University Professor','Professor I','Professor II','Professor III','Professor IV','Professor V','Professor VI','Associate Professor I','Associate Professor II','Associate Professor III','Associate Professor IV','Associate Professor V','Assistant Professor I','Assistant Professor II','Assistant Professor III','Assistant Professor IV','Instructor I','Instructor II','Instructor III','Vice President','Campus Director','Dean','Director','Head','Chairperson/Coordinator/As Officer in Faculty Association') NOT NULL,
  `administration_hours` int(11) DEFAULT 0,
  `instruction_hours` int(11) DEFAULT 0,
  `research_hours` int(11) DEFAULT 0,
  `extension_hours` int(11) DEFAULT 0,
  `production_hours` int(11) DEFAULT 0,
  `consultation_hours` int(11) DEFAULT 0,
  `total_hours` int(11) DEFAULT 40,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workload_policy`
--

INSERT INTO `workload_policy` (`policy_id`, `policy_type`, `name`, `administration_hours`, `instruction_hours`, `research_hours`, `extension_hours`, `production_hours`, `consultation_hours`, `total_hours`, `created_at`, `updated_at`) VALUES
(26, 'Designation', 'Vice President', 18, 3, 10, 3, 3, 3, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(27, 'Designation', 'Campus Director', 18, 3, 10, 3, 3, 3, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(28, 'Designation', 'Dean', 18, 6, 7, 3, 3, 3, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(29, 'Designation', 'Director', 15, 6, 10, 3, 3, 3, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(30, 'Designation', 'Head', 12, 9, 10, 3, 3, 3, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(31, 'Designation', 'Chairperson/Coordinator/As Officer in Faculty Association', 9, 15, 7, 3, 3, 3, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(32, 'Rank', 'University Professor', 0, 6, 16, 9, 3, 6, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(33, 'Rank', 'Professor I', 0, 9, 12, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(34, 'Rank', 'Professor II', 0, 9, 12, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(35, 'Rank', 'Professor III', 0, 9, 12, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(36, 'Rank', 'Professor IV', 0, 9, 12, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(37, 'Rank', 'Professor V', 0, 9, 12, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(38, 'Rank', 'Professor VI', 0, 9, 12, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(39, 'Rank', 'Associate Professor I', 0, 12, 9, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(40, 'Rank', 'Associate Professor II', 0, 12, 9, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(41, 'Rank', 'Associate Professor III', 0, 12, 9, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(42, 'Rank', 'Associate Professor IV', 0, 12, 9, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(43, 'Rank', 'Associate Professor V', 0, 12, 9, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(44, 'Rank', 'Assistant Professor I', 0, 15, 6, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(45, 'Rank', 'Assistant Professor II', 0, 15, 6, 9, 3, 7, 40, '2026-01-04 16:40:46', '2026-01-04 16:40:46'),
(46, 'Rank', 'Assistant Professor III', 0, 15, 6, 9, 3, 7, 40, '2026-01-04 16:40:47', '2026-01-04 16:40:47'),
(47, 'Rank', 'Assistant Professor IV', 0, 15, 6, 9, 3, 7, 40, '2026-01-04 16:40:47', '2026-01-04 16:40:47'),
(48, 'Rank', 'Instructor I', 0, 18, 6, 6, 3, 7, 40, '2026-01-04 16:40:47', '2026-01-04 16:40:47'),
(49, 'Rank', 'Instructor II', 0, 18, 6, 6, 3, 7, 40, '2026-01-04 16:40:47', '2026-01-04 16:40:47'),
(50, 'Rank', 'Instructor III', 0, 18, 6, 6, 3, 7, 40, '2026-01-04 16:40:47', '2026-01-04 16:40:47');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account`
--
ALTER TABLE `account`
  ADD PRIMARY KEY (`acc_id`),
  ADD UNIQUE KEY `acc_user` (`acc_user`),
  ADD UNIQUE KEY `acc_email` (`acc_email`),
  ADD KEY `fk_account_department` (`dept_id`),
  ADD KEY `fk_account_role` (`role_id`),
  ADD KEY `idx_verification_otp` (`verification_otp`,`verification_otp_expires_at`);

--
-- Indexes for table `account_departments`
--
ALTER TABLE `account_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_account_department` (`acc_id`,`dept_id`),
  ADD KEY `fk_account_departments_account` (`acc_id`),
  ADD KEY `fk_account_departments_department` (`dept_id`);

--
-- Indexes for table `active_school_year_semester`
--
ALTER TABLE `active_school_year_semester`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sy_id` (`sy_id`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `acc_id` (`acc_id`);

--
-- Indexes for table `building`
--
ALTER TABLE `building`
  ADD PRIMARY KEY (`bd_id`),
  ADD KEY `idx_dept_id` (`dept_id`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`class_id`),
  ADD KEY `curr_id` (`curr_id`);

--
-- Indexes for table `college`
--
ALTER TABLE `college`
  ADD PRIMARY KEY (`college_id`),
  ADD UNIQUE KEY `college_code` (`college_code`);

--
-- Indexes for table `conflict`
--
ALTER TABLE `conflict`
  ADD PRIMARY KEY (`conflict_id`),
  ADD KEY `schd_id` (`schd_id`);

--
-- Indexes for table `curriculum`
--
ALTER TABLE `curriculum`
  ADD PRIMARY KEY (`curr_id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `idx_curr_type` (`curr_type`),
  ADD KEY `idx_curr_version` (`curr_version`),
  ADD KEY `idx_program_id` (`program_id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `dept_code` (`dept_code`),
  ADD KEY `fk_department_college` (`college_id`);

--
-- Indexes for table `faculty_workload`
--
ALTER TABLE `faculty_workload`
  ADD PRIMARY KEY (`workload_id`),
  ADD KEY `inst_id` (`inst_id`),
  ADD KEY `sy_id` (`sy_id`);

--
-- Indexes for table `instructor`
--
ALTER TABLE `instructor`
  ADD PRIMARY KEY (`inst_id`),
  ADD UNIQUE KEY `inst_user` (`inst_user`),
  ADD KEY `idx_instructor_program_id` (`program_id`);

--
-- Indexes for table `instructor_department_appointment`
--
ALTER TABLE `instructor_department_appointment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_inst_dept` (`inst_id`,`dept_id`),
  ADD KEY `fk_ida_inst` (`inst_id`),
  ADD KEY `fk_ida_dept` (`dept_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`),
  ADD UNIQUE KEY `permission_key` (`permission_key`);

--
-- Indexes for table `permission_content`
--
ALTER TABLE `permission_content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key` (`permission_key`),
  ADD KEY `module` (`module`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `program`
--
ALTER TABLE `program`
  ADD PRIMARY KEY (`program_id`),
  ADD KEY `fk_program_department` (`dept_id`),
  ADD KEY `idx_effective_academic_year` (`effective_academic_year`),
  ADD KEY `idx_program_type` (`program_type`);

--
-- Indexes for table `program_year_level_curriculum`
--
ALTER TABLE `program_year_level_curriculum`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_program_year_level` (`program_id`,`year_level`),
  ADD KEY `idx_program_id` (`program_id`),
  ADD KEY `idx_curr_id` (`curr_id`),
  ADD KEY `idx_year_level` (`year_level`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`rm_id`),
  ADD KEY `bd_id` (`bd_id`),
  ADD KEY `fk_room_department` (`dept_id`);

--
-- Indexes for table `room_access`
--
ALTER TABLE `room_access`
  ADD PRIMARY KEY (`access_id`),
  ADD UNIQUE KEY `unique_room_dept_access` (`rm_id`,`granted_to_dept_id`,`status`),
  ADD KEY `fk_room_access_room` (`rm_id`),
  ADD KEY `fk_room_access_granted_to` (`granted_to_dept_id`),
  ADD KEY `fk_room_access_granted_by` (`granted_by_dept_id`),
  ADD KEY `fk_room_access_account` (`granted_by_acc_id`);

--
-- Indexes for table `room_request`
--
ALTER TABLE `room_request`
  ADD PRIMARY KEY (`req_id`),
  ADD KEY `rm_id` (`rm_id`),
  ADD KEY `inst_id` (`inst_id`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`schd_id`),
  ADD KEY `sy_id` (`sy_id`),
  ADD KEY `subj_id` (`subj_id`),
  ADD KEY `sec_id` (`sec_id`),
  ADD KEY `inst_id` (`inst_id`),
  ADD KEY `rm_id` (`rm_id`),
  ADD KEY `idx_program_id` (`program_id`),
  ADD KEY `idx_year_level` (`year_level`),
  ADD KEY `idx_dept_id` (`dept_id`),
  ADD KEY `idx_sy_term_status` (`sy_id`,`schd_term`,`schd_status`),
  ADD KEY `idx_schd_term` (`schd_term`),
  ADD KEY `idx_schd_status` (`schd_status`);

--
-- Indexes for table `schoolyear`
--
ALTER TABLE `schoolyear`
  ADD PRIMARY KEY (`sy_id`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`sec_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `idx_program_id` (`program_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `subject`
--
ALTER TABLE `subject`
  ADD PRIMARY KEY (`subj_id`),
  ADD KEY `curr_id` (`curr_id`),
  ADD KEY `subject_ibfk_2` (`program_id`),
  ADD KEY `subject_ibfk_3` (`dept_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `acc_id` (`acc_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`acc_id`,`role_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `workload_policy`
--
ALTER TABLE `workload_policy`
  ADD PRIMARY KEY (`policy_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account`
--
ALTER TABLE `account`
  MODIFY `acc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `account_departments`
--
ALTER TABLE `account_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `active_school_year_semester`
--
ALTER TABLE `active_school_year_semester`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=387;

--
-- AUTO_INCREMENT for table `building`
--
ALTER TABLE `building`
  MODIFY `bd_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `college`
--
ALTER TABLE `college`
  MODIFY `college_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `conflict`
--
ALTER TABLE `conflict`
  MODIFY `conflict_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `curriculum`
--
ALTER TABLE `curriculum`
  MODIFY `curr_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `dept_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `faculty_workload`
--
ALTER TABLE `faculty_workload`
  MODIFY `workload_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `instructor`
--
ALTER TABLE `instructor`
  MODIFY `inst_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `instructor_department_appointment`
--
ALTER TABLE `instructor_department_appointment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `permission_content`
--
ALTER TABLE `permission_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `program`
--
ALTER TABLE `program`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `program_year_level_curriculum`
--
ALTER TABLE `program_year_level_curriculum`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `rm_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `room_access`
--
ALTER TABLE `room_access`
  MODIFY `access_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `room_request`
--
ALTER TABLE `room_request`
  MODIFY `req_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schd_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=570;

--
-- AUTO_INCREMENT for table `schoolyear`
--
ALTER TABLE `schoolyear`
  MODIFY `sy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `sec_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `subject`
--
ALTER TABLE `subject`
  MODIFY `subj_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=458;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=720;

--
-- AUTO_INCREMENT for table `workload_policy`
--
ALTER TABLE `workload_policy`
  MODIFY `policy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account`
--
ALTER TABLE `account`
  ADD CONSTRAINT `fk_account_department` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_account_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `account_departments`
--
ALTER TABLE `account_departments`
  ADD CONSTRAINT `fk_account_departments_account` FOREIGN KEY (`acc_id`) REFERENCES `account` (`acc_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_account_departments_department` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `active_school_year_semester`
--
ALTER TABLE `active_school_year_semester`
  ADD CONSTRAINT `active_school_year_semester_ibfk_1` FOREIGN KEY (`sy_id`) REFERENCES `schoolyear` (`sy_id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`acc_id`) REFERENCES `account` (`acc_id`);

--
-- Constraints for table `building`
--
ALTER TABLE `building`
  ADD CONSTRAINT `building_ibfk_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `class`
--
ALTER TABLE `class`
  ADD CONSTRAINT `class_ibfk_1` FOREIGN KEY (`curr_id`) REFERENCES `curriculum` (`curr_id`);

--
-- Constraints for table `conflict`
--
ALTER TABLE `conflict`
  ADD CONSTRAINT `conflict_ibfk_1` FOREIGN KEY (`schd_id`) REFERENCES `schedule` (`schd_id`);

--
-- Constraints for table `curriculum`
--
ALTER TABLE `curriculum`
  ADD CONSTRAINT `curriculum_ibfk_1` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`),
  ADD CONSTRAINT `fk_curriculum_program` FOREIGN KEY (`program_id`) REFERENCES `program` (`program_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `department`
--
ALTER TABLE `department`
  ADD CONSTRAINT `fk_department_college` FOREIGN KEY (`college_id`) REFERENCES `college` (`college_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `faculty_workload`
--
ALTER TABLE `faculty_workload`
  ADD CONSTRAINT `faculty_workload_ibfk_1` FOREIGN KEY (`inst_id`) REFERENCES `instructor` (`inst_id`),
  ADD CONSTRAINT `faculty_workload_ibfk_2` FOREIGN KEY (`sy_id`) REFERENCES `schoolyear` (`sy_id`);

--
-- Constraints for table `instructor`
--
ALTER TABLE `instructor`
  ADD CONSTRAINT `fk_instructor_program` FOREIGN KEY (`program_id`) REFERENCES `program` (`program_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `instructor_department_appointment`
--
ALTER TABLE `instructor_department_appointment`
  ADD CONSTRAINT `fk_ida_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ida_inst` FOREIGN KEY (`inst_id`) REFERENCES `instructor` (`inst_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `program`
--
ALTER TABLE `program`
  ADD CONSTRAINT `fk_program_department` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `program_year_level_curriculum`
--
ALTER TABLE `program_year_level_curriculum`
  ADD CONSTRAINT `program_year_level_curriculum_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `program` (`program_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `program_year_level_curriculum_ibfk_2` FOREIGN KEY (`curr_id`) REFERENCES `curriculum` (`curr_id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room`
--
ALTER TABLE `room`
  ADD CONSTRAINT `fk_room_department` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `room_ibfk_1` FOREIGN KEY (`bd_id`) REFERENCES `building` (`bd_id`);

--
-- Constraints for table `room_access`
--
ALTER TABLE `room_access`
  ADD CONSTRAINT `fk_room_access_account` FOREIGN KEY (`granted_by_acc_id`) REFERENCES `account` (`acc_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_room_access_granted_by` FOREIGN KEY (`granted_by_dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_room_access_granted_to` FOREIGN KEY (`granted_to_dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_room_access_room` FOREIGN KEY (`rm_id`) REFERENCES `room` (`rm_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `room_request`
--
ALTER TABLE `room_request`
  ADD CONSTRAINT `room_request_ibfk_1` FOREIGN KEY (`rm_id`) REFERENCES `room` (`rm_id`),
  ADD CONSTRAINT `room_request_ibfk_2` FOREIGN KEY (`inst_id`) REFERENCES `instructor` (`inst_id`);

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`sy_id`) REFERENCES `schoolyear` (`sy_id`),
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`subj_id`) REFERENCES `subject` (`subj_id`),
  ADD CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`sec_id`) REFERENCES `section` (`sec_id`),
  ADD CONSTRAINT `schedule_ibfk_4` FOREIGN KEY (`inst_id`) REFERENCES `instructor` (`inst_id`),
  ADD CONSTRAINT `schedule_ibfk_5` FOREIGN KEY (`rm_id`) REFERENCES `room` (`rm_id`);

--
-- Constraints for table `section`
--
ALTER TABLE `section`
  ADD CONSTRAINT `section_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`);

--
-- Constraints for table `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `account` (`acc_id`) ON DELETE SET NULL;

--
-- Constraints for table `subject`
--
ALTER TABLE `subject`
  ADD CONSTRAINT `subject_ibfk_1` FOREIGN KEY (`curr_id`) REFERENCES `curriculum` (`curr_id`),
  ADD CONSTRAINT `subject_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `program` (`program_id`),
  ADD CONSTRAINT `subject_ibfk_3` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`);

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`acc_id`) REFERENCES `account` (`acc_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`acc_id`) REFERENCES `account` (`acc_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
