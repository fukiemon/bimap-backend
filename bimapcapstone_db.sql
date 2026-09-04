-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 29, 2026 at 12:25 PM
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
-- Database: `bimapcapstone_db`
--

CREATE DATABASE IF NOT EXISTS `bimapcapstone_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bimapcapstone_db`;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `action` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `name`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'admin', '', 'admin@bimap.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-04-20 05:08:25'),
(2, 'admin 2', 'admin2', 'admin2@gmail.com', '$2y$10$GPYrGZ2BSZJ87YQGPhQTiuf67cMO5o9ZPtT4Eg4IkFbPR2oZMGsCO', '2026-04-26 09:57:05');

-- --------------------------------------------------------

--
-- Table structure for table `announcement`
--

CREATE TABLE `announcement` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `target_audience` enum('residents','drivers','all') DEFAULT 'all',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `complaint`
--

CREATE TABLE `complaint` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `resident_name` varchar(255) NOT NULL DEFAULT 'Unknown Resident',
  `resident_phone` varchar(50) NOT NULL DEFAULT 'No phone',
  `barangay` varchar(100) NOT NULL DEFAULT 'Not specified',
  `concern` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Resolved') NOT NULL DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `photo_data` longtext DEFAULT NULL,
  `waste_type` varchar(100) DEFAULT NULL,
  `location_str` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaint`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `driver`
--

CREATE TABLE `driver` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `driver_license` varchar(50) DEFAULT NULL,
  `license_photo` varchar(255) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `assigned_barangay` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `driver_location`
--

CREATE TABLE `driver_location` (
  `truck_id` varchar(50) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(10,8) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_location`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `driver_message`
--

CREATE TABLE `driver_message` (
  `id` int(11) NOT NULL,
  `driver_id` int(11) NOT NULL,
  `driver_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `sender` enum('driver','admin') NOT NULL DEFAULT 'driver',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_message`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) DEFAULT NULL,
  `resident_name` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `collected` enum('yes','no') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `user_type` enum('resident','driver') NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `registered_driver`
--

CREATE TABLE `registered_driver` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `driver_license` varchar(50) DEFAULT NULL,
  `license_photo` varchar(255) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `assigned_barangay` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registered_driver`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `registered_resident`
--

CREATE TABLE `registered_resident` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registered_resident`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `resident`
--

CREATE TABLE `resident` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resident`
--
-- (intentionally empty — no sample data)

-- --------------------------------------------------------

--
-- Table structure for table `resident_hidden_announcement`
--

CREATE TABLE `resident_hidden_announcement` (
  `id` int(11) NOT NULL,
  `resident_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `hidden_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resident_hidden_announcement`
--
-- (intentionally empty — no sample data)

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcement`
--
ALTER TABLE `announcement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `complaint`
--
ALTER TABLE `complaint`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `driver`
--
ALTER TABLE `driver`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `driver_location`
--
ALTER TABLE `driver_location`
  ADD PRIMARY KEY (`truck_id`);

--
-- Indexes for table `driver_message`
--
ALTER TABLE `driver_message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_driver` (`driver_id`),
  ADD KEY `idx_sender` (`sender`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resident_id` (`resident_id`);

--
-- Indexes for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registered_driver`
--
ALTER TABLE `registered_driver`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registered_resident`
--
ALTER TABLE `registered_resident`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `resident`
--
ALTER TABLE `resident`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `resident_hidden_announcement`
--
ALTER TABLE `resident_hidden_announcement`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_hide` (`resident_id`,`announcement_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `announcement`
--
ALTER TABLE `announcement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint`
--
ALTER TABLE `complaint`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver`
--
ALTER TABLE `driver`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `driver_message`
--
ALTER TABLE `driver_message`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset`
--
ALTER TABLE `password_reset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registered_driver`
--
ALTER TABLE `registered_driver`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registered_resident`
--
ALTER TABLE `registered_resident`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resident`
--
ALTER TABLE `resident`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `resident_hidden_announcement`
--
ALTER TABLE `resident_hidden_announcement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcement`
--
ALTER TABLE `announcement`
  ADD CONSTRAINT `announcement_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `complaint`
--
ALTER TABLE `complaint`
  ADD CONSTRAINT `complaint_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `resident` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`resident_id`) REFERENCES `resident` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
