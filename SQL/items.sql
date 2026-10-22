-- Seed data moved from tesda_inventory.sql.
-- Import SQL/tesda_inventory.sql before this file. Import once into a new database.
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;
SET time_zone = '+00:00';
USE tesda_inventory;

START TRANSACTION;

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

INSERT INTO `inventory_entries` (`entry_id`, `item_id`, `quantity`, `unit_cost`, `created_at`, `is_active`) VALUES
(291, 4, -1, 0.00, '2025-10-06 02:36:13', 1);

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
(11, 'Department of Education', '02', 'IT Equipment', 'Laptop Computer - Lenovo ThinkPad E14, Intel i7, 16GB RAM, 512GB SSD', 'PPE-2024-006', '2024-02-05', 'PAR-2024-005', 0.00, 2.00, 'IT Department - Alex Brown', 100000.00, 'Issued to IT staff for field work', 'issue', NULL, '2025-08-05 06:20:22', '2025-08-05 06:20:22')
ON DUPLICATE KEY UPDATE `pc_id` = `pc_id`;

INSERT INTO `regspi` (`id`, `entity_name`, `fund_cluster`, `semi_expendable_property`, `created_at`, `updated_at`) VALUES
(19, 'TESDA Regional Office', 'b', NULL, '2025-11-07 06:38:28', NULL),
(20, 'TESDA Regional Office', '', NULL, '2025-11-12 02:48:17', NULL),
(21, 'TESDA Regional Office', 'qwe', NULL, '2025-11-12 02:57:38', NULL),
(22, 'TESDA Regional Office', 'cv', NULL, '2025-11-12 03:07:22', NULL)
ON DUPLICATE KEY UPDATE `id` = `id`;

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
(47, 20, '2025-11-18', '0006-11-2025', 'HV-200-101-14', 'Laptop', '5', 0, 'zxcz', 0, NULL, 3, 'zxczxczxc', 0, 0, 10, 3000.00, '', '2025-11-18 06:45:48', NULL)
ON DUPLICATE KEY UPDATE `id` = `id`;

INSERT INTO `ris` (`ris_id`, `entity_name`, `fund_cluster`, `division`, `office`, `responsibility_center_code`, `ris_no`, `date_requested`, `purpose`, `requested_by`, `approved_by`, `issued_by`, `received_by`, `created_at`) VALUES
(31, 'a', 'f', 'FASD', 'TESDA CAR', 'f', '2025/10/0001', '2025-10-06', 'd', 'a', 'a', 'a', 'a', '2025-10-06 02:36:13');

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

INSERT INTO `ris_items` (`item_id`, `ris_id`, `stock_number`, `stock_available`, `issued_quantity`, `remarks`, `unit_cost_at_issue`) VALUES
(0, 31, 'A.03.b', 'Yes', 1, '', 0.00);

INSERT INTO `rspi_items` (`id`, `rspi_id`, `ics_no`, `responsibility_center_code`, `property_no`, `description`, `unit`, `quantity_issued`, `unit_cost`, `created_at`) VALUES
(1, 1, 'ICS-001', 'IT-001', 'PROP-001', 'Desktop Computer', 'unit', 5, 25000.00, '2025-10-27 05:55:30'),
(2, 1, 'ICS-002', 'IT-002', 'PROP-002', 'Printer', 'unit', 2, 8000.00, '2025-10-27 05:55:30'),
(3, 2, 'ICS-003', 'ADM-001', 'PROP-003', 'Office Chair', 'piece', 10, 1500.00, '2025-10-27 05:55:30');

INSERT INTO `rspi_reports` (`id`, `serial_no`, `entity_name`, `fund_cluster`, `report_date`, `custodian_name`, `posted_by`, `status`, `created_at`, `updated_at`) VALUES
(1, '2025-10-001', 'TESDA Provincial Office', '101', '2025-10-20', 'Juan Dela Cruz', 'Maria Santos', 'posted', '2025-10-27 05:55:30', '2025-10-27 05:55:30'),
(2, '2025-10-002', 'TESDA Provincial Office', '102', '2025-10-23', 'Pedro Reyes', 'Maria Santos', 'posted', '2025-10-27 05:55:30', '2025-10-27 05:55:30');

-- Sample data from the semi-expendable schema.
INSERT INTO `property_cards` (`entity_name`, `fund_cluster`, `ppe_type`, `description`, `property_number`, `transaction_date`, `reference_par_no`, `receipt_qty`, `issue_qty`, `office_officer`, `amount`, `remarks`, `transaction_type`) VALUES
  ('Department of Education', '01', 'Office Equipment', 'Desktop Computer - Dell OptiPlex 3070, Intel Core i5, 8GB RAM, 256GB SSD, Serial: DL123456', 'PPE-2024-001', '2024-01-15', 'IAR-2024-001', 1.00, 0.00, NULL, 45000.00, 'Brand new unit for Admin Office', 'receipt'),
  ('Department of Education', '01', 'Office Equipment', 'Desktop Computer - Dell OptiPlex 3070, Intel Core i5, 8GB RAM, 256GB SSD, Serial: DL123456', 'PPE-2024-001', '2024-01-20', 'PAR-2024-001', 0.00, 1.00, 'Admin Office - John Doe', 45000.00, 'Issued to Admin Office', 'issue'),
  ('Department of Education', '01', 'Furniture and Fixtures', 'Office Chair - Ergonomic Swivel Chair, Black Leather, Model: EC-2024', 'PPE-2024-002', '2024-01-16', 'IAR-2024-002', 5.00, 0.00, NULL, 12500.00, 'Set of 5 office chairs', 'receipt'),
  ('Department of Education', '01', 'Furniture and Fixtures', 'Office Chair - Ergonomic Swivel Chair, Black Leather, Model: EC-2024', 'PPE-2024-002', '2024-01-22', 'PAR-2024-002', 0.00, 3.00, 'HR Department - Jane Smith', 7500.00, 'Issued 3 chairs to HR Dept', 'issue'),
  ('Department of Education', '02', 'IT Equipment', 'Printer - HP LaserJet Pro M404dn, Monochrome, Network Ready, Serial: HP789012', 'PPE-2024-003', '2024-01-18', 'IAR-2024-003', 2.00, 0.00, NULL, 24000.00, 'Network printers for offices', 'receipt'),
  ('Department of Education', '02', 'IT Equipment', 'Printer - HP LaserJet Pro M404dn, Monochrome, Network Ready, Serial: HP789012', 'PPE-2024-003', '2024-01-25', 'PAR-2024-003', 0.00, 1.00, 'Finance Office - Mike Johnson', 12000.00, 'Assigned to Finance Office', 'issue'),
  ('Department of Education', '01', 'Appliances', 'Air Conditioning Unit - Split Type 1.5HP, Inverter, Brand: Samsung, Model: AR12NVFXAWKNEU', 'PPE-2024-004', '2024-01-20', 'IAR-2024-004', 1.00, 0.00, NULL, 35000.00, 'For conference room installation', 'receipt'),
  ('Department of Education', '01', 'Office Equipment', 'Filing Cabinet - 4-Drawer Steel Cabinet, Gray Color, with Lock', 'PPE-2024-005', '2024-01-22', 'IAR-2024-005', 3.00, 0.00, NULL, 18000.00, 'Storage cabinets for documents', 'receipt'),
  ('Department of Education', '01', 'Office Equipment', 'Filing Cabinet - 4-Drawer Steel Cabinet, Gray Color, with Lock', 'PPE-2024-005', '2024-01-28', 'PAR-2024-004', 0.00, 2.00, 'Records Office - Sarah Wilson', 12000.00, 'Transferred to Records Office', 'transfer'),
  ('Department of Education', '02', 'IT Equipment', 'Laptop Computer - Lenovo ThinkPad E14, Intel i7, 16GB RAM, 512GB SSD', 'PPE-2024-006', '2024-02-01', 'IAR-2024-006', 4.00, 0.00, NULL, 200000.00, 'Mobile workstations for staff', 'receipt'),
  ('Department of Education', '02', 'IT Equipment', 'Laptop Computer - Lenovo ThinkPad E14, Intel i7, 16GB RAM, 512GB SSD', 'PPE-2024-006', '2024-02-05', 'PAR-2024-005', 0.00, 2.00, 'IT Department - Alex Brown', 100000.00, 'Issued to IT staff for field work', 'issue');


INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-12-09', '22-20', 'HV-22-101-31', 'Water Dispenser (hot, cold and normal) with bottom load, Everest, model ETWD601BL', 5, 1, 'Billy F. Balingit', 1, 9400.00, 'Other PPE'),
('2022-05-10', '22-28', 'LV-22-101-41', 'Whiteboard with aluminum frame and stand, 2x3', 5, 1, 'Belma G. Angoting', 1, 4350.00, 'Other PPE'),
('2022-05-10', '22-29', 'LV-22-101-42', 'Whiteboard with aluminum frame and stand, 2x3', 5, 1, 'Mary Jane C. Bernales', 1, 4350.00, 'Other PPE'),
('2022-02-07', '22-30', 'HV-22-101-43 to 44', 'Bathroom Toilet Tissue, Panasonic model DG4HSJP', 5, 2, 'Billy F. Balingit', 2, 17000.00, 'Other PPE'),
('2022-10-18', '22-32', 'HV-22-101-46', 'Water Dispenser (hot, cold and normal) with bottom load, Everest, model ETWD601BL', 5, 1, 'Billy F. Balingit', 1, 9400.00, 'Other PPE'),
('2022-06-12', '22-37', 'LV-22-101-54', 'Coffee Urn Percolator, Boiler, 40 cups capacity, Dynamex 632', 5, 1, 'Mary Jane C. Bernales', 1, 4467.00, 'Other PPE');


INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-09-03', '22-01', 'LV-22-101-02 to 03', 'WEBCAM FULL HD, A4 TECH, PK-940HA; True 1080p @ 30fps or true 720p @ 60fps; autofocus with fixed mounting clip', 5, 2, 'Daisy D. Jamorabon', 2, 7300.00, 'Office Equipment'),
('2022-09-03', '22-01', 'LV-22-101-04', 'TRIPOD, camera; universal; 2 m or longer; stable', 5, 1, 'Daisy D. Jamorabon', 1, 950.00, 'Office Equipment'),
('2022-11-03', '22-02', 'HV-22-101-05', 'Webcam Full HD, Logitech, True 1080p @ 30fps or 720p@60fps autofocus with fix mounting clip', 5, 1, 'Susana G. Carbonell', 1, 6500.00, 'Office Equipment'),
('2022-11-03', '22-03', 'LV-22-101-06 to 10', 'Computer Headset (wired); HD; A4 tech AU-7P', 5, 5, 'Susana G. Carbonell', 5, 5500.00, 'Office Equipment'),
('2022-11-03', '22-03', 'HV-22-101-11 to 12', 'Wireless Directional Speaker', 5, 2, 'Susana G. Carbonell', 2, 18800.00, 'Office Equipment'),
('2022-07-26', '22-11', 'LV-22-101-19', 'Dryesal Stamp(New TESDA logo)', 5, 1, 'Mabelle G. Panganiban', 1, 2000.00, 'Office Equipment');


INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-09-03', '22-01', 'HV-22-101-01', 'Printer, multi function all in one, HP 3777, deskjet ink advantage, sn: CN19F7J950', 5, 1, 'Daisy D. Jamorabon', 1, 5995.00, 'ICT Equipment'),
('2022-03-28', '22-04', 'HV-22-101-13', 'MONITOR, desktop computer; 24" - 27" minimum; LED; Viewsonic VA; 2415 series; sn: V2M192000234', 5, 1, 'Brenda A. Hiano', 1, 9000.00, 'ICT Equipment'),
('2022-07-29', '22-15', 'HV-22-101-01', 'Video processor, 2GB NVIDIA GeForce Operating', 5, 1, 'Mary Ann L. Gavlican', 1, 49500.00, 'ICT Equipment'),
('2022-10-09', '22-16', 'HV-22-101-27', 'Printer, flatbed with color CIS, Epson Eco Tank L3250', 5, 1, 'Stephanie Nicole S. Pelioman', 1, 10595.00, 'ICT Equipment'),
('2022-08-19', '22-18', 'HV-22-101-30', 'All in one desktop computer, ACER Aspire C24-1750, sn: DBJLQSP01290950000', 5, 1, 'Belmar G. Angoting', 1, 48500.00, 'ICT Equipment');


INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2022-04-05', '22-07', 'HV-22-101-16', 'Cellular Phone, Android, Samsung M 52, 5G + LTE +Wifi', 5, 1, 'Belmar G. Angoting', 1, 14800.00, 'Communication Equipment'),
('2022-10-18', '22-33', 'HV-22-101-47', 'Mobile Phone, 256GB, IOS 16 operating system, phone 13midnight model A2633, SN: R6Q2RXNXNF6', 5, 1, 'RD Jovencio M. Ferrer Jr.', 1, 49850.00, 'Communication Equipment');


INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2021-06-24', '21-16', '21-101-23', 'Chair, executive', 5, 1, 'Dante J. Navarro', 1, 9800.00, 'Furniture and Fixtures');


INSERT INTO semi_expendable_property (date, ics_rrsp_no, semi_expendable_property_no, item_description, estimated_useful_life, quantity_issued, office_officer_issued, quantity_balance, amount_total, category) VALUES
('2021-01-12', '21-01', '21-101-01', 'Microwave Oven, American Home sn:', 5, 1, 'Mary Jane C. Bernales', 1, 7418.00, 'Other PPE'),
('2021-01-12', '21-01', '21-101-02', 'Foam Space Heater sn: 7760247', 5, 1, 'Mary Jane C. Bernales', 1, 3117.00, 'Other PPE'),
('2021-03-17', '21-05', '21-101-03', 'Coffee Percolator, 15 liters capacity, Innoflex', 5, 1, 'Daisy D. Jamorabon', 1, 5860.00, 'Other PPE'),
('2021-03-17', '21-06', '21-101-10', 'Coffee Maker, 10-15 cups capacity, tefal', 5, 1, 'Daisy D. Jamorabon', 1, 6805.00, 'Other PPE'),
('2021-05-04', '21-09', '21-101-14', 'Solar Panel', 5, 1, 'Mary Jane C. Bernales', 1, 2030.00, 'Other PPE');

COMMIT;
