-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 19, 2025 at 12:06 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tesda_inventory`
--
CREATE DATABASE IF NOT EXISTS tesda_inventory DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tesda_inventory;
-- --------------------------------------------------------

--
-- Table structure for table `ics`
--

DROP TABLE IF EXISTS `ics`;
CREATE TABLE IF NOT EXISTS `ics` (
  `ics_id` int NOT NULL AUTO_INCREMENT,
  `ics_no` varchar(50) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `fund_cluster` varchar(100) NOT NULL,
  `date_issued` date NOT NULL,
  `received_by` varchar(255) NOT NULL,
  `received_by_position` varchar(255) NOT NULL,
  `received_from` varchar(255) NOT NULL,
  `received_from_position` varchar(255) NOT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ics_id`),
  UNIQUE KEY `unique_ics_no` (`ics_no`)
) ENGINE=MyISAM AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ics_history`
--

DROP TABLE IF EXISTS `ics_history`;
CREATE TABLE IF NOT EXISTS `ics_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ics_id` int NOT NULL,
  `ics_item_id` int DEFAULT NULL,
  `stock_number` varchar(100) DEFAULT NULL,
  `description` text,
  `unit` varchar(50) DEFAULT NULL,
  `quantity_before` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `quantity_after` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `quantity_change` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_cost_before` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_cost_after` decimal(15,2) NOT NULL DEFAULT '0.00',
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `reference_details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ics_id` (`ics_id`),
  KEY `idx_ics_item_id` (`ics_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ics_items`
--

DROP TABLE IF EXISTS `ics_items`;
CREATE TABLE IF NOT EXISTS `ics_items` (
  `ics_item_id` int NOT NULL AUTO_INCREMENT,
  `ics_id` int NOT NULL,
  `stock_number` varchar(50) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `inventory_item_no` varchar(100) DEFAULT NULL,
  `estimated_useful_life` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`ics_item_id`),
  KEY `ics_id` (`ics_id`),
  KEY `idx_stock_number` (`stock_number`)
) ENGINE=MyISAM AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ict_registry`
--

DROP TABLE IF EXISTS `ict_registry`;
CREATE TABLE IF NOT EXISTS `ict_registry` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `reference_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `property_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `useful_life` int NOT NULL DEFAULT '0',
  `issued_qty` int NOT NULL DEFAULT '0',
  `issued_officer` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `returned_qty` int NOT NULL DEFAULT '0',
  `returned_officer` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reissued_qty` int NOT NULL DEFAULT '0',
  `reissued_officer` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disposed_qty` int NOT NULL DEFAULT '0',
  `balance_qty` int NOT NULL DEFAULT '0',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_property_no` (`property_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `iirusp`
--

DROP TABLE IF EXISTS `iirusp`;
CREATE TABLE IF NOT EXISTS `iirusp` (
  `iirusp_id` int NOT NULL AUTO_INCREMENT,
  `iirusp_no` varchar(50) NOT NULL,
  `as_at` date NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `fund_cluster` varchar(100) DEFAULT NULL,
  `accountable_officer_name` varchar(255) DEFAULT NULL,
  `accountable_officer_designation` varchar(255) DEFAULT NULL,
  `accountable_officer_station` varchar(255) DEFAULT NULL,
  `requested_by` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `inspection_officer` varchar(255) DEFAULT NULL,
  `witness` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`iirusp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `iirusp_history`
--

DROP TABLE IF EXISTS `iirusp_history`;
CREATE TABLE IF NOT EXISTS `iirusp_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `iirusp_id` int NOT NULL,
  `iirusp_item_id` int DEFAULT NULL,
  `semi_expendable_property_no` varchar(100) DEFAULT NULL,
  `particulars` text,
  `quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `accumulated_impairment` decimal(15,2) NOT NULL DEFAULT '0.00',
  `carrying_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `remarks` text,
  `disposal_sale` decimal(15,2) NOT NULL DEFAULT '0.00',
  `disposal_transfer` decimal(15,2) NOT NULL DEFAULT '0.00',
  `disposal_destruction` decimal(15,2) NOT NULL DEFAULT '0.00',
  `disposal_others` text,
  `disposal_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `appraised_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `or_no` varchar(100) DEFAULT NULL,
  `sales_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_iirusp_id` (`iirusp_id`),
  KEY `idx_iirusp_item_id` (`iirusp_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `iirusp_items`
--

DROP TABLE IF EXISTS `iirusp_items`;
CREATE TABLE IF NOT EXISTS `iirusp_items` (
  `iirusp_item_id` int NOT NULL AUTO_INCREMENT,
  `iirusp_id` int NOT NULL,
  `date_acquired` date DEFAULT NULL,
  `particulars` text,
  `semi_expendable_property_no` varchar(100) DEFAULT NULL,
  `quantity` int DEFAULT '0',
  `unit` varchar(50) DEFAULT NULL,
  `unit_cost` decimal(15,2) DEFAULT '0.00',
  `total_cost` decimal(15,2) DEFAULT '0.00',
  `accumulated_impairment` decimal(15,2) DEFAULT '0.00',
  `carrying_amount` decimal(15,2) DEFAULT '0.00',
  `remarks` text,
  `disposal_sale` decimal(15,2) DEFAULT '0.00',
  `disposal_transfer` decimal(15,2) DEFAULT '0.00',
  `disposal_destruction` decimal(15,2) DEFAULT '0.00',
  `disposal_others` varchar(255) DEFAULT NULL,
  `disposal_total` decimal(15,2) DEFAULT '0.00',
  `appraised_value` decimal(15,2) DEFAULT '0.00',
  `or_no` varchar(100) DEFAULT NULL,
  `sales_amount` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`iirusp_item_id`),
  KEY `iirusp_id` (`iirusp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_entries`
--

DROP TABLE IF EXISTS `inventory_entries`;
CREATE TABLE IF NOT EXISTS `inventory_entries` (
  `entry_id` int NOT NULL AUTO_INCREMENT,
  `item_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`entry_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=292 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_entries`
--

INSERT INTO `inventory_entries` (`entry_id`, `item_id`, `quantity`, `unit_cost`, `created_at`, `is_active`) VALUES
(291, 4, -1, 0.00, '2025-10-06 02:36:13', 1);

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
CREATE TABLE IF NOT EXISTS `items` (
  `item_id` int NOT NULL AUTO_INCREMENT,
  `stock_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `item_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `reorder_point` int DEFAULT NULL,
  `parent_item_id` int DEFAULT NULL,
  `quantity_on_hand` int DEFAULT '0',
  `unit_cost` decimal(10,4) DEFAULT NULL,
  `initial_quantity` int DEFAULT '0',
  `average_unit_cost` decimal(10,4) DEFAULT NULL,
  `calculated_unit_cost` decimal(10,4) DEFAULT NULL,
  `calculated_quantity` int DEFAULT NULL,
  `iar` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=223 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `stock_number`, `item_name`, `description`, `unit`, `reorder_point`, `parent_item_id`, `quantity_on_hand`, `unit_cost`, `initial_quantity`, `average_unit_cost`, `calculated_unit_cost`, `calculated_quantity`, `iar`) VALUES
(3, 'A.03.a', 'ALCOHOL', '70% ethy/isopropyl, with moisturizer, gallon', 'gallon', 0, NULL, 1, 314.5000, 1, 0.0000, NULL, NULL, 'Beg Bal'),
(4, 'A.03.b', 'ALCOHOL', '70% ethyl/isopropyl, 500ml', 'bottle', 0, NULL, 9, 55.6200, 10, 55.6200, 55.6200, 10, '25-06'),
(5, 'B.01.a', 'BATTERY', 'dry cell, AA, 4pcs/pack, 1.5V, heavy duty', 'pack', 0, NULL, 7, 77.1500, 7, 0.0000, NULL, NULL, 'Beg Bal'),
(6, 'B.01.b', 'BATTERY', 'dry cell, AAA, 4pcs/pack, 1.5V, heavy duty', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(7, 'B.01.c', 'BATTERY', 'dry cell, 9V1', 'pc', 0, NULL, 8, 204.5000, 8, 0.0000, NULL, NULL, 'Beg Bal'),
(8, 'B.01.d', 'BATTERY', 'Li-on for thermo scanner', 'pack', 0, NULL, 3, 65.0000, 3, 0.0000, NULL, NULL, 'Beg Bal'),
(9, 'B.02.a', 'BLEACH', 'Bleach, detergent', 'pack', 0, NULL, 2, 145.2000, 2, 0.0000, NULL, NULL, 'Beg Bal'),
(10, 'C.01.a', 'CALCULATOR', 'Calculator', 'pc', 0, NULL, 1, 271.7200, 1, 0.0000, NULL, NULL, 'Beg Bal'),
(11, 'C.02.a', 'CERTIFICATE HOLDER', 'Certifcate Holder, A4', 'pc', 0, NULL, 3, 38.3000, 3, 0.0000, NULL, NULL, 'Beg Bal'),
(12, 'C.03.a', 'CLIP', 'backfold, large, 41mm, 12pcs/box', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(13, 'C.03.b', 'CLIP', 'backfold, medium, 25mm, 12pcs/box', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(14, 'C.03.c', 'CLIP', 'backfold, small, 19mm, 12pcs/box', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(15, 'C.03.d', 'CLIP', 'backfold, extra small, 15mm, 12pcs/box', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(16, 'C.04.a', 'CORRECTION TAPE', 'film based', 'pc', 0, NULL, 4, 14.5400, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(17, 'C.05.a', 'CUTTER PAPER', 'blade/knife', 'pc', 0, NULL, 7, 63.3900, 7, 0.0000, NULL, NULL, 'Beg Bal'),
(18, 'C.06.a', 'CLING WRAP', '12inches x 300meters', 'roll', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(19, 'D.01.a', 'DISHWASHING LIQUID', '500ml', 'bottle', 0, NULL, 4, 113.6600, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(20, 'D.02.a', 'DISINFECTANT SPRAY', 'aerosol type', 'can', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(21, 'D.03.a', 'DRAWER LOCK', 'set with key', 'set', 0, NULL, 4, 250.0000, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(22, 'E.01.a', 'ENVELOPE EXPANDABLE', 'brown, long', 'pc', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(23, 'F.01.a', 'FASTENER', 'plastic', 'box', 0, NULL, 3, 30.0000, 3, 0.0000, NULL, NULL, 'Beg Bal'),
(24, 'F.02.a', 'FOLDER', 'Tag Board, White, 100pcs/pack, Long', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(25, 'F.02.b', 'FOLDER EXPANDING', 'Long, pressboard 100pcs/pack, white & blue', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(26, 'F.03.a', 'FABRIC CONDITIONER', 'Softener', 'gallon', 0, NULL, 3, 158.2000, 3, 0.0000, NULL, NULL, 'Beg Bal'),
(27, 'G.01.a', 'GLUE STICK', 'all purpose, 22 grams,', 'pc', 0, NULL, 15, 80.0000, 15, 0.0000, NULL, NULL, 'Beg Bal'),
(28, 'G.02.a', 'GLASS CLEANER', 'with Spray cap 500ml', 'bottle', 0, NULL, 4, 192.2300, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(29, 'H.01.a', 'HANDSOAP', 'Liquid, 500ml', 'btl', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(30, 'I.01.a', 'INDEX TAB', 'Index Tab', 'box', 0, NULL, 16, 85.0000, 16, 0.0000, NULL, NULL, 'Beg Bal'),
(31, 'I.02.a', 'INK', 'Canon, GI 790, Magenta', 'bottle', 0, NULL, 4, 282.5000, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(32, 'I.02.b', 'INK', 'Canon, GI 790, Yellow', 'bottle', 0, NULL, 1, 295.0000, 1, 0.0000, NULL, NULL, 'Beg Bal'),
(33, 'I.02.c', 'INK', 'Canon, GI 790, Black', 'bottle', 0, NULL, 2, 337.5000, 2, 0.0000, NULL, NULL, 'Beg Bal'),
(34, 'I.02.d', 'INK', 'Canon, GI 790, Cyan', 'bottle', 0, NULL, 2, 282.5000, 2, 0.0000, NULL, NULL, 'Beg Bal'),
(35, 'I.03.a', 'INK', 'HP 682, black', 'cart', 0, NULL, 5, 513.0400, 5, 0.0000, NULL, NULL, 'Beg Bal'),
(36, 'I.03.b', 'INK', 'HP 682, colored', 'cart', 0, NULL, 6, 516.0800, 6, 0.0000, NULL, NULL, 'Beg Bal'),
(37, 'I.04.a', 'INK', 'Ink Cart, Canon, 810 Black', 'cart', 0, NULL, 5, 980.0000, 5, 0.0000, NULL, NULL, 'Beg Bal'),
(38, 'I.04.b', 'INK', 'Ink Cart, Canon, 811 Colored', 'cart', 0, NULL, 9, 1099.4600, 9, 0.0000, NULL, NULL, 'Beg Bal'),
(39, 'I.05.a', 'INK', 'Epson L3110/Epson 003, Black', 'bottle', 0, NULL, 23, 255.9100, 23, 0.0000, NULL, NULL, 'Beg Bal'),
(40, 'I.05.b', 'INK', 'Epson L3110/ Epson 003, Cyan', 'bottle', 0, NULL, 13, 249.7000, 13, 0.0000, NULL, NULL, 'Beg Bal'),
(41, 'I.05.c', 'INK', 'Epson L3110/Epson 003, Magenta', 'bottle', 0, NULL, 13, 249.7000, 13, 0.0000, NULL, NULL, 'Beg Bal'),
(42, 'I.05.d', 'INK', 'Epson L3110/Epson 003, Yellow', 'bottle', 0, NULL, 14, 250.8400, 14, 0.0000, NULL, NULL, 'Beg Bal'),
(43, 'I.06.a', 'INSECTICIDE', 'Aerosol type, waterbased, 600ml/can', 'can', 0, NULL, 8, 370.1900, 8, 0.0000, NULL, NULL, 'Beg Bal'),
(44, 'K.01.a', 'KITCHEN TOWEL', 'Paper Towel, roll, 2ply', 'roll', 0, NULL, 6, 79.3700, 6, 0.0000, NULL, NULL, 'Beg Bal'),
(45, 'L.01.a', 'LED BULB', 'LED 22-30 Watts', 'pc', 0, NULL, 30, 319.0000, 30, 0.0000, NULL, NULL, '25-11'),
(47, 'N.02.a', 'NOTE PAD', 'stick on, 2\"x3\"', 'pc', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(48, 'N.02.b', 'NOTE PAD', 'stick on, 3\"x3\"', 'pad', 0, NULL, 10, 18.0800, 10, 0.0000, NULL, NULL, 'Beg Bal'),
(49, 'N.02.c', 'NOTE PAD', 'stick on, 4\"x3\"', 'pad', 0, NULL, 7, 27.6400, 7, 0.0000, NULL, NULL, 'Beg Bal'),
(50, 'N.02.d', 'NOTE PAD', 'stick on, d3-4 (4\'s -1\"x3\")', 'pc', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(51, 'P.01.a', 'PAPER', 'Board, A4, white, 180gsm, 100sheets/pack', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(52, 'P.01.b', 'PAPER', 'Board, A4, white, 200gsm, 100sheets/pack', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(53, 'P.01.c', 'PAPER', 'Board, Morocco, A4, 200gsm, 100sheets/pack', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(54, 'P.02.a', 'PAPER CLIP', '50mm, jumbo, vinyl coated', 'box', 0, NULL, 12, 20.0400, 12, 0.0000, NULL, NULL, 'Beg Bal'),
(55, 'P.02.b', 'PAPER CLIP', '33mm, vinyl coated', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(56, 'P.03.a', 'PAPER', 'Multicopy, PPC, s20, 8.5\" x 13\"', 'ream', 0, NULL, 8, 214.2500, 8, 0.0000, NULL, NULL, 'Beg Bal'),
(57, 'P.03.b', 'PAPER', 'Multicopy, PPC, s20, 8.5\" x 14\"', 'ream', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(58, 'P.03.c', 'PAPER', 'Multicopy, PPC, s20, A4', 'ream', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(59, 'P.03.d', 'PAPER', 'Multicopy, PPC, s20, Short', 'ream', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(60, 'P.04.a', 'PEN SIGN', 'gel or liquid ink, retractable, 0.7mm Black/ Blue, 12pcs/box', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(61, 'p.04.b', 'PEN SIGN', 'Hi-tecpoint V10Grip, 1.0, 12pcs/box, Black/Blue', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(62, 'P.04.c', 'PEN', 'ballpoint, retractable, 0.7mm, Black/Blue', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(63, 'P.04.d', 'PEN', 'Fine, Retractable, 0.5mm', 'pc', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(64, 'P.05.a', 'POST IT- Sticky Note', '\"Sign Here\", \"Please Sign\",', 'pack', 0, NULL, 12, 45.3200, 12, 0.0000, NULL, NULL, 'Beg Bal'),
(65, 'P.06.a', 'PUSH PINS', '100pcs/box', 'box', 0, NULL, 1, 28.0000, 1, 0.0000, NULL, NULL, 'Beg Bal'),
(66, 'R.01.a', 'RECORD BOOK', 'Logbook, 300 pages', 'pc', 0, NULL, 15, 146.3100, 15, 0.0000, NULL, NULL, 'Beg Bal'),
(67, 'R.02.a', 'RULER', 'Steel, 12 inches', 'pc', 0, NULL, 6, 40.0000, 6, 0.0000, NULL, NULL, 'Beg Bal'),
(68, 'R.03.a', 'RAGS', 'Rags', 'kilo', 0, NULL, 10, 70.8100, 10, 0.0000, NULL, NULL, 'Beg Bal'),
(69, 'S.01.a', 'STAPLER', '', 'pc', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(70, 'S.01.b', 'STAPLE WIRE', 'Standard, 5000 staples/box', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(71, 'S.01.c', 'STAPLE WIRE', 'Bostitch, 5000 staples/box', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(72, 'S.01.d', 'STAPLER REMOVER', 'Staple remover', 'pc', 0, NULL, 5, 49.5700, 5, 0.0000, NULL, NULL, 'Beg Bal'),
(73, 'S.02.a', 'SCOURING PAD', 'Dishwashing sponge', 'pc', 0, NULL, 4, 59.0400, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(74, 'T.01.a', 'TAPE', 'clear, 1inch', 'roll', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(75, 'T.01.b', 'TAPE', 'Cloth, Duct tape', 'roll', 0, NULL, 4, 64.0000, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(76, 'T.01.c', 'TAPE', 'double sided, 1inch', 'roll', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(77, 'T.01.d', 'TAPE', 'Packing, 2\"', 'roll', 0, NULL, 4, 35.6200, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(78, 'T.01.e', 'TAPE', 'transparent, 2\"', 'roll', 0, NULL, 5, 44.0000, 5, 0.0000, NULL, NULL, 'Beg Bal'),
(79, 'T.01.f', 'TAPE', 'transparent, 3\"', 'roll', 0, NULL, 8, 36.0000, 8, 0.0000, NULL, NULL, 'Beg Bal'),
(80, 'T.02.a', 'TAPE', 'refill for Epson LW-K400 printer/label 12mm', 'pcs', 0, NULL, 18, 442.8500, 18, 0.0000, NULL, NULL, 'Beg Bal'),
(81, 'T.03.a', 'TAPE DISPENSER', 'Tape Dispenser', 'pc', 0, NULL, 2, 105.0000, 2, 0.0000, NULL, NULL, 'Beg Bal'),
(82, 'T.04.a', 'TOILET BOWL BRUSH', 'round headed brush', 'pc', 0, NULL, 4, 90.0000, 4, 0.0000, NULL, NULL, 'Beg Bal'),
(83, 'T.04.b', 'TOILET BOWL CLEANER', 'Liquid, 900ml', 'bottle', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(84, 'T.05.a', 'TISSUE BATHROOM', 'Green Tea, 180g, 10pcs/pack', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(85, 'T.05.b', 'TISSUE FACIAL', 'Econo Box, 2ply, 200-250pulls', 'box', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(86, 'T.05.c', 'TOILET TISSUE PAPER', '2ply, 12\'s per pack, 1000 sheets per roll', 'pack', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(87, 'U.01.a', 'USB', 'Flash Drive, 64GB', 'pc', 0, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(214, 'B.03.a', 'Ballpen', 'Ballpen 0.5, fine, retractable', 'pc', 0, NULL, 27, 28.0000, 27, 28.0000, 28.0000, 27, 'Beg Bal'),
(215, 'B.03.b', 'Ballpen', 'Ballpen, sign pen, 0.7mm, hi-techpoint V&RT (black/blue)', 'pc', 0, NULL, 13, 68.0000, 13, 68.0000, 68.0000, 13, 'Beg Bal'),
(216, 'C.03.e', 'CLIP', 'Clip All, 1\"', 'box', 0, NULL, 3, 19.0000, 3, 19.0000, 19.0000, 3, 'Beg Bal'),
(217, 'F.04.a', 'FLASH DRIVE', 'USB 64GB', 'pc', 0, NULL, 2, 425.0000, 2, 425.0000, 425.0000, 2, 'Beg Bal'),
(218, 'S.03.a', 'SEAL', 'Notarial Seal, gold no. 24', 'pack', 0, NULL, 6, 39.0000, 6, 39.0000, 39.0000, 6, 'Beg Bal'),
(220, 'A.2.a', 'DISINFECTANT ', 'Air Freshener', 'can', 0, NULL, 7, 162.0000, 7, 162.0000, 162.0000, 7, 'Beg Bal'),
(221, 'A.01.a', 'ARCHFILE   ', 'Tagila Lock size 3\"x9\"x15 blue and black', 'pc', 0, NULL, 23, 129.0000, 23, 129.0000, 129.0000, 23, 'Beg Bal');

-- --------------------------------------------------------

--
-- Table structure for table `item_history`
--

DROP TABLE IF EXISTS `item_history`;
CREATE TABLE IF NOT EXISTS `item_history` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `stock_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `item_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reorder_point` int DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `quantity_on_hand` int DEFAULT NULL,
  `quantity_change` int DEFAULT NULL,
  `change_direction` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `change_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'update',
  `ris_id` int DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `item_id` (`item_id`),
  KEY `fk_item_history_ris` (`ris_id`)
) ENGINE=InnoDB AUTO_INCREMENT=675 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_history`
--

INSERT INTO `item_history` (`history_id`, `item_id`, `stock_number`, `item_name`, `description`, `unit`, `reorder_point`, `unit_cost`, `quantity_on_hand`, `quantity_change`, `change_direction`, `changed_at`, `change_type`, `ris_id`) VALUES
(599, 4, 'A.03.b', 'ALCOHOL', '70% ethyl/isopropyl, 500ml', 'bottle', 0, 0.00, 1, 1, 'increase', '2025-09-04 14:38:14', 'update', NULL),
(600, 3, 'A.03.a', 'ALCOHOL', '70% ethy/isopropyl, with moisturizer, gallon', 'gallon', 0, 0.00, 1, 1, 'increase', '2025-09-04 14:38:53', 'update', NULL),
(603, 214, 'B.03.a', 'Ballpen', 'Ballpen 0.5, fine, retractable', 'pc', 0, 28.00, 27, 27, 'increase', '2025-09-04 14:43:34', 'add', NULL),
(604, 215, 'B.03.b', 'Ballpen', 'Ballpen, sign pen, 0.7mm, hi-techpoint V&RT (black/blue)', 'pc', 0, 68.00, 13, 13, 'increase', '2025-09-04 14:45:32', 'add', NULL),
(605, 5, 'B.01.a', 'BATTERY', 'dry cell, AA, 4pcs/pack, 1.5V, heavy duty', 'pack', 0, 0.00, 7, 7, 'increase', '2025-09-04 14:47:10', 'update', NULL),
(606, 7, 'B.01.c', 'BATTERY', 'dry cell, 9V1', 'pc', 0, 0.00, 0, 0, 'no_change', '2025-09-04 14:47:45', 'update', NULL),
(607, 7, 'B.01.c', 'BATTERY', 'dry cell, 9V1', 'pc', 0, 0.00, 8, 8, 'increase', '2025-09-04 14:47:50', 'update', NULL),
(608, 8, 'B.01.d', 'BATTERY', 'Li-on for thermo scanner', 'pack', 0, 0.00, 3, 3, 'increase', '2025-09-04 14:48:22', 'update', NULL),
(609, 9, 'B.02.a', 'BLEACH', 'Bleach, detergent', 'pack', 0, 0.00, 2, 2, 'increase', '2025-09-04 14:49:11', 'update', NULL),
(610, 10, 'C.01.a', 'CALCULATOR', 'Calculator', 'pc', 0, 0.00, 1, 1, 'increase', '2025-09-04 14:49:49', 'update', NULL),
(611, 11, 'C.02.a', 'CERTIFICATE HOLDER', 'Certifcate Holder, A4', 'pc', 0, 0.00, 3, 3, 'increase', '2025-09-04 14:50:30', 'update', NULL),
(612, 216, 'C.03.e', 'CLIP', 'Clip All, 1\"', 'box', 0, 19.00, 3, 3, 'increase', '2025-09-04 14:53:42', 'add', NULL),
(613, 16, 'C.04.a', 'CORRECTION TAPE', 'film based', 'pc', 0, 0.00, 4, 4, 'increase', '2025-09-04 14:54:14', 'update', NULL),
(614, 17, 'C.05.a', 'CUTTER PAPER', 'blade/knife', 'pc', 0, 0.00, 7, 7, 'increase', '2025-09-04 14:54:46', 'update', NULL),
(615, 19, 'D.01.a', 'DISHWASHING LIQUID', '500ml', 'bottle', 0, 0.00, 4, 4, 'increase', '2025-09-04 14:55:16', 'update', NULL),
(616, 21, 'D.03.a', 'DRAWER LOCK', 'set with key', 'set', 0, 0.00, 4, 4, 'increase', '2025-09-04 14:56:18', 'update', NULL),
(617, 39, 'I.05.a', 'INK', 'Epson L3110/Epson 003, Black', 'bottle', 0, 0.00, 23, 23, 'increase', '2025-09-04 14:57:28', 'update', NULL),
(618, 41, 'I.05.c', 'INK', 'Epson L3110/Epson 003, Magenta', 'bottle', 0, 0.00, 13, 13, 'increase', '2025-09-04 14:58:01', 'update', NULL),
(619, 40, 'I.05.b', 'INK', 'Epson L3110/ Epson 003, Cyan', 'bottle', 0, 0.00, 13, 13, 'increase', '2025-09-04 14:58:34', 'update', NULL),
(620, 42, 'I.05.d', 'INK', 'Epson L3110/Epson 003, Yellow', 'bottle', 0, 0.00, 0, 0, 'no_change', '2025-09-04 14:59:05', 'update', NULL),
(621, 42, 'I.05.d', 'INK', 'Epson L3110/Epson 003, Yellow', 'bottle', 0, 0.00, 14, 14, 'increase', '2025-09-04 14:59:11', 'update', NULL),
(622, 26, 'F.03.a', 'FABRIC CONDITIONER', 'Softener', 'gallon', 0, 0.00, 3, 3, 'increase', '2025-09-04 14:59:50', 'update', NULL),
(623, 23, 'F.01.a', 'FASTENER', 'plastic', 'box', 0, 0.00, 3, 3, 'increase', '2025-09-04 15:00:21', 'update', NULL),
(624, 217, 'F.04.a', 'FLASH DRIVE', 'USB 64GB', 'pc', 0, 425.00, 2, 2, 'increase', '2025-09-04 15:02:02', 'add', NULL),
(625, 28, 'G.02.a', 'GLASS CLEANER', 'with Spray cap 500ml', 'bottle', 0, 0.00, 4, 4, 'increase', '2025-09-04 15:02:38', 'update', NULL),
(626, 27, 'G.01.a', 'GLUE STICK', 'all purpose, 22 grams,', 'pc', 0, 0.00, 15, 15, 'increase', '2025-09-04 15:03:10', 'update', NULL),
(627, 31, 'I.02.a', 'INK', 'Canon, GI 790, Magenta', 'bottle', 0, 0.00, 0, 0, 'no_change', '2025-09-04 15:03:37', 'update', NULL),
(628, 31, 'I.02.a', 'INK', 'Canon, GI 790, Magenta', 'bottle', 0, 0.00, 4, 4, 'increase', '2025-09-04 15:03:38', 'update', NULL),
(629, 32, 'I.02.b', 'INK', 'Canon, GI 790, Yellow', 'bottle', 0, 0.00, 1, 1, 'increase', '2025-09-04 15:04:01', 'update', NULL),
(630, 33, 'I.02.c', 'INK', 'Canon, GI 790, Black', 'bottle', 0, 0.00, 2, 2, 'increase', '2025-09-04 15:04:20', 'update', NULL),
(631, 34, 'I.02.d', 'INK', 'Canon, GI 790, Cyan', 'bottle', 0, 0.00, 2, 2, 'increase', '2025-09-04 15:04:44', 'update', NULL),
(632, 37, 'I.04.a', 'INK', 'Ink Cart, Canon, 810 Black', 'cart', 0, 0.00, 5, 5, 'increase', '2025-09-04 15:05:28', 'update', NULL),
(633, 38, 'I.04.b', 'INK', 'Ink Cart, Canon, 811 Colored', 'cart', 0, 0.00, 9, 9, 'increase', '2025-09-04 15:05:59', 'update', NULL),
(634, 35, 'I.03.a', 'INK', 'HP 682, black', 'cart', 0, 0.00, 5, 5, 'increase', '2025-09-04 15:06:32', 'update', NULL),
(635, 36, 'I.03.b', 'INK', 'HP 682, colored', 'cart', 0, 0.00, 6, 6, 'increase', '2025-09-04 15:06:58', 'update', NULL),
(636, 30, 'I.01.a', 'INDEX TAB', 'Index Tab', 'box', 0, 0.00, 16, 16, 'increase', '2025-09-04 15:07:26', 'update', NULL),
(637, 43, 'I.06.a', 'INSECTICIDE', 'Aerosol type, waterbased, 600ml/can', 'can', 0, 0.00, 8, 8, 'increase', '2025-09-04 15:07:52', 'update', NULL),
(638, 43, 'I.06.a', 'INSECTICIDE', 'Aerosol type, waterbased, 600ml/can', 'can', 0, 0.00, 8, 8, 'increase', '2025-09-04 15:07:55', 'update', NULL),
(639, 218, 'S.03.a', 'SEAL', 'Notarial Seal, gold no. 24', 'pack', 0, 39.00, 6, 6, 'increase', '2025-09-04 15:15:47', 'add', NULL),
(640, 48, 'N.02.b', 'NOTE PAD', 'stick on, 3\"x3\"', 'pad', 0, 0.00, 10, 10, 'increase', '2025-09-04 15:17:15', 'update', NULL),
(641, 49, 'N.02.c', 'NOTE PAD', 'stick on, 4\"x3\"', 'pad', 0, 0.00, 7, 7, 'increase', '2025-09-04 15:17:43', 'update', NULL),
(642, 54, 'P.02.a', 'PAPER CLIP', '50mm, jumbo, vinyl coated', 'box', 0, 0.00, 12, 12, 'increase', '2025-09-04 15:18:15', 'update', NULL),
(643, 56, 'P.03.a', 'PAPER', 'Multicopy, PPC, s20, 8.5\" x 13\"', 'ream', 0, 0.00, 8, 8, 'increase', '2025-09-04 15:19:01', 'update', NULL),
(644, 44, 'K.01.a', 'KITCHEN TOWEL', 'Paper Towel, roll, 2ply', 'roll', 0, 0.00, 6, 6, 'increase', '2025-09-04 15:19:52', 'update', NULL),
(645, 65, 'P.06.a', 'PUSH PINS', '100pcs/box', 'box', 0, 0.00, 1, 1, 'increase', '2025-09-04 15:20:17', 'update', NULL),
(646, 68, 'R.03.a', 'RAGS', 'Rags', 'kilo', 0, 0.00, 10, 10, 'increase', '2025-09-04 15:23:47', 'update', NULL),
(647, 66, 'R.01.a', 'RECORD BOOK', 'Logbook, 300 pages', 'pc', 0, 0.00, 15, 15, 'increase', '2025-09-04 15:29:03', 'update', NULL),
(648, 67, 'R.02.a', 'RULER', 'Steel, 12 inches', 'pc', 0, 0.00, 6, 6, 'increase', '2025-09-04 15:29:24', 'update', NULL),
(649, 73, 'S.02.a', 'SCOURING PAD', 'Dishwashing sponge', 'pc', 0, 0.00, 4, 4, 'increase', '2025-09-04 15:29:51', 'update', NULL),
(650, 72, 'S.01.d', 'STAPLER REMOVER', 'Staple remover', 'pc', 0, 0.00, 5, 5, 'increase', '2025-09-04 15:30:33', 'update', NULL),
(651, 64, 'P.05.a', 'POST IT- Sticky Note', '\"Sign Here\", \"Please Sign\",', 'pack', 0, 0.00, 12, 12, 'increase', '2025-09-04 15:31:12', 'update', NULL),
(652, 81, 'T.03.a', 'TAPE DISPENSER', 'Tape Dispenser', 'pc', 0, 0.00, 2, 2, 'increase', '2025-09-04 15:31:41', 'update', NULL),
(653, 75, 'T.01.b', 'TAPE', 'Cloth, Duct tape', 'roll', 0, 0.00, 4, 4, 'increase', '2025-09-04 15:32:03', 'update', NULL),
(654, 77, 'T.01.d', 'TAPE', 'Packing, 2\"', 'roll', 0, 0.00, 4, 4, 'increase', '2025-09-04 15:32:28', 'update', NULL),
(655, 78, 'T.01.e', 'TAPE', 'transparent, 2\"', 'roll', 0, 0.00, 5, 5, 'increase', '2025-09-04 15:32:54', 'update', NULL),
(656, 79, 'T.01.f', 'TAPE', 'transparent, 3\"', 'roll', 0, 0.00, 8, 8, 'increase', '2025-09-04 15:33:18', 'update', NULL),
(657, 80, 'T.02.a', 'TAPE', 'refill for Epson LW-K400 printer/label 12mm', 'pcs', 0, 0.00, 18, 18, 'increase', '2025-09-04 15:33:47', 'update', NULL),
(658, 82, 'T.04.a', 'TOILET BOWL BRUSH', 'round headed brush', 'pc', 0, 0.00, 4, 4, 'increase', '2025-09-04 15:34:14', 'update', NULL),
(667, 220, 'A.2.a', 'DISINFECTANT ', 'Air Freshener', 'can', 0, 162.00, 7, 7, 'increase', '2025-09-04 16:11:44', 'add', NULL),
(669, 45, 'L.01.a', 'LED BULB', 'LED 22-30 Watts', 'pc', 0, 0.00, 30, 30, 'increase', '2025-09-07 10:16:48', 'update', NULL),
(670, 4, 'A.03.b', 'ALCOHOL', '70% ethyl/isopropyl, 500ml', 'bottle', 0, 0.00, 10, 10, 'increase', '2025-09-07 10:18:41', 'update', NULL),
(671, 4, 'A.03.b', 'ALCOHOL', '70% ethyl/isopropyl, 500ml', 'bottle', 0, 0.00, 9, -1, 'decrease', '2025-10-06 10:36:13', 'issued', 31),
(673, 221, 'A.01.a', 'ARCHFILE   ', 'Tagila Lock size 3\"x9\"x15 blue and black', 'pc', 0, 129.00, 24, 24, 'increase', '2025-10-10 11:50:33', 'update', NULL),
(674, 221, 'A.01.a', 'ARCHFILE   ', 'Tagila Lock size 3\"x9\"x15 blue and black', 'pc', 0, 129.00, 23, 23, 'increase', '2025-10-10 11:50:55', 'update', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `item_history_archive`
--

DROP TABLE IF EXISTS `item_history_archive`;
CREATE TABLE IF NOT EXISTS `item_history_archive` (
  `history_id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `stock_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `item_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `reorder_point` int DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `quantity_on_hand` int DEFAULT NULL,
  `quantity_change` int DEFAULT NULL,
  `change_direction` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `changed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `change_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'update',
  `reference_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `item_id` (`item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=669 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_history_archive`
--

INSERT INTO `item_history_archive` (`history_id`, `item_id`, `stock_number`, `item_name`, `description`, `unit`, `reorder_point`, `unit_cost`, `quantity_on_hand`, `quantity_change`, `change_direction`, `changed_at`, `change_type`, `reference_id`) VALUES
(1, 1, 'A.01.a', NULL, 'Tagila Lock', 'pc', 10, 12.00, 20, 10, '0', '2025-08-05 14:33:26', 'entry', NULL),
(2, 1, 'A.01.a', NULL, 'Tagila Lock', 'pc', 10, 12.00, 31, 11, '0', '2025-08-05 14:33:39', 'entry', NULL),
(4, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 10, 12.00, 30, 10, 'increase', '2025-08-05 14:59:53', 'entry', NULL),
(15, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 10, 12.00, 30, 0, 'no_change', '2025-08-05 16:39:25', 'cleared', NULL),
(92, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 12.00, 30, 0, 'no_change', '2025-08-06 01:43:49', 'update', NULL),
(416, 2, 'A.02.a', 'AIR FRESHINER REFILL', 'Automatic Spray Refill(glade)', 'can', 0, 10.00, 10, 0, 'no_change', '2025-08-06 20:28:58', 'update', NULL),
(417, 2, 'A.02.a', 'AIR FRESHINER REFILL', 'Automatic Spray Refill(glade)', 'can', 0, 11.00, 11, 1, 'increase', '2025-08-06 20:29:28', 'update', NULL),
(418, 2, 'A.02.a', 'AIR FRESHINER REFILL', 'Automatic Spray Refill(glade)', 'can', 0, 11.00, 23, 12, 'increase', '2025-08-06 20:31:56', 'entry', NULL),
(419, 2, 'A.02.a', 'AIR FRESHINER REFILL', 'Automatic Spray Refill(glade)', 'can', 0, 11.55, 22, -1, 'decrease', '2025-08-06 20:39:14', 'cleared', NULL),
(420, 2, 'A.02.a', 'AIR FRESHINER REFILL', 'Automatic Spray Refill(glade)', 'can', 0, 10.00, 10, -12, 'decrease', '2025-08-06 20:39:23', 'update', NULL),
(460, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 12.00, 0, -30, 'decrease', '2025-08-06 23:55:35', 'update', NULL),
(469, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 12.00, 12, 12, 'increase', '2025-08-07 00:05:55', 'selective_update', NULL),
(470, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 12.00, 22, 10, 'increase', '2025-08-07 00:06:26', 'entry', NULL),
(471, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 11.09, 22, 0, 'no_change', '2025-08-07 00:06:33', 'cleared', NULL),
(472, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 10.00, 10, -12, 'decrease', '2025-08-07 00:06:45', 'selective_update', NULL),
(490, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 11.00, 11, 1, 'increase', '2025-08-07 00:38:56', 'selective_update', NULL),
(592, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock123123', 'pc', 11, 11.00, 11, 0, 'no_change', '2025-09-03 15:43:19', 'update', NULL),
(593, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, 11.00, 11, 0, 'no_change', '2025-09-03 15:45:42', 'update', NULL),
(595, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 0, 11.00, 11, 0, 'no_change', '2025-09-04 14:35:45', 'update', NULL),
(596, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 0, 0.00, 0, 0, 'no_change', '2025-09-04 14:35:52', 'update', NULL),
(597, 2, 'A.02.a', 'Disinfectant', 'Air Freshener', 'can', 0, 162.17, 7, 7, 'increase', '2025-09-04 14:37:13', 'update', NULL),
(598, 2, 'A.02.a', 'Disinfectant', 'Air Freshener', 'can', 0, 162.17, 7, 7, 'increase', '2025-09-04 14:37:22', 'update', NULL),
(601, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock size 3\"x9\"x15 blue and black', 'pc', 0, 129.08, 23, 23, 'increase', '2025-09-04 14:39:44', 'update', NULL),
(602, 1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock size 3\"x9\"x15 blue and black', 'pc', 0, 129.08, 23, 0, 'no_change', '2025-09-04 14:39:55', 'update', NULL),
(661, 2, 'A.02.a', 'Disinfectant', 'Air Freshener', 'can', 0, 162.17, 8, 8, 'increase', '2025-09-04 15:46:28', 'update', NULL),
(662, 2, 'A.02.a', 'Disinfectant', 'Air Freshener', 'can', 0, 162.17, 7, 7, 'increase', '2025-09-04 15:46:41', 'update', NULL),
(663, 2, 'A.02.a', 'Disinfectant', 'Air Freshener', 'can', 0, 162.17, 0, 0, 'no_change', '2025-09-04 15:47:30', 'update', NULL),
(664, 2, 'A.02.a', 'Disinfectant', 'Air Freshener', 'can', 0, 162.17, 7, 7, 'increase', '2025-09-04 15:47:40', 'update', NULL),
(668, 221, 'A.01.a', 'ARCHFILE   ', 'Tagila Lock size 3\"x9\"x15 blue and black', 'pc', 0, 129.00, 23, 23, 'increase', '2025-09-04 16:14:34', 'add', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `itr`
--

DROP TABLE IF EXISTS `itr`;
CREATE TABLE IF NOT EXISTS `itr` (
  `itr_id` int NOT NULL AUTO_INCREMENT,
  `itr_no` varchar(32) NOT NULL,
  `itr_date` date NOT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `fund_cluster` varchar(255) DEFAULT NULL,
  `from_accountable` varchar(255) DEFAULT NULL,
  `to_accountable` varchar(255) DEFAULT NULL,
  `transfer_type` varchar(50) DEFAULT NULL,
  `transfer_other` varchar(255) DEFAULT NULL,
  `reason` text,
  `remarks` text,
  `approved_name` varchar(255) DEFAULT NULL,
  `approved_designation` varchar(255) DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `released_name` varchar(255) DEFAULT NULL,
  `released_designation` varchar(255) DEFAULT NULL,
  `released_date` date DEFAULT NULL,
  `received_name` varchar(255) DEFAULT NULL,
  `received_designation` varchar(255) DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_ics_no` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`itr_id`),
  UNIQUE KEY `itr_no_unique` (`itr_no`)
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `itr_history`
--

DROP TABLE IF EXISTS `itr_history`;
CREATE TABLE IF NOT EXISTS `itr_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `itr_id` int NOT NULL,
  `itr_item_id` int DEFAULT NULL,
  `ics_id` int DEFAULT NULL,
  `ics_item_id` int DEFAULT NULL,
  `item_no` varchar(255) DEFAULT NULL,
  `stock_number` varchar(100) DEFAULT NULL,
  `description` text,
  `unit` varchar(50) DEFAULT NULL,
  `transfer_qty` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `from_accountable` varchar(255) DEFAULT NULL,
  `to_accountable` varchar(255) DEFAULT NULL,
  `transfer_type` varchar(50) DEFAULT NULL,
  `transfer_other` varchar(255) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `reference_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_itr_id` (`itr_id`),
  KEY `idx_itr_item_id` (`itr_item_id`),
  KEY `idx_ics_item_id` (`ics_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `itr_items`
--

DROP TABLE IF EXISTS `itr_items`;
CREATE TABLE IF NOT EXISTS `itr_items` (
  `itr_item_id` int NOT NULL AUTO_INCREMENT,
  `itr_id` int NOT NULL,
  `date_acquired` date DEFAULT NULL,
  `item_no` varchar(255) DEFAULT NULL,
  `ics_info` varchar(255) DEFAULT NULL,
  `description` text,
  `amount` decimal(15,2) DEFAULT '0.00',
  `transfer_qty` int NOT NULL DEFAULT '0',
  `ics_id` int DEFAULT NULL,
  `ics_item_id` int DEFAULT NULL,
  `cond` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`itr_item_id`),
  KEY `fk_itr_items_itr` (`itr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_cards`
--

DROP TABLE IF EXISTS `property_cards`;
CREATE TABLE IF NOT EXISTS `property_cards` (
  `pc_id` int NOT NULL AUTO_INCREMENT,
  `entity_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fund_cluster` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ppe_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Property, Plant and Equipment type',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Description of the PPE (brand, size, color, serial no., etc.)',
  `property_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Number assigned by Supply/Property Division',
  `transaction_date` date NOT NULL COMMENT 'Date of acquisition/issue/transfer/disposal',
  `reference_par_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reference document or PAR number',
  `receipt_qty` decimal(10,2) DEFAULT '0.00' COMMENT 'Quantity received',
  `issue_qty` decimal(10,2) DEFAULT '0.00' COMMENT 'Quantity issued/transferred/disposed',
  `office_officer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Receiving office/officer name',
  `amount` decimal(15,2) DEFAULT '0.00' COMMENT 'Amount of PPE',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Important information or comments',
  `transaction_type` enum('receipt','issue','transfer','disposal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'receipt',
  `created_by` int DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pc_id`),
  KEY `idx_entity_fund` (`entity_name`,`fund_cluster`),
  KEY `idx_property_number` (`property_number`),
  KEY `idx_ppe_type` (`ppe_type`),
  KEY `idx_transaction_date` (`transaction_date`),
  KEY `idx_pc_compound` (`entity_name`,`fund_cluster`,`ppe_type`,`transaction_date`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_cards`
--

INSERT INTO `property_cards` (`pc_id`, `entity_name`, `fund_cluster`, `ppe_type`, `description`, `property_number`, `transaction_date`, `reference_par_no`, `receipt_qty`, `issue_qty`, `office_officer`, `amount`, `remarks`, `transaction_type`, `created_by`, `date_created`, `last_updated`) VALUES
(1, 'Department of Education', '01', 'Office Equipment', 'Desktop Computer - Dell OptiPlex 3070, Intel Core i5, 8GB RAM, 256GB SSD, Serial: DL123456', 'PPE-2024-001', '2024-01-15', 'IAR-2024-001', 1.00, 0.00, NULL, 45000.00, 'Brand new unit for Admin Office', 'receipt', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(2, 'Department of Education', '01', 'Office Equipment', 'Desktop Computer - Dell OptiPlex 3070, Intel Core i5, 8GB RAM, 256GB SSD, Serial: DL123456', 'PPE-2024-001', '2024-01-20', 'PAR-2024-001', 0.00, 1.00, 'Admin Office - John Doe', 45000.00, 'Issued to Admin Office', 'issue', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(3, 'Department of Education', '01', 'Furniture and Fixtures', 'Office Chair - Ergonomic Swivel Chair, Black Leather, Model: EC-2024', 'PPE-2024-002', '2024-01-16', 'IAR-2024-002', 5.00, 0.00, NULL, 12500.00, 'Set of 5 office chairs', 'receipt', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(4, 'Department of Education', '01', 'Furniture and Fixtures', 'Office Chair - Ergonomic Swivel Chair, Black Leather, Model: EC-2024', 'PPE-2024-002', '2024-01-22', 'PAR-2024-002', 0.00, 3.00, 'HR Department - Jane Smith', 7500.00, 'Issued 3 chairs to HR Dept', 'issue', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(5, 'Department of Education', '02', 'IT Equipment', 'Printer - HP LaserJet Pro M404dn, Monochrome, Network Ready, Serial: HP789012', 'PPE-2024-003', '2024-01-18', 'IAR-2024-003', 2.00, 0.00, NULL, 24000.00, 'Network printers for offices', 'receipt', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(6, 'Department of Education', '02', 'IT Equipment', 'Printer - HP LaserJet Pro M404dn, Monochrome, Network Ready, Serial: HP789012', 'PPE-2024-003', '2024-01-25', 'PAR-2024-003', 0.00, 1.00, 'Finance Office - Mike Johnson', 12000.00, 'Assigned to Finance Office', 'issue', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(7, 'Department of Education', '01', 'Appliances', 'Air Conditioning Unit - Split Type 1.5HP, Inverter, Brand: Samsung, Model: AR12NVFXAWKNEU', 'PPE-2024-004', '2024-01-20', 'IAR-2024-004', 1.00, 0.00, NULL, 35000.00, 'For conference room installation', 'receipt', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(8, 'Department of Education', '01', 'Office Equipment', 'Filing Cabinet - 4-Drawer Steel Cabinet, Gray Color, with Lock', 'PPE-2024-005', '2024-01-22', 'IAR-2024-005', 3.00, 0.00, NULL, 18000.00, 'Storage cabinets for documents', 'receipt', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(9, 'Department of Education', '01', 'Office Equipment', 'Filing Cabinet - 4-Drawer Steel Cabinet, Gray Color, with Lock', 'PPE-2024-005', '2024-01-28', 'PAR-2024-004', 0.00, 2.00, 'Records Office - Sarah Wilson', 12000.00, 'Transferred to Records Office', 'transfer', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(10, 'Department of Education', '02', 'IT Equipment', 'Laptop Computer - Lenovo ThinkPad E14, Intel i7, 16GB RAM, 512GB SSD', 'PPE-2024-006', '2024-02-01', 'IAR-2024-006', 4.00, 0.00, NULL, 200000.00, 'Mobile workstations for staff', 'receipt', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22'),
(11, 'Department of Education', '02', 'IT Equipment', 'Laptop Computer - Lenovo ThinkPad E14, Intel i7, 16GB RAM, 512GB SSD', 'PPE-2024-006', '2024-02-05', 'PAR-2024-005', 0.00, 2.00, 'IT Department - Alex Brown', 100000.00, 'Issued to IT staff for field work', 'issue', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22');

-- --------------------------------------------------------

--
-- Table structure for table `regspi`
--

DROP TABLE IF EXISTS `regspi`;
CREATE TABLE IF NOT EXISTS `regspi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_name` varchar(255) NOT NULL,
  `fund_cluster` varchar(100) DEFAULT NULL,
  `semi_expendable_property` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `regspi`
--

INSERT INTO `regspi` (`id`, `entity_name`, `fund_cluster`, `semi_expendable_property`, `created_at`, `updated_at`) VALUES
(19, 'TESDA Regional Office', 'b', NULL, '2025-11-07 06:38:28', NULL),
(20, 'TESDA Regional Office', '', NULL, '2025-11-12 02:48:17', NULL),
(21, 'TESDA Regional Office', 'qwe', NULL, '2025-11-12 02:57:38', NULL),
(22, 'TESDA Regional Office', 'cv', NULL, '2025-11-12 03:07:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `regspi_entries`
--

DROP TABLE IF EXISTS `regspi_entries`;
CREATE TABLE IF NOT EXISTS `regspi_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regspi_id` int NOT NULL,
  `date` date NOT NULL,
  `ics_rrsp_no` varchar(100) NOT NULL,
  `property_no` varchar(100) NOT NULL,
  `item_description` text NOT NULL,
  `useful_life` varchar(100) NOT NULL,
  `issued_qty` int NOT NULL DEFAULT '0',
  `issued_office` varchar(255) DEFAULT NULL,
  `returned_qty` int NOT NULL DEFAULT '0',
  `returned_office` varchar(255) DEFAULT NULL,
  `reissued_qty` int NOT NULL DEFAULT '0',
  `reissued_office` varchar(255) DEFAULT NULL,
  `disposed_qty1` int NOT NULL DEFAULT '0',
  `disposed_qty2` int NOT NULL DEFAULT '0',
  `balance_qty` int NOT NULL DEFAULT '0',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_regspi_id` (`regspi_id`),
  KEY `idx_date` (`date`),
  KEY `idx_ics_rrsp_no` (`ics_rrsp_no`),
  KEY `idx_property_no` (`property_no`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `regspi_entries`
--

INSERT INTO `regspi_entries` (`id`, `regspi_id`, `date`, `ics_rrsp_no`, `property_no`, `item_description`, `useful_life`, `issued_qty`, `issued_office`, `returned_qty`, `returned_office`, `reissued_qty`, `reissued_office`, `disposed_qty1`, `disposed_qty2`, `balance_qty`, `amount`, `remarks`, `created_at`, `updated_at`) VALUES
(20, 19, '2025-11-07', '0001-11-2025', 'x', 'x', '5', 0, 'b', 0, NULL, 2, 'b', 0, 0, 7, 2000.00, '', '2025-11-07 06:38:28', NULL),
(21, 20, '2025-11-12', '0002-11-2025', 'x', 'x', '5', 0, 'asd', 0, NULL, 2, 'dasd', 0, 0, 7, 2000.00, '', '2025-11-12 02:48:17', NULL),
(22, 21, '2025-11-12', '0003-11-2025', 'x', 'x', '5', 0, 'qwe', 0, NULL, 2, 'qwe', 0, 0, 0, 2000.00, '', '2025-11-12 02:57:38', NULL),
(23, 20, '2025-11-12', '0004-11-2025', 'ZXC', 'zxc', '5', 0, 'zxc', 0, NULL, 1, 'tyu', 0, 0, 4, 1000.00, '', '2025-11-12 03:02:47', NULL),
(24, 22, '2025-11-12', '0005-11-2025', 'ZXC', 'zxc', '5', 0, 'cv', 0, NULL, 2, 'cv', 0, 0, 1, 2000.00, '', '2025-11-12 03:07:22', NULL),
(25, 20, '2025-11-12', '0001-11-2025', 'asd', 'asd', '5', 0, 'bnm', 0, NULL, 3, 'bnm', 0, 0, 5, 3000.00, '', '2025-11-12 03:22:37', NULL),
(26, 20, '2025-11-12', '0001-11-2025', '123', '123', '5', 0, '123', 0, NULL, 5, 'asd', 0, 0, 5, 5000.00, '', '2025-11-12 03:39:33', NULL),
(27, 20, '2025-11-12', '0001-11-2025', '123', '123', '5', 0, NULL, 0, NULL, 3, NULL, 0, 0, 0, 3000.00, '', '2025-11-12 05:04:48', NULL),
(28, 20, '2025-11-12', '0002-11-2025', '123', '123', '5', 0, '1', 0, NULL, 1, '1', 0, 0, 0, 1000.00, '', '2025-11-12 05:05:17', NULL),
(29, 20, '2025-11-12', '0001-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'a', 0, NULL, 1, 'c', 0, 0, 4, 20000.00, '', '2025-11-12 05:33:45', NULL),
(30, 20, '2025-11-12', '0002-11-2025', 'gasmon', 'laptop', '5', 0, 'qwee', 0, NULL, 1, 'qwe', 0, 0, 2, 10000.00, '', '2025-11-12 06:45:16', NULL),
(31, 20, '2025-11-18', '0001-11-2025', 'HV-200-101-10', 'yeah\r\n', '5', 0, '123', 0, NULL, 2, '123', 0, 0, 6, 20.00, '', '2025-11-18 02:15:44', NULL),
(32, 20, '2025-11-18', '0002-11-2025', 'HV-200-101-10', 'yeah\r\n', '5', 0, 'hjk', 0, NULL, 1, 'hjk', 0, 0, 6, 10.00, '', '2025-11-18 02:30:41', NULL),
(33, 20, '2025-11-18', '0003-11-2025', 'HV-200-101-15', 'Laptop', '5', 0, '111', 0, NULL, 4, '111', 0, 0, 3, 40000.00, '', '2025-11-18 02:52:17', NULL),
(34, 20, '2025-11-18', '0004-11-2025', 'HV-200-101-15', 'Laptop', '5', 0, 'asd', 0, NULL, 3, 'asd', 0, 0, 3, 30000.00, '', '2025-11-18 05:44:40', NULL),
(35, 20, '2025-11-18', '0005-11-2025', 'HV-200-101-10', 'yeah\r\n', '5', 0, '1112', 0, NULL, 1, '1112', 0, 0, 6, 10.00, '', '2025-11-18 05:50:37', NULL),
(36, 20, '2025-11-18', '0006-11-2025', '\\zxc', 'asd', '5', 0, 'aaa', 0, NULL, 1, 'aaa', 0, 0, 6, 1000.00, '', '2025-11-18 05:59:52', NULL),
(37, 20, '2025-11-18', '0007-11-2025', '\\zxc', 'asd', '5', 0, 'ss', 0, NULL, 1, 'ss', 0, 0, 6, 1000.00, '', '2025-11-18 06:00:03', NULL),
(38, 20, '2025-11-18', '0001-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'asd', 0, NULL, 3, 'zxc', 0, 0, 30, 3000.00, '', '2025-11-18 06:12:56', NULL),
(39, 20, '2025-11-18', '0002-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'zxc', 0, NULL, 3, 'zxc', 0, 0, 30, 3000.00, '', '2025-11-18 06:13:49', NULL),
(40, 20, '2025-11-18', '0003-11-2025', 'HV-200-101-11', 'asd', '5', 0, 'asd', 0, NULL, 2, 'asd', 0, 0, 8, 2246.00, '', '2025-11-18 06:14:41', NULL),
(41, 20, '2025-11-18', '0001-11-2025', 'HV-200-101-11', 'asd', '5', 0, 'asd', 0, NULL, 1, 'asd', 0, 0, 8, 1123.00, '', '2025-11-18 06:18:53', NULL),
(42, 20, '2025-11-18', '0001-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'zxc', 0, NULL, 3, 'zxc', 0, 0, 20, 3000.00, '', '2025-11-18 06:20:23', NULL),
(43, 20, '2025-11-18', '0002-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'zxc', 0, NULL, 2, 'zxc', 0, 0, 20, 2000.00, '', '2025-11-18 06:20:45', NULL),
(44, 20, '2025-11-18', '0003-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, '123', 0, NULL, 3, '123', 0, 0, 20, 3000.00, '', '2025-11-18 06:26:37', NULL),
(45, 20, '2025-11-18', '0004-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'xzcc', 0, NULL, 2, 'zxczxcx', 0, 0, 20, 2000.00, '', '2025-11-18 06:39:53', NULL),
(46, 20, '2025-11-18', '0005-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, '34', 0, NULL, 1, '2134', 0, 0, 20, 1000.00, '', '2025-11-18 06:41:02', NULL),
(47, 20, '2025-11-18', '0006-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'zxcz', 0, NULL, 3, 'zxczxczxc', 0, 0, 10, 3000.00, '', '2025-11-18 06:45:48', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ris`
--

DROP TABLE IF EXISTS `ris`;
CREATE TABLE IF NOT EXISTS `ris` (
  `ris_id` int NOT NULL AUTO_INCREMENT,
  `entity_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fund_cluster` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `division` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `office` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `responsibility_center_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ris_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_requested` date DEFAULT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `requested_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approved_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `received_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ris_id`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ris`
--

INSERT INTO `ris` (`ris_id`, `entity_name`, `fund_cluster`, `division`, `office`, `responsibility_center_code`, `ris_no`, `date_requested`, `purpose`, `requested_by`, `approved_by`, `issued_by`, `received_by`, `created_at`) VALUES
(31, 'a', 'f', 'FASD', 'TESDA CAR', 'f', '2025/10/0001', '2025-10-06', 'd', 'a', 'a', 'a', 'a', '2025-10-06 02:36:13');

-- --------------------------------------------------------

--
-- Table structure for table `ris_items`
--

DROP TABLE IF EXISTS `ris_items`;
CREATE TABLE IF NOT EXISTS `ris_items` (
  `item_id` int NOT NULL,
  `ris_id` int DEFAULT NULL,
  `stock_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stock_available` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_quantity` int DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `unit_cost_at_issue` decimal(10,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ris_items`
--

INSERT INTO `ris_items` (`item_id`, `ris_id`, `stock_number`, `stock_available`, `issued_quantity`, `remarks`, `unit_cost_at_issue`) VALUES
(0, 31, 'A.03.b', 'Yes', 1, '', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `rpci`
--

DROP TABLE IF EXISTS `rpci`;
CREATE TABLE IF NOT EXISTS `rpci` (
  `rpci_id` int NOT NULL,
  `inventory_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `as_of_date` date DEFAULT NULL,
  `fund_cluster` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `article` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stock_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `unit_value` decimal(10,2) DEFAULT NULL,
  `balance_per_card` int DEFAULT NULL,
  `on_hand_per_count` int DEFAULT NULL,
  `shortage` int DEFAULT '0',
  `overage` int DEFAULT '0',
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rrsp`
--

DROP TABLE IF EXISTS `rrsp`;
CREATE TABLE IF NOT EXISTS `rrsp` (
  `rrsp_id` int NOT NULL AUTO_INCREMENT,
  `rrsp_no` varchar(50) DEFAULT NULL,
  `date_prepared` date NOT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `fund_cluster` varchar(120) DEFAULT NULL,
  `returned_by` varchar(255) DEFAULT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `returned_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`rrsp_id`),
  UNIQUE KEY `rrsp_no` (`rrsp_no`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rrsp_history`
--

DROP TABLE IF EXISTS `rrsp_history`;
CREATE TABLE IF NOT EXISTS `rrsp_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rrsp_id` int NOT NULL,
  `rrsp_item_id` int DEFAULT NULL,
  `ics_no` varchar(100) DEFAULT NULL,
  `item_description` text,
  `quantity` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `unit_cost` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `end_user` varchar(255) DEFAULT NULL,
  `item_remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rrsp_id` (`rrsp_id`),
  KEY `idx_rrsp_item_id` (`rrsp_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rrsp_items`
--

DROP TABLE IF EXISTS `rrsp_items`;
CREATE TABLE IF NOT EXISTS `rrsp_items` (
  `rrsp_item_id` int NOT NULL AUTO_INCREMENT,
  `rrsp_id` int NOT NULL,
  `item_description` varchar(255) NOT NULL,
  `quantity` int DEFAULT '0',
  `ics_no` varchar(120) DEFAULT NULL,
  `end_user` varchar(255) DEFAULT NULL,
  `item_remarks` varchar(255) DEFAULT NULL,
  `unit_cost` decimal(12,2) DEFAULT '0.00',
  `total_amount` decimal(14,2) DEFAULT '0.00',
  PRIMARY KEY (`rrsp_item_id`),
  KEY `rrsp_id` (`rrsp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rsmi`
--

DROP TABLE IF EXISTS `rsmi`;
CREATE TABLE IF NOT EXISTS `rsmi` (
  `rsmi_id` int NOT NULL,
  `date_generated` datetime NOT NULL,
  `total_issued` int NOT NULL,
  `month_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rspi_items`
--

DROP TABLE IF EXISTS `rspi_items`;
CREATE TABLE IF NOT EXISTS `rspi_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rspi_id` int NOT NULL,
  `ics_no` varchar(50) NOT NULL,
  `responsibility_center_code` varchar(50) DEFAULT NULL,
  `property_no` varchar(50) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `quantity_issued` int DEFAULT '0',
  `unit_cost` decimal(12,2) DEFAULT '0.00',
  `amount` decimal(12,2) GENERATED ALWAYS AS ((`quantity_issued` * `unit_cost`)) STORED,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `rspi_id` (`rspi_id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rspi_items`
--

INSERT INTO `rspi_items` (`id`, `rspi_id`, `ics_no`, `responsibility_center_code`, `property_no`, `description`, `unit`, `quantity_issued`, `unit_cost`, `created_at`) VALUES
(1, 1, 'ICS-001', 'IT-001', 'PROP-001', 'Desktop Computer', 'unit', 5, 25000.00, '2025-10-27 05:55:30'),
(2, 1, 'ICS-002', 'IT-002', 'PROP-002', 'Printer', 'unit', 2, 8000.00, '2025-10-27 05:55:30'),
(3, 2, 'ICS-003', 'ADM-001', 'PROP-003', 'Office Chair', 'piece', 10, 1500.00, '2025-10-27 05:55:30');

-- --------------------------------------------------------

--
-- Table structure for table `rspi_reports`
--

DROP TABLE IF EXISTS `rspi_reports`;
CREATE TABLE IF NOT EXISTS `rspi_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `serial_no` varchar(20) NOT NULL,
  `entity_name` varchar(100) NOT NULL,
  `fund_cluster` varchar(50) NOT NULL,
  `report_date` date NOT NULL,
  `custodian_name` varchar(100) NOT NULL,
  `posted_by` varchar(100) DEFAULT NULL,
  `status` enum('draft','posted','archived') DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_no` (`serial_no`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rspi_reports`
--

INSERT INTO `rspi_reports` (`id`, `serial_no`, `entity_name`, `fund_cluster`, `report_date`, `custodian_name`, `posted_by`, `status`, `created_at`, `updated_at`) VALUES
(1, '2025-10-001', 'TESDA Provincial Office', '101', '2025-10-20', 'Juan Dela Cruz', 'Maria Santos', 'posted', '2025-10-27 05:55:30', '2025-10-27 05:55:30'),
(2, '2025-10-002', 'TESDA Provincial Office', '102', '2025-10-23', 'Pedro Reyes', 'Maria Santos', 'posted', '2025-10-27 05:55:30', '2025-10-27 05:55:30');

-- --------------------------------------------------------

--
-- Table structure for table `semi_expendable_history`
--

DROP TABLE IF EXISTS `semi_expendable_history`;
CREATE TABLE IF NOT EXISTS `semi_expendable_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `semi_id` int NOT NULL,
  `date` date DEFAULT NULL,
  `ics_rrsp_no` varchar(255) DEFAULT NULL,
  `quantity` int DEFAULT '0',
  `quantity_issued` int DEFAULT '0',
  `quantity_returned` int DEFAULT '0',
  `quantity_reissued` int DEFAULT '0',
  `quantity_disposed` int DEFAULT '0',
  `quantity_balance` int DEFAULT '0',
  `office_officer_issued` varchar(255) DEFAULT NULL,
  `office_officer_returned` varchar(255) DEFAULT NULL,
  `office_officer_reissued` varchar(255) DEFAULT NULL,
  `amount_total` decimal(15,2) DEFAULT '0.00',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `amount` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `semi_id` (`semi_id`)
) ENGINE=InnoDB AUTO_INCREMENT=331 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `semi_expendable_property`
--

DROP TABLE IF EXISTS `semi_expendable_property`;
CREATE TABLE IF NOT EXISTS `semi_expendable_property` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `ics_rrsp_no` varchar(50) NOT NULL,
  `semi_expendable_property_no` varchar(50) NOT NULL,
  `item_description` text NOT NULL,
  `unit` varchar(64) DEFAULT NULL,
  `estimated_useful_life` int NOT NULL,
  `quantity` int DEFAULT '0',
  `quantity_issued` int NOT NULL,
  `office_officer_issued` varchar(100) DEFAULT NULL,
  `quantity_returned` int DEFAULT '0',
  `office_officer_returned` varchar(100) DEFAULT NULL,
  `quantity_reissued` int DEFAULT '0',
  `office_officer_reissued` varchar(100) DEFAULT NULL,
  `quantity_disposed` int DEFAULT '0',
  `quantity_balance` int NOT NULL,
  `amount_total` decimal(15,2) NOT NULL,
  `category` varchar(50) NOT NULL,
  `fund_cluster` varchar(20) DEFAULT '101',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `amount` decimal(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_card`
--

DROP TABLE IF EXISTS `stock_card`;
CREATE TABLE IF NOT EXISTS `stock_card` (
  `stock_card_id` int NOT NULL,
  `item_id` int DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `receipt_qty` int DEFAULT '0',
  `issue_qty` int DEFAULT '0',
  `balance_qty` int DEFAULT NULL,
  `issued_to_office` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(250) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

ALTER TABLE users 
ADD remember_token VARCHAR(255) NULL;

ALTER TABLE users
ADD full_name VARCHAR(255) NULL;

ALTER TABLE users
ADD user_position VARCHAR(255) NULL;
-- --------------------------------------------------------

--
-- Table structure for table `ppe_property`
--

DROP TABLE IF EXISTS `ppe_property`;
CREATE TABLE IF NOT EXISTS `ppe_property` (
  `id` int NOT NULL AUTO_INCREMENT,
  `par_no` varchar(50) NOT NULL,
  `item_name` text NOT NULL,
  `item_description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `officer_incharge` varchar(255) NOT NULL,
  `quantity` int DEFAULT '1',
  `unit` varchar(50) DEFAULT 'unit',
  `custodian` varchar(255) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `date_acquired` date DEFAULT NULL,
  `condition` enum('Good','Fair','Poor','Unserviceable') DEFAULT 'Good',
  `status` enum('Active','Transferred','Returned','For Repair','Unserviceable','Disposed') DEFAULT 'Active',
  `fund_cluster` varchar(10) DEFAULT '101',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ppe_pc`
--

DROP TABLE IF EXISTS `ppe_pc`;
CREATE TABLE IF NOT EXISTS `ppe_pc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date_created` date NOT NULL,
  `par_no` varchar(50) NOT NULL,
  `ppe_property_no` varchar(50) NOT NULL,
  `item_name` text NOT NULL,
  `item_description` text NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `quantity` int DEFAULT '1',
  `unit` varchar(50) DEFAULT 'unit',
  `custodian` varchar(255) NOT NULL,
  `officer` varchar(255) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `ptr_no` varchar(50) DEFAULT NULL,
  `fund_cluster` varchar(10) DEFAULT '101',
  `remarks` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ppe_property_no` (`ppe_property_no`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ppe_ptr`
--

DROP TABLE IF EXISTS `ppe_ptr`;
CREATE TABLE IF NOT EXISTS `ppe_ptr` (
  `ptr_id` int NOT NULL AUTO_INCREMENT,
  `ptr_no` varchar(100) NOT NULL,
  `entity_name` varchar(255) DEFAULT 'TESDA Regional Office',
  `fund_cluster` varchar(100) DEFAULT '101',
  `from_officer` varchar(255) DEFAULT NULL,
  `to_officer` varchar(255) DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `transfer_type` varchar(100) DEFAULT NULL,
  `reason` text,
  `approved_by` varchar(255) DEFAULT NULL,
  `approved_by_designation` varchar(255) DEFAULT NULL,
  `approved_by_date` date DEFAULT NULL,
  `released_by` varchar(255) DEFAULT NULL,
  `released_by_designation` varchar(255) DEFAULT NULL,
  `released_by_date` date DEFAULT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `received_by_designation` varchar(255) DEFAULT NULL,
  `received_by_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ptr_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ppe_ptr_items`
--

DROP TABLE IF EXISTS `ppe_ptr_items`;
CREATE TABLE IF NOT EXISTS `ppe_ptr_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ptr_id` int NOT NULL,
  `ppe_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ptr_id` (`ptr_id`),
  KEY `ppe_id` (`ppe_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ppe_par`
--

DROP TABLE IF EXISTS `ppe_par`;
CREATE TABLE IF NOT EXISTS `ppe_par` (
  `par_id` int NOT NULL AUTO_INCREMENT,
  `par_no` varchar(100) NOT NULL,
  `entity_name` varchar(255) DEFAULT 'TESDA Regional Office',
  `fund_cluster` varchar(100) DEFAULT '101',
  `date_acquired` date DEFAULT NULL,
  `property_number` varchar(100) DEFAULT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `received_by_designation` varchar(255) DEFAULT NULL,
  `received_by_date` date DEFAULT NULL,
  `issued_by` varchar(255) DEFAULT NULL,
  `issued_by_designation` varchar(255) DEFAULT NULL,
  `issued_by_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`par_id`),
  UNIQUE KEY `par_no` (`par_no`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ppe_par_items`
--

DROP TABLE IF EXISTS `ppe_par_items`;
CREATE TABLE IF NOT EXISTS `ppe_par_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `par_id` int NOT NULL,
  `ppe_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `par_id` (`par_id`),
  KEY `ppe_id` (`ppe_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpcppe`
--

DROP TABLE IF EXISTS `rpcppe`;
CREATE TABLE IF NOT EXISTS `rpcppe` (
  `rpcppe_id` int NOT NULL AUTO_INCREMENT,
  `report_date` date NOT NULL,
  `fund_cluster` varchar(100) DEFAULT '101',
  `accountable_officer` varchar(255) DEFAULT NULL,
  `official_designation` varchar(255) DEFAULT NULL,
  `entity_name` varchar(255) DEFAULT 'TESDA Regional Office',
  `assumption_date` date DEFAULT NULL,
  `certified_by` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `verified_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`rpcppe_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rpcppe_items`
--

DROP TABLE IF EXISTS `rpcppe_items`;
CREATE TABLE IF NOT EXISTS `rpcppe_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rpcppe_id` int NOT NULL,
  `ppe_id` int NOT NULL,
  `on_hand_per_count` int DEFAULT '0',
  `shortage_overage_qty` int DEFAULT '0',
  `shortage_overage_value` decimal(10,2) DEFAULT '0.00',
  `remarks` text,
  PRIMARY KEY (`id`),
  KEY `rpcppe_id` (`rpcppe_id`),
  KEY `ppe_id` (`ppe_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_property_card_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_property_card_summary`;
CREATE TABLE IF NOT EXISTS `vw_property_card_summary` (
`current_balance` decimal(33,2)
,`description` text
,`entity_name` varchar(255)
,`fund_cluster` varchar(50)
,`last_transaction_date` date
,`ppe_type` varchar(255)
,`property_number` varchar(100)
,`total_amount` decimal(37,2)
,`total_issued` decimal(32,2)
,`total_received` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_property_card_summary`
--
DROP TABLE IF EXISTS `vw_property_card_summary`;

DROP VIEW IF EXISTS `vw_property_card_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_property_card_summary`  AS SELECT `pc`.`entity_name` AS `entity_name`, `pc`.`fund_cluster` AS `fund_cluster`, `pc`.`ppe_type` AS `ppe_type`, `pc`.`property_number` AS `property_number`, `pc`.`description` AS `description`, sum((case when (`pc`.`transaction_type` = 'receipt') then `pc`.`receipt_qty` else 0 end)) AS `total_received`, sum((case when (`pc`.`transaction_type` in ('issue','transfer','disposal')) then `pc`.`issue_qty` else 0 end)) AS `total_issued`, (sum((case when (`pc`.`transaction_type` = 'receipt') then `pc`.`receipt_qty` else 0 end)) - sum((case when (`pc`.`transaction_type` in ('issue','transfer','disposal')) then `pc`.`issue_qty` else 0 end))) AS `current_balance`, sum(`pc`.`amount`) AS `total_amount`, max(`pc`.`transaction_date`) AS `last_transaction_date` FROM `property_cards` AS `pc` GROUP BY `pc`.`entity_name`, `pc`.`fund_cluster`, `pc`.`ppe_type`, `pc`.`property_number`, `pc`.`description` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `iirusp_items`
--
ALTER TABLE `iirusp_items`
  ADD CONSTRAINT `iirusp_items_ibfk_1` FOREIGN KEY (`iirusp_id`) REFERENCES `iirusp` (`iirusp_id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_entries`
--
ALTER TABLE `inventory_entries`
  ADD CONSTRAINT `inventory_entries_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `item_history`
--
ALTER TABLE `item_history`
  ADD CONSTRAINT `fk_item_history_ris` FOREIGN KEY (`ris_id`) REFERENCES `ris` (`ris_id`),
  ADD CONSTRAINT `item_history_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `itr_items`
--
ALTER TABLE `itr_items`
  ADD CONSTRAINT `fk_itr_items_itr` FOREIGN KEY (`itr_id`) REFERENCES `itr` (`itr_id`) ON DELETE CASCADE;

--
-- Constraints for table `regspi_entries`
--
ALTER TABLE `regspi_entries`
  ADD CONSTRAINT `fk_regspi_entries_header` FOREIGN KEY (`regspi_id`) REFERENCES `regspi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rrsp_items`
--
ALTER TABLE `rrsp_items`
  ADD CONSTRAINT `rrsp_items_ibfk_1` FOREIGN KEY (`rrsp_id`) REFERENCES `rrsp` (`rrsp_id`) ON DELETE CASCADE;

--
-- Constraints for table `ppe_ptr_items`
--
ALTER TABLE `ppe_ptr_items`
  ADD CONSTRAINT `ppe_ptr_items_ibfk_1` FOREIGN KEY (`ptr_id`) REFERENCES `ppe_ptr` (`ptr_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ppe_ptr_items_ibfk_2` FOREIGN KEY (`ppe_id`) REFERENCES `ppe_property` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ppe_par_items`
--
ALTER TABLE `ppe_par_items`
  ADD CONSTRAINT `ppe_par_items_ibfk_1` FOREIGN KEY (`par_id`) REFERENCES `ppe_par` (`par_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ppe_par_items_ibfk_2` FOREIGN KEY (`ppe_id`) REFERENCES `ppe_property` (`id`) ON DELETE CASCADE;

COMMIT;

CREATE Table if NOT exists `officers` (
  officer_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  officer_name varchar(255) NOT NULL,
  officer_position varchar(255) NOT NULL
); 

DROP TABLE IF EXISTS `officers`;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
