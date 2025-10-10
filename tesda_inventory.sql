-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
<<<<<<< HEAD
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2025 at 06:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30
=======
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 08, 2025 at 08:35 AM
-- Server version: 9.1.0
-- PHP Version: 8.3.14
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

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

-- --------------------------------------------------------

--
<<<<<<< HEAD
-- Table structure for table `inventory_entries`
--

CREATE TABLE `inventory_entries` (
  `entry_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
=======
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ics_id`),
  UNIQUE KEY `unique_ics_no` (`ics_no`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

--
-- Dumping data for table `inventory_entries`
--

INSERT INTO `inventory_entries` (`entry_id`, `item_id`, `quantity`, `unit_cost`, `created_at`, `is_active`) VALUES
<<<<<<< HEAD
(285, 210, 1, 30.00, '2025-08-12 15:31:34', 1),
(286, 210, -5, 0.00, '2025-08-12 15:32:01', 1),
(287, 210, 5, 21.00, '2025-08-12 15:32:49', 1);
=======
(291, 4, -1, 0.00, '2025-10-06 02:36:13', 1);
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

<<<<<<< HEAD
CREATE TABLE `items` (
  `item_id` int(11) NOT NULL,
  `stock_number` varchar(50) NOT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) NOT NULL,
  `reorder_point` int(11) DEFAULT NULL,
  `parent_item_id` int(11) DEFAULT NULL,
  `quantity_on_hand` int(11) DEFAULT 0,
  `unit_cost` decimal(10,4) DEFAULT NULL,
  `initial_quantity` int(11) DEFAULT 0,
  `average_unit_cost` decimal(10,4) DEFAULT NULL,
  `calculated_unit_cost` decimal(10,4) DEFAULT NULL,
  `calculated_quantity` int(11) DEFAULT NULL,
  `iar` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
=======
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
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `stock_number`, `item_name`, `description`, `unit`, `reorder_point`, `parent_item_id`, `quantity_on_hand`, `unit_cost`, `initial_quantity`, `average_unit_cost`, `calculated_unit_cost`, `calculated_quantity`, `iar`) VALUES
<<<<<<< HEAD
(1, 'A.01.a', 'ARCHFILE FOLDER', 'Tagila Lock', 'pc', 11, NULL, 11, 11.0000, 11, NULL, NULL, NULL, NULL),
(2, 'A.02.a', 'AIR FRESHINER REFILL', 'Automatic Spray Refill(glade)', 'can', 0, NULL, 0, 10.0000, 0, NULL, NULL, NULL, NULL),
(3, 'A.03.a', 'ALCOHOL', '70% ethy/isopropyl, with moisturizer, gallon', 'gallon', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(4, 'A.03.b', 'ALCOHOL', '70% ethyl/isopropyl, 500ml', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(5, 'B.01.a', 'BATTERY', 'dry cell, AA, 4pcs/pack, 1.5V, heavy duty', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(6, 'B.01.b', 'BATTERY', 'dry cell, AAA, 4pcs/pack, 1.5V, heavy duty', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(7, 'B.01.c', 'BATTERY', 'dry cell, 9V1', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(8, 'B.01.d', 'BATTERY', 'Li-on for thermo scanner', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(9, 'B.02.a', 'BLEACH', 'Zonrox', 'gallon', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(10, 'C.01.a', 'CALCULATOR', '', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(11, 'C.02.a', 'CERTIFICATE HOLDER', 'A4', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(12, 'C.03.a', 'CLIP', 'backfold, large, 41mm, 12pcs/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(13, 'C.03.b', 'CLIP', 'backfold, medium, 25mm, 12pcs/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(14, 'C.03.c', 'CLIP', 'backfold, small, 19mm, 12pcs/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(15, 'C.03.d', 'CLIP', 'backfold, extra small, 15mm, 12pcs/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(16, 'C.04.a', 'CORRECTION TAPE', 'film based', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(17, 'C.05.a', 'CUTTER PAPER', 'blade/knife', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(18, 'C.06.a', 'CLING WRAP', '12inches x 300meters', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(19, 'D.01.a', 'DISHWASHING LIQUID', '500ml', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(20, 'D.02.a', 'DISINFECTANT SPRAY', 'aerosol type', 'can', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(21, 'D.03.a', 'DRAWER LOCK', 'set with key', 'set', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(22, 'E.01.a', 'ENVELOPE EXPANDABLE', 'brown, long', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(23, 'F.01.a', 'FASTENER', 'plastic', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(24, 'F.02.a', 'FOLDER', 'Tag Board, White, 100pcs/pack, Long', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(25, 'F.02.b', 'FOLDER EXPANDING', 'Long, pressboard 100pcs/pack, white & blue', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(26, 'F.03.a', 'FABRIC CONDITIONER', 'Softener', 'gallon', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(27, 'G.01.a', 'GLUE STICK', 'all purpose, 22 grams,', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(28, 'G.02.a', 'GLASS CLEANER', 'with Spray cap 500ml', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(29, 'H.01.a', 'HANDSOAP', 'Liquid, 500ml', 'btl', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(30, 'I.01.a', 'INDEX TAB', '', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(31, 'I.02.a', 'INK', 'Canon, GI 790, Magenta', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(32, 'I.02.b', 'INK', 'Canon, GI 790, Yellow', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(33, 'I.02.c', 'INK', 'Canon, GI 790, Black', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(34, 'I.02.d', 'INK', 'Canon, GI 790, Cyan', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(35, 'I.03.a', 'INK HP', '682, black', 'cart', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(36, 'I.03.b', 'INK HP', '682, colored', 'cart', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(37, 'I.04.a', 'INK', 'Canon, 810 Black', 'cart', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(38, 'I.04.b', 'INK', 'Canon, 811 Colored', 'cart', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(39, 'I.05.a', 'INK', 'Epson 003, Black', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(40, 'I.05.b', 'INK', 'Epson 003, Cyan', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(41, 'I.05.c', 'INK', 'Epson 003, Magenta', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(42, 'I.05.d', 'INK', 'Epson 003, Yellow', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(43, 'I.06.a', 'INSECTICIDE', 'Aerosol type, waterbased, 600ml/can', 'can', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(44, 'K.01.a', 'KITCHEN TOWEL', 'Paper Towel, roll, 2ply', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(45, 'L.01.a', 'LED BULB', '', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(46, 'N.01.a', 'NOTARIAL SEAL', '', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(47, 'N.02.a', 'NOTE PAD', 'stick on, 2\"x3\"', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(48, 'N.02.b', 'NOTE PAD', 'stick on, 3\"x3\"', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(49, 'N.02.c', 'NOTE PAD', 'stick on, 4\"x3\"', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(50, 'N.02.d', 'NOTE PAD', 'stick on, d3-4 (4\'s -1\"x3\")', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(51, 'P.01.a', 'PAPER', 'Board, A4, white, 180gsm, 100sheets/pack', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(52, 'P.01.b', 'PAPER', 'Board, A4, white, 200gsm, 100sheets/pack', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(53, 'P.01.c', 'PAPER', 'Board, Morocco, A4, 200gsm, 100sheets/pack', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(54, 'P.02.a', 'PAPER CLIP', '50mm, jumbo, vinyl coated', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(55, 'P.02.b', 'PAPER CLIP', '33mm, vinyl coated', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(56, 'P.03.a', 'PAPER', 'Multicopy, PPC, s20, 8.5\" x 13\"', 'ream', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(57, 'P.03.b', 'PAPER', 'Multicopy, PPC, s20, 8.5\" x 14\"', 'ream', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(58, 'P.03.c', 'PAPER', 'Multicopy, PPC, s20, A4', 'ream', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(59, 'P.03.d', 'PAPER', 'Multicopy, PPC, s20, Short', 'ream', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(60, 'P.04.a', 'PEN SIGN', 'gel or liquid ink, retractable, 0.7mm Black/ Blue, 12pcs/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(61, 'p.04.b', 'PEN SIGN', 'Hi-tecpoint V10Grip, 1.0, 12pcs/box, Black/Blue', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(62, 'P.04.c', 'PEN', 'ballpoint, retractable, 0.7mm, Black/Blue', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(63, 'P.04.d', 'PEN', 'Fine, Retractable, 0.5mm', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(64, 'P.05.a', 'POST IT- Sticky Note', '\"Sign Here\", \"Please Sign\",', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(65, 'P.06.a', 'PUSH PINS', '100pcs/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(66, 'R.01.a', 'RECORD BOOK', 'Logbook, 300 pages', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(67, 'R.02.a', 'RULER', 'Steel, 12 inches', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(68, 'R.03.a', 'RAGS', '', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(69, 'S.01.a', 'STAPLER', '', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(70, 'S.01.b', 'STAPLE WIRE', 'Standard, 5000 staples/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(71, 'S.01.c', 'STAPLE WIRE', 'Bostitch, 5000 staples/box', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(72, 'S.01.d', 'STAPLER REMOVER', '', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(73, 'S.02.a', 'SCOURING PAD', 'Dishwashing sponge', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(74, 'T.01.a', 'TAPE', 'clear, 1inch', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(75, 'T.01.b', 'TAPE', 'Cloth, Duct tape', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(76, 'T.01.c', 'TAPE', 'double sided, 1inch', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(77, 'T.01.d', 'TAPE', 'Packing, 2\"', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(78, 'T.01.e', 'TAPE', 'transparent, 2\"', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(79, 'T.01.f', 'TAPE', 'transparent, 3\"', 'roll', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(80, 'T.02.a', 'TAPE', 'refill for Epson LW-K400 printer/label 12mm', 'pcs', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(81, 'T.03.a', 'TAPE DISPENSER', '', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(82, 'T.04.a', 'TOILET BOWL BRUSH', 'round headed brush', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(83, 'T.04.b', 'TOILET BOWL CLEANER', 'Liquid, 900ml', 'bottle', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(84, 'T.05.a', 'TISSUE BATHROOM', 'Green Tea, 180g, 10pcs/pack', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(85, 'T.05.b', 'TISSUE FACIAL', 'Econo Box, 2ply, 200-250pulls', 'box', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(86, 'T.05.c', 'TOILET TISSUE PAPER', '2ply, 12\'s per pack, 1000 sheets per roll', 'pack', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(87, 'U.01.a', 'USB', 'Flash Drive, 64GB', 'pc', NULL, NULL, 0, 0.0000, 0, 0.0000, NULL, NULL, NULL),
(210, '123', '123', '123', '123', 1, NULL, 5, 21.0000, 4, 23.2500, 23.2500, 5, '1232'),
(211, 'qweqw', 'qwewqe', 'qweqwe', 'qweqwe', 1, NULL, 10, 10.0000, 10, NULL, NULL, NULL, 'qwe');
=======
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
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

-- --------------------------------------------------------

--
-- Table structure for table `item_history`
--

<<<<<<< HEAD
CREATE TABLE `item_history` (
  `history_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `stock_number` varchar(255) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `reorder_point` int(11) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `quantity_on_hand` int(11) DEFAULT NULL,
  `quantity_change` int(11) DEFAULT NULL,
  `change_direction` varchar(20) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `change_type` varchar(50) DEFAULT 'update',
  `ris_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
=======
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
) ENGINE=InnoDB AUTO_INCREMENT=673 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

--
-- Dumping data for table `item_history`
--

INSERT INTO `item_history` (`history_id`, `item_id`, `stock_number`, `item_name`, `description`, `unit`, `reorder_point`, `unit_cost`, `quantity_on_hand`, `quantity_change`, `change_direction`, `changed_at`, `change_type`, `ris_id`) VALUES
<<<<<<< HEAD
=======
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
(668, 221, 'A.01.a', 'ARCHFILE   ', 'Tagila Lock size 3\"x9\"x15 blue and black', 'pc', 0, 129.00, 23, 23, 'increase', '2025-09-04 16:14:34', 'add', NULL),
(669, 45, 'L.01.a', 'LED BULB', 'LED 22-30 Watts', 'pc', 0, 0.00, 30, 30, 'increase', '2025-09-07 10:16:48', 'update', NULL),
(670, 4, 'A.03.b', 'ALCOHOL', '70% ethyl/isopropyl, 500ml', 'bottle', 0, 0.00, 10, 10, 'increase', '2025-09-07 10:18:41', 'update', NULL),
(671, 4, 'A.03.b', 'ALCOHOL', '70% ethyl/isopropyl, 500ml', 'bottle', 0, 0.00, 9, -1, 'decrease', '2025-10-06 10:36:13', 'issued', 31);

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
) ENGINE=InnoDB AUTO_INCREMENT=665 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `item_history_archive`
--

INSERT INTO `item_history_archive` (`history_id`, `item_id`, `stock_number`, `item_name`, `description`, `unit`, `reorder_point`, `unit_cost`, `quantity_on_hand`, `quantity_change`, `change_direction`, `changed_at`, `change_type`, `reference_id`) VALUES
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6
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
<<<<<<< HEAD
(577, 210, '123', '123', '123', '123', 1, 21.00, 4, 4, 'increase', '2025-08-12 23:31:11', 'add', NULL),
(578, 210, '123', '123', '123', '123', 1, 25.50, 5, 1, 'increase', '2025-08-12 23:31:34', 'entry', NULL),
(579, 210, '123', '123', '123', '123', 1, 25.50, 0, -5, 'decrease', '2025-08-12 23:32:01', 'issued', 29),
(580, 210, '123', '123', '123', '123', 1, 23.25, 5, 5, 'increase', '2025-08-12 23:32:49', 'entry', NULL),
(581, 211, 'qwe', 'qwe', 'qwe', 'qwe', 1, 12.00, 12, 12, 'increase', '2025-08-12 23:33:33', 'add', NULL),
(582, 211, 'qwe', 'qwe', 'qwe', 'qwe', 1, 12.50, 17, 5, 'increase', '2025-08-12 23:34:02', 'entry', NULL),
(583, 211, 'qwe', 'qwe', 'qwe', 'qwe', 1, 12.50, 17, 0, 'no_change', '2025-08-12 23:34:15', 'cleared', NULL),
(584, 211, 'qwe', 'qwe', 'qwe', 'qwe', 1, 10.00, 10, 10, 'increase', '2025-08-12 23:34:29', 'update', NULL),
(585, 211, 'qwe', 'qwe', 'qwe', 'qwe', 1, 16.00, 12, 2, 'increase', '2025-08-12 23:34:44', 'entry', NULL),
(587, 211, 'qweqw', 'qwewqe', 'qweqwe', 'qweqwe', 1, 10.00, 10, 10, 'increase', '2025-08-12 23:37:04', 'update', NULL),
(588, 210, '123', '123', '123', '123', 1, 23.25, 5, 0, 'no_change', '2025-08-13 00:01:35', 'update', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `item_history_archive`
--

CREATE TABLE `item_history_archive` (
  `history_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `stock_number` varchar(255) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `reorder_point` int(11) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `quantity_on_hand` int(11) DEFAULT NULL,
  `quantity_change` int(11) DEFAULT NULL,
  `change_direction` varchar(20) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `change_type` varchar(50) DEFAULT 'update',
  `reference_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
=======
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
(664, 2, 'A.02.a', 'Disinfectant', 'Air Freshener', 'can', 0, 162.17, 7, 7, 'increase', '2025-09-04 15:47:40', 'update', NULL);
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

-- --------------------------------------------------------

--
-- Table structure for table `property_cards`
--

<<<<<<< HEAD
CREATE TABLE `property_cards` (
  `pc_id` int(11) NOT NULL,
  `entity_name` varchar(255) NOT NULL,
  `fund_cluster` varchar(50) NOT NULL,
  `ppe_type` varchar(255) NOT NULL COMMENT 'Property, Plant and Equipment type',
  `description` text NOT NULL COMMENT 'Description of the PPE (brand, size, color, serial no., etc.)',
  `property_number` varchar(100) NOT NULL COMMENT 'Number assigned by Supply/Property Division',
  `transaction_date` date NOT NULL COMMENT 'Date of acquisition/issue/transfer/disposal',
  `reference_par_no` varchar(100) DEFAULT NULL COMMENT 'Reference document or PAR number',
  `receipt_qty` decimal(10,2) DEFAULT 0.00 COMMENT 'Quantity received',
  `issue_qty` decimal(10,2) DEFAULT 0.00 COMMENT 'Quantity issued/transferred/disposed',
  `office_officer` varchar(255) DEFAULT NULL COMMENT 'Receiving office/officer name',
  `amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount of PPE',
  `remarks` text DEFAULT NULL COMMENT 'Important information or comments',
  `transaction_type` enum('receipt','issue','transfer','disposal') NOT NULL DEFAULT 'receipt',
  `created_by` int(11) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
=======
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
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

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
-- Table structure for table `ris`
--

<<<<<<< HEAD
CREATE TABLE `ris` (
  `ris_id` int(11) NOT NULL,
  `entity_name` varchar(255) DEFAULT NULL,
  `fund_cluster` varchar(100) DEFAULT NULL,
  `division` varchar(100) DEFAULT NULL,
  `office` varchar(100) DEFAULT NULL,
  `responsibility_center_code` varchar(100) DEFAULT NULL,
  `ris_no` varchar(100) DEFAULT NULL,
  `date_requested` date DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `requested_by` varchar(255) DEFAULT NULL,
  `approved_by` varchar(255) DEFAULT NULL,
  `issued_by` varchar(255) DEFAULT NULL,
  `received_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
=======
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
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

--
-- Dumping data for table `ris`
--

INSERT INTO `ris` (`ris_id`, `entity_name`, `fund_cluster`, `division`, `office`, `responsibility_center_code`, `ris_no`, `date_requested`, `purpose`, `requested_by`, `approved_by`, `issued_by`, `received_by`, `created_at`) VALUES
<<<<<<< HEAD
(29, '123', '123', 'FASD', 'TESDA CAR', '123', '2025/08/0001', '2025-08-12', '123', '123', '123', '123', '123', '2025-08-12 15:32:01');
=======
(31, 'a', 'f', 'FASD', 'TESDA CAR', 'f', '2025/10/0001', '2025-10-06', 'd', 'a', 'a', 'a', 'a', '2025-10-06 02:36:13');
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

-- --------------------------------------------------------

--
-- Table structure for table `ris_items`
--

<<<<<<< HEAD
CREATE TABLE `ris_items` (
  `item_id` int(11) NOT NULL,
  `ris_id` int(11) DEFAULT NULL,
  `stock_number` varchar(100) DEFAULT NULL,
  `stock_available` varchar(10) DEFAULT NULL,
  `issued_quantity` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `unit_cost_at_issue` decimal(10,2) DEFAULT 0.00
=======
DROP TABLE IF EXISTS `ris_items`;
CREATE TABLE IF NOT EXISTS `ris_items` (
  `item_id` int NOT NULL,
  `ris_id` int DEFAULT NULL,
  `stock_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stock_available` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_quantity` int DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `unit_cost_at_issue` decimal(10,2) DEFAULT '0.00'
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ris_items`
--

INSERT INTO `ris_items` (`item_id`, `ris_id`, `stock_number`, `stock_available`, `issued_quantity`, `remarks`, `unit_cost_at_issue`) VALUES
<<<<<<< HEAD
(0, 29, '123', 'Yes', 5, '213', 25.50);
=======
(0, 31, 'A.03.b', 'Yes', 1, '', 0.00);
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

-- --------------------------------------------------------

--
-- Table structure for table `rpci`
--

<<<<<<< HEAD
CREATE TABLE `rpci` (
  `rpci_id` int(11) NOT NULL,
  `inventory_type` varchar(100) DEFAULT NULL,
  `as_of_date` date DEFAULT NULL,
  `fund_cluster` varchar(50) DEFAULT NULL,
  `article` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `stock_number` varchar(50) DEFAULT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `unit_value` decimal(10,2) DEFAULT NULL,
  `balance_per_card` int(11) DEFAULT NULL,
  `on_hand_per_count` int(11) DEFAULT NULL,
  `shortage` int(11) DEFAULT 0,
  `overage` int(11) DEFAULT 0,
  `remarks` varchar(255) DEFAULT NULL
=======
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
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rsmi`
--

<<<<<<< HEAD
CREATE TABLE `rsmi` (
  `rsmi_id` int(11) NOT NULL,
  `date_generated` datetime NOT NULL,
  `total_issued` int(11) NOT NULL,
  `month_year` varchar(20) NOT NULL
=======
DROP TABLE IF EXISTS `rsmi`;
CREATE TABLE IF NOT EXISTS `rsmi` (
  `rsmi_id` int NOT NULL,
  `date_generated` datetime NOT NULL,
  `total_issued` int NOT NULL,
  `month_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
<<<<<<< HEAD
-- Table structure for table `stock_card`
--

CREATE TABLE `stock_card` (
  `stock_card_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `transaction_date` date DEFAULT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `receipt_qty` int(11) DEFAULT 0,
  `issue_qty` int(11) DEFAULT 0,
  `balance_qty` int(11) DEFAULT NULL,
  `issued_to_office` varchar(100) DEFAULT NULL
=======
-- Table structure for table `semi_expendable_property`
--

DROP TABLE IF EXISTS `semi_expendable_property`;
CREATE TABLE IF NOT EXISTS `semi_expendable_property` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `ics_rrsp_no` varchar(50) NOT NULL,
  `semi_expendable_property_no` varchar(50) NOT NULL,
  `item_description` text NOT NULL,
  `estimated_useful_life` int NOT NULL,
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
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `semi_expendable_property`
--

INSERT INTO `semi_expendable_property` (`id`, `date`, `ics_rrsp_no`, `semi_expendable_property_no`, `item_description`, `estimated_useful_life`, `quantity_issued`, `office_officer_issued`, `quantity_returned`, `office_officer_returned`, `quantity_reissued`, `office_officer_reissued`, `quantity_disposed`, `quantity_balance`, `amount_total`, `category`, `fund_cluster`, `remarks`, `created_at`) VALUES
(1, '2025-10-08', '22-01', 'HV-200-101-10', 'computer', 5, 1, '', 0, '0', 0, '', 0, 1, 12000.00, 'Other PPE', '101', '', '2025-10-08 05:59:47'),
(2, '2025-10-08', '22-04', 'HV-200-101-11', 'computer', 5, 1, '', 0, '0', 0, '', 0, 1, 12000.00, 'Other PPE', '101', '', '2025-10-08 06:00:59'),
(3, '2025-10-08', '22-04', 'HV-200-101-12', 'computer', 5, 1, '', 0, '0', 0, '', 0, 1, 4000.00, 'Office Equipment', '101', '', '2025-10-08 06:12:56'),
(4, '2025-10-08', '22-04', 'HV-200-101-13', 'pc', 5, 1, '', 0, '0', 0, '', 0, 1, 12000.00, 'ICT Equipment', '101', '', '2025-10-08 06:39:36');

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
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
<<<<<<< HEAD
-- Stand-in structure for view `vw_property_card_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_property_card_summary` (
=======
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(250) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`) VALUES
(1, 'asd', '$2y$10$PApfH/YVd9WxMAO/BYs/Zu8rsDSEVzpl.Q8zGveIgeUfYZwQKHBNW');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_property_card_summary`
-- (See below for the actual view)
--
DROP VIEW IF EXISTS `vw_property_card_summary`;
CREATE TABLE IF NOT EXISTS `vw_property_card_summary` (
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6
`entity_name` varchar(255)
,`fund_cluster` varchar(50)
,`ppe_type` varchar(255)
,`property_number` varchar(100)
,`description` text
,`total_received` decimal(32,2)
,`total_issued` decimal(32,2)
,`current_balance` decimal(33,2)
,`total_amount` decimal(37,2)
,`last_transaction_date` date
);

-- --------------------------------------------------------

--
-- Structure for view `vw_property_card_summary`
--
DROP TABLE IF EXISTS `vw_property_card_summary`;

<<<<<<< HEAD
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_property_card_summary`  AS SELECT `pc`.`entity_name` AS `entity_name`, `pc`.`fund_cluster` AS `fund_cluster`, `pc`.`ppe_type` AS `ppe_type`, `pc`.`property_number` AS `property_number`, `pc`.`description` AS `description`, sum(case when `pc`.`transaction_type` = 'receipt' then `pc`.`receipt_qty` else 0 end) AS `total_received`, sum(case when `pc`.`transaction_type` in ('issue','transfer','disposal') then `pc`.`issue_qty` else 0 end) AS `total_issued`, sum(case when `pc`.`transaction_type` = 'receipt' then `pc`.`receipt_qty` else 0 end) - sum(case when `pc`.`transaction_type` in ('issue','transfer','disposal') then `pc`.`issue_qty` else 0 end) AS `current_balance`, sum(`pc`.`amount`) AS `total_amount`, max(`pc`.`transaction_date`) AS `last_transaction_date` FROM `property_cards` AS `pc` GROUP BY `pc`.`entity_name`, `pc`.`fund_cluster`, `pc`.`ppe_type`, `pc`.`property_number`, `pc`.`description` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory_entries`
--
ALTER TABLE `inventory_entries`
  ADD PRIMARY KEY (`entry_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `item_history`
--
ALTER TABLE `item_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `fk_item_history_ris` (`ris_id`);

--
-- Indexes for table `item_history_archive`
--
ALTER TABLE `item_history_archive`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `property_cards`
--
ALTER TABLE `property_cards`
  ADD PRIMARY KEY (`pc_id`),
  ADD KEY `idx_entity_fund` (`entity_name`,`fund_cluster`),
  ADD KEY `idx_property_number` (`property_number`),
  ADD KEY `idx_ppe_type` (`ppe_type`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_pc_compound` (`entity_name`,`fund_cluster`,`ppe_type`,`transaction_date`);

--
-- Indexes for table `ris`
--
ALTER TABLE `ris`
  ADD PRIMARY KEY (`ris_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory_entries`
--
ALTER TABLE `inventory_entries`
  MODIFY `entry_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=291;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `item_history`
--
ALTER TABLE `item_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=589;

--
-- AUTO_INCREMENT for table `item_history_archive`
--
ALTER TABLE `item_history_archive`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_cards`
--
ALTER TABLE `property_cards`
  MODIFY `pc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `ris`
--
ALTER TABLE `ris`
  MODIFY `ris_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;
=======
DROP VIEW IF EXISTS `vw_property_card_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_property_card_summary`  AS SELECT `pc`.`entity_name` AS `entity_name`, `pc`.`fund_cluster` AS `fund_cluster`, `pc`.`ppe_type` AS `ppe_type`, `pc`.`property_number` AS `property_number`, `pc`.`description` AS `description`, sum((case when (`pc`.`transaction_type` = 'receipt') then `pc`.`receipt_qty` else 0 end)) AS `total_received`, sum((case when (`pc`.`transaction_type` in ('issue','transfer','disposal')) then `pc`.`issue_qty` else 0 end)) AS `total_issued`, (sum((case when (`pc`.`transaction_type` = 'receipt') then `pc`.`receipt_qty` else 0 end)) - sum((case when (`pc`.`transaction_type` in ('issue','transfer','disposal')) then `pc`.`issue_qty` else 0 end))) AS `current_balance`, sum(`pc`.`amount`) AS `total_amount`, max(`pc`.`transaction_date`) AS `last_transaction_date` FROM `property_cards` AS `pc` GROUP BY `pc`.`entity_name`, `pc`.`fund_cluster`, `pc`.`ppe_type`, `pc`.`property_number`, `pc`.`description` ;
>>>>>>> 2fcf138baf998ee12e1b2085adec886dacd3abb6

--
-- Constraints for dumped tables
--

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
