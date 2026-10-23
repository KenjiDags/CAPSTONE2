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
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ics_history`
--

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
  KEY `idx_stock_number` (`stock_number`),
  CONSTRAINT `fk_ics_items_ics` FOREIGN KEY (`ics_id`) REFERENCES `ics` (`ics_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------


-- --------------------------------------------------------

--
-- Table structure for table `iirusp`
--

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
  PRIMARY KEY (`iirusp_id`),
  `approved_by_designation` VARCHAR(255),
  `requested_by_designation` VARCHAR(255)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `iirusp_history`
--

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
  KEY `iirusp_id` (`iirusp_id`),
  CONSTRAINT `iirusp_items_ibfk_1` FOREIGN KEY (`iirusp_id`) REFERENCES `iirusp` (`iirusp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------


--
-- Table structure for table `items`
--

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
  PRIMARY KEY (`item_id`),
  `estimated_life` VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=223 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `inventory_entries`
--

CREATE TABLE IF NOT EXISTS `inventory_entries` (
  `entry_id` int NOT NULL AUTO_INCREMENT,
  `item_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`entry_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `inventory_entries_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=292 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------


--
-- Table structure for table `item_history_archive`
--

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


-- --------------------------------------------------------

--
-- Table structure for table `itr`
--

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
  KEY `fk_itr_items_itr` (`itr_id`),
  CONSTRAINT `fk_itr_items_itr` FOREIGN KEY (`itr_id`) REFERENCES `itr` (`itr_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_cards`
--

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


-- --------------------------------------------------------

--
-- Table structure for table `regspi`
--

CREATE TABLE IF NOT EXISTS `regspi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_name` varchar(255) NOT NULL,
  `fund_cluster` varchar(100) DEFAULT NULL,
  `semi_expendable_property` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `regspi_entries`
--

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
  KEY `idx_property_no` (`property_no`),
  CONSTRAINT `fk_regspi_entries_header` FOREIGN KEY (`regspi_id`) REFERENCES `regspi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------

--
-- Table structure for table `ris`
--

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
  PRIMARY KEY (`ris_id`),
  requested_by_designation varchar(255),
  approved_by_designation varchar(255),
  issued_by_designation varchar(255),
  received_by_designation varchar(255),
  requested_by_date date,
  approved_by_date date,
  issued_by_date date,
  received_by_date date
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `item_history`
--

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
  KEY `fk_item_history_ris` (`ris_id`),
  CONSTRAINT `fk_item_history_ris` FOREIGN KEY (`ris_id`) REFERENCES `ris` (`ris_id`),
  CONSTRAINT `item_history_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=675 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `ris_items`
--

CREATE TABLE IF NOT EXISTS `ris_items` (
  `item_id` int NOT NULL,
  `ris_id` int DEFAULT NULL,
  `stock_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stock_available` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `issued_quantity` int DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `unit_cost_at_issue` decimal(10,2) DEFAULT '0.00',
  `days_to_consume` int unsigned DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Table structure for table `rpci`
--

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
  KEY `rrsp_id` (`rrsp_id`),
  CONSTRAINT `rrsp_items_ibfk_1` FOREIGN KEY (`rrsp_id`) REFERENCES `rrsp` (`rrsp_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rsmi`
--

CREATE TABLE IF NOT EXISTS `rsmi` (
  `rsmi_id` int NOT NULL,
  `date_generated` datetime NOT NULL,
  `total_issued` int NOT NULL,
  `month_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rsmi_reports`
--

CREATE TABLE IF NOT EXISTS rsmi_reports (
        rsmi_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        serial_no VARCHAR(40) NULL,
        report_date DATE NOT NULL,
        custodian_name VARCHAR(255) NOT NULL,
        accounting_staff VARCHAR(255) NOT NULL,
        total_issued DECIMAL(18,2) NOT NULL,
        total_amount DECIMAL(18,2) NOT NULL,
        item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY rsmi_serial (serial_no),
        KEY rsmi_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `rspi_items`
--

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


-- --------------------------------------------------------

--
-- Table structure for table `rspi_reports`
--

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


-- --------------------------------------------------------

--
-- Table structure for table `semi_expendable_history`
--

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
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_card`
--

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
-- User accounts and profile fields are maintained in SQL/create_admin.sql.
-- Import that script after this file when setting up a new database.
-- --------------------------------------------------------

--

-- --------------------------------------------------------

-- PPE inventory and report tables are included below.

--
-- Structure for view `vw_property_card_summary`
--

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_property_card_summary`  AS SELECT `pc`.`entity_name` AS `entity_name`, `pc`.`fund_cluster` AS `fund_cluster`, `pc`.`ppe_type` AS `ppe_type`, `pc`.`property_number` AS `property_number`, `pc`.`description` AS `description`, sum((case when (`pc`.`transaction_type` = 'receipt') then `pc`.`receipt_qty` else 0 end)) AS `total_received`, sum((case when (`pc`.`transaction_type` in ('issue','transfer','disposal')) then `pc`.`issue_qty` else 0 end)) AS `total_issued`, (sum((case when (`pc`.`transaction_type` = 'receipt') then `pc`.`receipt_qty` else 0 end)) - sum((case when (`pc`.`transaction_type` in ('issue','transfer','disposal')) then `pc`.`issue_qty` else 0 end))) AS `current_balance`, sum(`pc`.`amount`) AS `total_amount`, max(`pc`.`transaction_date`) AS `last_transaction_date` FROM `property_cards` AS `pc` GROUP BY `pc`.`entity_name`, `pc`.`fund_cluster`, `pc`.`ppe_type`, `pc`.`property_number`, `pc`.`description` ;

CREATE Table if NOT exists `officers` (
  officer_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  officer_name varchar(255) NOT NULL,
  officer_position varchar(255) NOT NULL
);


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- Saved RPCI documents preserve the report metadata and inventory snapshot.
CREATE TABLE IF NOT EXISTS `rpci_reports` (
  `rpci_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `report_date` DATE NOT NULL,
  `entity_name` VARCHAR(255) NOT NULL,
  `accountable_officer` VARCHAR(255) NOT NULL,
  `item_count` INT UNSIGNED NOT NULL,
  `snapshot` LONGTEXT NOT NULL,
  `created_by` INT NOT NULL,
  `save_token` CHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `rpci_save_token` (`save_token`),
  KEY `rpci_date` (`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Saved REGSPI documents preserve report details and item snapshots.
CREATE TABLE IF NOT EXISTS regspi_reports (
        regspi_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_date DATE NOT NULL,
        entity_name VARCHAR(255) NOT NULL,
        fund_cluster VARCHAR(255) NOT NULL,
        category VARCHAR(255) NOT NULL,
        item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL,
        created_by INT NOT NULL,
        save_token CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY regspi_save_token (save_token),
        KEY regspi_report_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Saved RPCSP documents preserve report details and item snapshots.
CREATE TABLE IF NOT EXISTS rpcsp_reports (
        rpcsp_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        report_date DATE NOT NULL, entity_name VARCHAR(255) NOT NULL,
        accountable_officer VARCHAR(255) NOT NULL, item_count INT UNSIGNED NOT NULL,
        snapshot LONGTEXT NOT NULL, created_by INT NOT NULL, save_token CHAR(64) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY rpcsp_save_token (save_token), KEY rpcsp_report_date (report_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Property, Plant and Equipment schema.
/* Table for Property, Plant and Equipment (PPE) Inventory */
CREATE TABLE IF NOT EXISTS ppe_property (
    id INT PRIMARY KEY AUTO_INCREMENT,

    item_name TEXT NOT NULL,
    item_description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    officer_incharge VARCHAR(255) NOT NULL,
    PPE_no VARCHAR(50) NOT NULL UNIQUE,
    property_no VARCHAR(100) NULL,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'unit',

    custodian VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,

    date_acquired DATE NULL,

    `condition` ENUM('Good', 'Fair', 'Poor', 'Unserviceable') DEFAULT 'Good',
    status ENUM('Active', 'Transferred', 'Returned', 'For Repair', 'Unserviceable', 'Disposed') DEFAULT 'Active',

    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Table for Property Custodian Slip (PCS) */
CREATE TABLE IF NOT EXISTS ppe_pc (
    id INT PRIMARY KEY AUTO_INCREMENT,

    date_created DATE NOT NULL,
    ppe_property_no VARCHAR(50) NOT NULL UNIQUE,
    property_no VARCHAR(100) NULL,
    item_name TEXT NOT NULL,
    item_description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    quantity INT DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'unit',
    custodian VARCHAR(255) NOT NULL,
    officer VARCHAR(255) NOT NULL,
    entity_name VARCHAR(255) NOT NULL,
    fund_cluster VARCHAR(10) DEFAULT '101',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Table for Property Transfer Receipt (PTR) */
CREATE TABLE IF NOT EXISTS ppe_ptr (
    ptr_id INT AUTO_INCREMENT PRIMARY KEY,
    ptr_no VARCHAR(100) NOT NULL,
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    fund_cluster VARCHAR(100) DEFAULT '101',
    from_officer VARCHAR(255),
    to_officer VARCHAR(255),
    transfer_date DATE,
    transfer_type VARCHAR(100),
    reason TEXT,
    approved_by VARCHAR(255),
    approved_by_designation VARCHAR(255),
    approved_by_date DATE,
    released_by VARCHAR(255),
    released_by_designation VARCHAR(255),
    released_by_date DATE,
    received_by VARCHAR(255),
    received_by_designation VARCHAR(255),
    received_by_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ppe_ptr_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ptr_id INT NOT NULL,
    ppe_id INT NOT NULL,
    FOREIGN KEY (ptr_id) REFERENCES ppe_ptr(ptr_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


/* Table for Property Acknowledgment Receipt (PAR) */
CREATE TABLE IF NOT EXISTS ppe_par (
    par_id INT AUTO_INCREMENT PRIMARY KEY,
    par_no VARCHAR(100) NOT NULL UNIQUE,
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    fund_cluster VARCHAR(100) DEFAULT '101',
    date_acquired DATE,
    property_number VARCHAR(100),
    received_by VARCHAR(255),
    received_by_designation VARCHAR(255),
    received_by_date DATE,
    issued_by VARCHAR(255),
    issued_by_designation VARCHAR(255),
    issued_by_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Table for PAR items (junction table for linking PAR to multiple properties) */
CREATE TABLE IF NOT EXISTS ppe_par_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    par_id INT NOT NULL,
    ppe_id INT NOT NULL,
    FOREIGN KEY (par_id) REFERENCES ppe_par(par_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Table for Report on Physical Count of Property, Plant and Equipment (RPCPPE) */
CREATE TABLE IF NOT EXISTS rpcppe (
    rpcppe_id INT AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    fund_cluster VARCHAR(100) DEFAULT '101',
    accountable_officer VARCHAR(255),
    official_designation VARCHAR(255),
    entity_name VARCHAR(255) DEFAULT 'TESDA Regional Office',
    assumption_date DATE,
    certified_by VARCHAR(255),
    approved_by VARCHAR(255),
    verified_by VARCHAR(255),
    snapshot LONGTEXT NULL,
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Table for RPCPPE items (physical count data for each PPE item) */
CREATE TABLE IF NOT EXISTS rpcppe_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rpcppe_id INT NOT NULL,
    ppe_id INT NOT NULL,
    on_hand_per_count INT DEFAULT 0,
    shortage_overage_qty INT DEFAULT 0,
    shortage_overage_value DECIMAL(10,2) DEFAULT 0.00,
    remarks TEXT,
    FOREIGN KEY (rpcppe_id) REFERENCES rpcppe(rpcppe_id) ON DELETE CASCADE,
    FOREIGN KEY (ppe_id) REFERENCES ppe_property(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Table for PPE history log (to track changes in PPE items) */
CREATE TABLE IF NOT EXISTS item_history_ppe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    PPE_no VARCHAR(50) NULL,
    property_no VARCHAR(100) NULL,
    PAR_number VARCHAR(100) NULL,
    refference_no VARCHAR(100) NULL,
    item_name VARCHAR(255) NULL,
    description TEXT NULL,
    unit VARCHAR(50) NULL,
    unit_cost DECIMAL(15,2) NULL,
    quantity_on_hand INT NULL,
    quantity_change INT NULL,
    receipt_qty INT NULL,
    issue_qty INT NULL,
    balance_qty INT NULL,
    unserviceable_qty INT NULL,
    officer_incharge VARCHAR(255) NULL,
    change_direction VARCHAR(20) NULL,
    change_type VARCHAR(50) NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_property_no (PPE_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ppe_iirup (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_reported DATE NOT NULL,
    PPE_no VARCHAR(50) NULL,
    particulars VARCHAR(255) NULL,
    quantity INT NULL,
    depreciation DECIMAL(15,2) NULL,
    impairment_loss DECIMAL(15,2) NULL,
    carrying_amount DECIMAL(15,2) NULL,
    remarks TEXT NULL,
    sale INT NULL,
    transfer INT NULL,
    destruction INT NULL,
    other INT NULL,
    total INT NULL,
    appraised_value DECIMAL(15,2) NULL,
    or_no VARCHAR(100) NULL,
    amount DECIMAL(15,2) NULL,
    requested_by_name VARCHAR(255) NULL,
    requested_by_designation VARCHAR(255) NULL,
    requested_by_station VARCHAR(255) NULL,
    approved_by_name VARCHAR(255) NULL,
    approved_by_designation VARCHAR(255) NULL,
    inspection_officer_name VARCHAR(255) NULL,
    inspection_officer_designation VARCHAR(255) NULL,
    witness_name VARCHAR(255) NULL,
    witness_designation VARCHAR(255) NULL,
    entity_name VARCHAR(255) NULL,
    total_cost DECIMAL(15,2) NULL,
    accountable_officer_name VARCHAR(255) NULL,
    accountable_officer_designation VARCHAR(255) NULL,
    accountable_officer_station VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ppe_iirup_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ppe_iirup_id INT NOT NULL,
    date_acquired DATE NULL,
    quantity INT NULL,
    PPE_no VARCHAR(50) NULL,
    depreciation DECIMAL(15,2) NULL,
    impairment_loss DECIMAL(15,2) NULL,
    carrying_amount DECIMAL(15,2) NULL,
    remarks TEXT NULL,
    sale INT NULL,
    transfer INT NULL,
    destruction INT NULL,
    other INT NULL,
    total INT NULL,
    appraised_value DECIMAL(15,2) NULL,
    or_no VARCHAR(100) NULL,
    amount DECIMAL(15,2) NULL,
    particulars VARCHAR(255) NULL,
    sale_amount DECIMAL(15,2) NULL,
    other_specification VARCHAR(255) NULL,
    FOREIGN KEY (ppe_iirup_id) REFERENCES ppe_iirup(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

-- Existing installations: preserve the days estimate saved with each RIS item.
ALTER TABLE ris_items ADD COLUMN IF NOT EXISTS days_to_consume INT UNSIGNED NULL;
