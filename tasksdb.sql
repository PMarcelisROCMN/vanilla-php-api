-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2024 at 05:04 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tasksdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `tblsessions`
--

CREATE TABLE `tblsessions` (
  `id` bigint(20) NOT NULL COMMENT 'Session ID',
  `userid` bigint(20) NOT NULL COMMENT 'User ID',
  `accesstoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Access Token',
  `accesstokenexpiry` datetime NOT NULL COMMENT 'Access Token Expiry Date/Time',
  `refreshtoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `refreshtokenexpiry` datetime NOT NULL COMMENT 'Refresh Token Expiry Date/Time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Sessions Table';

--
-- Dumping data for table `tblsessions`
--

INSERT INTO `tblsessions` (`id`, `userid`, `accesstoken`, `accesstokenexpiry`, `refreshtoken`, `refreshtokenexpiry`) VALUES
(6, 1, 'ZWFlMTgwNmJjMzI5NmQyYmFkNzU3MDQ5ZWU0YWM0MGNjYmEzN2NhNGQ2YjViZDQ3MTcxMzc5NzY4OQ==', '2024-04-22 17:14:49', 'ZjEyZjZiOTliNjgwN2E3NjU0ZTRmODhiZDAyMWU2NTc0NDAyYTNiYWRjZmU0NGRkMTcxMzc5NzY4OQ==', '2024-04-09 16:54:49');

-- --------------------------------------------------------

--
-- Table structure for table `tbltasks`
--

CREATE TABLE `tbltasks` (
  `id` bigint(20) NOT NULL COMMENT 'Task ID - Primary Key',
  `title` varchar(255) NOT NULL COMMENT 'Task Title',
  `description` mediumtext DEFAULT NULL COMMENT 'Task Description',
  `deadline` datetime DEFAULT NULL COMMENT 'Task Deadline State',
  `completed` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Task Completion State',
  `userid` bigint(20) DEFAULT NULL COMMENT 'User ID of task owner'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tbltasks`
--

INSERT INTO `tbltasks` (`id`, `title`, `description`, `deadline`, `completed`, `userid`) VALUES
(9, 'test', 'a description', '2024-04-26 17:03:02', 'N', 1);

-- --------------------------------------------------------

--
-- Table structure for table `tblusers`
--

CREATE TABLE `tblusers` (
  `id` bigint(20) NOT NULL COMMENT 'User ID',
  `fullname` varchar(255) NOT NULL COMMENT 'Users full Name',
  `username` varchar(255) NOT NULL COMMENT 'Users Username',
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Users Password',
  `useractive` enum('N','Y') NOT NULL DEFAULT 'Y' COMMENT 'Is User Active',
  `loginattempts` int(1) NOT NULL DEFAULT 0 COMMENT 'Attempts To Log In'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='Users Table';

--
-- Dumping data for table `tblusers`
--

INSERT INTO `tblusers` (`id`, `fullname`, `username`, `password`, `useractive`, `loginattempts`) VALUES
(1, 'Peter Marcelis', 'JustcallmePete', '$2y$10$40opPGeSS/CbcQYmcFzO/OtkBaIkVCruT8z10/yxeQ.DX5OrfdV8q', 'Y', 0),
(3, 'Peter Marcelis', 'justcallmepeter', '$2y$10$psJfDCfR1j5CVpH6omFz9O6ct75yxuJ3nRckUAO2aOvHM/SbYTxq2', 'Y', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `accesstoken` (`accesstoken`),
  ADD UNIQUE KEY `refreshtoken` (`refreshtoken`),
  ADD KEY `sessionuserid_fk` (`userid`);

--
-- Indexes for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taskuserid_fk` (`userid`);

--
-- Indexes for table `tblusers`
--
ALTER TABLE `tblusers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblsessions`
--
ALTER TABLE `tblsessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Session ID', AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tbltasks`
--
ALTER TABLE `tbltasks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'Task ID - Primary Key', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tblusers`
--
ALTER TABLE `tblusers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT 'User ID', AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD CONSTRAINT `sessionuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD CONSTRAINT `taskuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
