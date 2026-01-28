-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: May 29, 2025 at 09:39 AM
-- Server version: 5.7.34
-- PHP Version: 8.0.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `visitor_mgmt`
--

-- --------------------------------------------------------

--
-- Table structure for table `otp_links`
--

CREATE TABLE `otp_links` (
  `otp_id` int(11) NOT NULL,
  `visitor_id` int(11) NOT NULL,
  `otp_token` varchar(255) NOT NULL,
  `otp_code_hash` varchar(255) NOT NULL,
  `is_used` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `last_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `otp_links`
--


-- Sample data omitted intentionally for security reasons.

-- --------------------------------------------------------

--
-- Table structure for table `owners`
--

CREATE TABLE `owners` (
  `owner_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `house_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `owners`
--


-- Seed owners data as needed.

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guard') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `guards` (
  `guard_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ic_number` varchar(30) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `shift` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--


-- Seed user accounts via the application to ensure password hashes follow the current algorithm.

-- --------------------------------------------------------

--
-- Table structure for table `visitors`
--

CREATE TABLE `visitors` (
  `visitor_id` int(11) NOT NULL,
  `name` text NOT NULL,
  `ic_number` text NOT NULL,
  `plate_number` text DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `category` varchar(20) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `guard_id` int(11) DEFAULT NULL,
  `status` enum('Pending','Allowed','Rejected','Overdue','Checked Out') DEFAULT 'Pending',
  `visit_duration_minutes` int(11) NOT NULL DEFAULT 0,
  `expected_checkout` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `allowed_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `visitors`
--


-- Visitor records should be inserted via application logic to ensure field-level encryption.

--
-- Indexes for dumped tables
--

--
-- Indexes for table `otp_links`
--
ALTER TABLE `otp_links`
  ADD PRIMARY KEY (`otp_id`),
  ADD KEY `visitor_id` (`visitor_id`);

--
-- Indexes for table `owners`
--
ALTER TABLE `owners`
  ADD PRIMARY KEY (`owner_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `guards`
  ADD PRIMARY KEY (`guard_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `visitors`
--
ALTER TABLE `visitors`
  ADD PRIMARY KEY (`visitor_id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `guard_id` (`guard_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `otp_links`
--
ALTER TABLE `otp_links`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `owners`
--
ALTER TABLE `owners`
  MODIFY `owner_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `guards`
  MODIFY `guard_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `visitors`
--
ALTER TABLE `visitors`
  MODIFY `visitor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `otp_links`
--
ALTER TABLE `otp_links`
  ADD CONSTRAINT `otp_links_ibfk_1` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`visitor_id`);

--
-- Constraints for table `visitors`
--
ALTER TABLE `visitors`
  ADD CONSTRAINT `visitors_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `owners` (`owner_id`);

ALTER TABLE `visitors`
  ADD CONSTRAINT `visitors_ibfk_2` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`guard_id`) ON DELETE SET NULL;

ALTER TABLE `guards`
  ADD CONSTRAINT `guards_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
