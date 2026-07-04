-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 04, 2026 at 09:47 PM
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
-- Database: `tourvolt`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `business_name`, `logo_path`, `created_at`) VALUES
(1, 'African Breathtaking Adventure', NULL, '2026-07-04 14:55:52');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `account_id`, `name`, `phone`, `email`, `notes`, `created_at`) VALUES
(1, 1, 'Marta Ellison', '+44 7700 900001', 'marta.ellison@example.com', 'Prefers smaller group camps, celebrating anniversary.', '2026-07-04 14:55:52'),
(2, 1, 'Daniel Kwizera', '+255 754 000002', 'd.kwizera@example.com', 'Repeat client, referred by a past guest.', '2026-07-04 14:55:52'),
(3, 1, 'The Alverson family', '+1 415 555 0103', 'alversons@example.com', 'Two kids (8 and 11) - needs family-friendly lodges.', '2026-07-04 14:55:52'),
(4, 1, 'Priya Nair', '+91 98200 00004', 'priya.nair@example.com', 'Photography-focused, wants early game drives.', '2026-07-04 14:55:52');

-- --------------------------------------------------------

--
-- Table structure for table `cost_lines`
--

CREATE TABLE `cost_lines` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `category` enum('hotel','guide','vehicle','park_fee','fuel','other') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cost_lines`
--

INSERT INTO `cost_lines` (`id`, `trip_id`, `category`, `description`, `amount`) VALUES
(1, 1, 'hotel', 'Serengeti lodge, 3 nights', 1800000.00),
(2, 1, 'hotel', 'Zanzibar beach resort, 3 nights', 1350000.00),
(3, 1, 'guide', 'Emmanuel Sanga, 4 days', 400000.00),
(4, 1, 'vehicle', 'Land Cruiser hire + fuel', 600000.00),
(5, 1, 'park_fee', 'Serengeti park fees, 4 pax', 700000.00),
(6, 1, 'other', 'Zanzibar flight transfer', 500000.00),
(7, 2, 'park_fee', 'Ngorongoro crater fees, 2 pax', 220000.00),
(8, 2, '', 'Isaya Lengai, day rate', 80000.00),
(9, 2, 'fuel', 'Fuel, Arusha-Ngorongoro round trip', 120000.00),
(10, 3, 'hotel', 'Tarangire + Manyara lodges, estimate', 2100000.00),
(11, 3, 'park_fee', 'Park fees, 5 pax, estimate', 900000.00),
(12, 4, 'hotel', 'Serengeti mobile camp, 6 nights', 2600000.00),
(13, 4, 'guide', 'Grace Mmasi, 6 days', 600000.00),
(14, 4, 'vehicle', 'Land Cruiser hire + fuel', 900000.00),
(15, 4, 'park_fee', 'Park fees, 2 pax', 500000.00);

-- --------------------------------------------------------

--
-- Table structure for table `itinerary_items`
--

CREATE TABLE `itinerary_items` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `day_number` int(11) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `itinerary_items`
--

INSERT INTO `itinerary_items` (`id`, `trip_id`, `day_number`, `activity`, `location`, `notes`) VALUES
(1, 1, 1, 'Arrival + transfer to lodge', 'Arusha', 'Pick up from JRO airport'),
(2, 1, 2, 'Game drive, full day', 'Serengeti National Park', NULL),
(3, 1, 3, 'Game drive + sundowner', 'Serengeti National Park', NULL),
(4, 1, 4, 'Fly to Zanzibar', 'Zanzibar', 'Morning flight from Seronera airstrip'),
(5, 1, 5, 'Stone Town tour', 'Zanzibar', NULL),
(6, 1, 6, 'Beach day', 'Nungwi, Zanzibar', NULL),
(7, 1, 7, 'Departure', 'Zanzibar', 'Transfer to airport'),
(8, 2, 1, 'Crater day tour', 'Ngorongoro Crater', 'Early departure, packed lunch included'),
(9, 4, 1, 'arrival', 'reception', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('cash','mpesa','bank','other') DEFAULT 'cash',
  `type` enum('deposit','balance','refund') DEFAULT 'deposit'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `trip_id`, `amount`, `payment_date`, `method`, `type`) VALUES
(1, 1, 2000000.00, '2026-06-01', 'bank', 'deposit'),
(2, 2, 420000.00, '2026-07-01', 'mpesa', 'balance'),
(3, 4, 3000000.00, '2026-05-20', 'bank', 'deposit'),
(4, 4, 1600000.00, '2026-06-09', 'mpesa', 'balance');

-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `type` enum('guide','driver','vehicle') NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resources`
--

INSERT INTO `resources` (`id`, `account_id`, `type`, `name`, `contact`) VALUES
(1, 1, 'guide', 'Emmanuel Sanga', '+255 754 111001'),
(2, 1, 'guide', 'Grace Mmasi', '+255 754 111002'),
(3, 1, 'driver', 'Isaya Lengai', '+255 754 111003'),
(4, 1, 'vehicle', 'Land Cruiser - T123 ABC', NULL),
(5, 1, 'vehicle', 'Land Cruiser - T456 DEF', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `template_cost_lines`
--

CREATE TABLE `template_cost_lines` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `template_itinerary_items`
--

CREATE TABLE `template_itinerary_items` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `day_number` int(11) NOT NULL,
  `activity` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `destination` varchar(200) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `pax` int(11) DEFAULT 1,
  `status` enum('inquiry','confirmed','in_progress','completed','cancelled') DEFAULT 'inquiry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`id`, `account_id`, `client_id`, `destination`, `start_date`, `end_date`, `pax`, `status`, `created_at`) VALUES
(1, 1, 1, 'Serengeti + Zanzibar', '2026-07-14', '2026-07-21', 4, 'confirmed', '2026-07-04 14:55:52'),
(2, 1, 2, 'Ngorongoro day trip', '2026-07-16', '2026-07-16', 2, 'confirmed', '2026-07-04 14:55:52'),
(3, 1, 3, 'Tarangire + Lake Manyara', '2026-07-22', '2026-07-25', 5, 'inquiry', '2026-07-04 14:55:52'),
(4, 1, 4, 'Serengeti photography safari', '2026-06-10', '2026-06-16', 2, 'completed', '2026-07-04 14:55:52');

-- --------------------------------------------------------

--
-- Table structure for table `trip_resources`
--

CREATE TABLE `trip_resources` (
  `trip_id` int(11) NOT NULL,
  `resource_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `trip_resources`
--

INSERT INTO `trip_resources` (`trip_id`, `resource_id`) VALUES
(1, 1),
(1, 4),
(2, 3),
(2, 4),
(4, 1),
(4, 2),
(4, 4),
(4, 5);

-- --------------------------------------------------------

--
-- Table structure for table `trip_templates`
--

CREATE TABLE `trip_templates` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `default_pax` int(11) DEFAULT 1,
  `status` varchar(50) DEFAULT 'inquiry',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('owner','staff') DEFAULT 'owner',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `account_id`, `name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(1, 1, 'James Mollel', 'owner@breathtaking.co.tz', '$2b$10$SVyd4Ij55amw1wBnwzLdKef72CuYWBSXP5hw9KYEazXTOnAJRb7C.', 'owner', '2026-07-04 14:55:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `cost_lines`
--
ALTER TABLE `cost_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `itinerary_items`
--
ALTER TABLE `itinerary_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `trip_id` (`trip_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `template_cost_lines`
--
ALTER TABLE `template_cost_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `template_itinerary_items`
--
ALTER TABLE `template_itinerary_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `trip_resources`
--
ALTER TABLE `trip_resources`
  ADD PRIMARY KEY (`trip_id`,`resource_id`),
  ADD KEY `resource_id` (`resource_id`);

--
-- Indexes for table `trip_templates`
--
ALTER TABLE `trip_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `account_id` (`account_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cost_lines`
--
ALTER TABLE `cost_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `itinerary_items`
--
ALTER TABLE `itinerary_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `resources`
--
ALTER TABLE `resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `template_cost_lines`
--
ALTER TABLE `template_cost_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `template_itinerary_items`
--
ALTER TABLE `template_itinerary_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `trip_templates`
--
ALTER TABLE `trip_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `clients_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cost_lines`
--
ALTER TABLE `cost_lines`
  ADD CONSTRAINT `cost_lines_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `itinerary_items`
--
ALTER TABLE `itinerary_items`
  ADD CONSTRAINT `itinerary_items_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resources`
--
ALTER TABLE `resources`
  ADD CONSTRAINT `resources_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `template_cost_lines`
--
ALTER TABLE `template_cost_lines`
  ADD CONSTRAINT `template_cost_lines_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `trip_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `template_itinerary_items`
--
ALTER TABLE `template_itinerary_items`
  ADD CONSTRAINT `template_itinerary_items_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `trip_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);

--
-- Constraints for table `trip_resources`
--
ALTER TABLE `trip_resources`
  ADD CONSTRAINT `trip_resources_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_resources_ibfk_2` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `trip_templates`
--
ALTER TABLE `trip_templates`
  ADD CONSTRAINT `trip_templates_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
