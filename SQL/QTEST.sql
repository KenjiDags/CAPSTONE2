-- Import this file using XAMPP phpMyAdmin after importing SQL/tesda_inventory.sql.
-- Select tesda_inventory, open Import, choose this SQL file, and click Go.
-- Adds forecast fixtures and 12 PPE samples without removing real inventory.
USE tesda_inventory;
SET NAMES utf8mb4;
START TRANSACTION;
SET @forecast_item_id = (SELECT item_id FROM items WHERE stock_number = 'A.03.a' ORDER BY item_id LIMIT 1);
SET @forecast_item_id = COALESCE(@forecast_item_id, (SELECT MIN(item_id) FROM items));

-- =========================================================
-- 1. REMOVE ANY OLD FORECAST TEST DATA
-- =========================================================

DELETE ri
FROM ris_items ri
JOIN ris r ON r.ris_id = ri.ris_id
WHERE r.ris_no LIKE 'TEST-FORECAST-%';

DELETE FROM ris
WHERE ris_no LIKE 'TEST-FORECAST-%';


-- =========================================================
-- 2. CREATE 12 MONTHS OF TEST RIS RECORDS
--    October 2025 - September 2026
-- =========================================================

INSERT INTO ris (
    ris_no,
    date_requested,
    purpose,
    requested_by
)
VALUES
(
    'TEST-FORECAST-OCT-2025',
    '2025-10-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-NOV-2025',
    '2025-11-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-DEC-2025',
    '2025-12-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-JAN-2026',
    '2026-01-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-FEB-2026',
    '2026-02-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-MAR-2026',
    '2026-03-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-APR-2026',
    '2026-04-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-MAY-2026',
    '2026-05-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-JUN-2026',
    '2026-06-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-JUL-2026',
    '2026-07-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-AUG-2026',
    '2026-08-15',
    'Temporary forecast test',
    'TEST USER'
),
(
    'TEST-FORECAST-SEP-2026',
    '2026-09-15',
    'Temporary forecast test',
    'TEST USER'
);


-- =========================================================
-- 3. CHECK THE GENERATED RIS IDs
-- =========================================================

SELECT
    ris_id,
    ris_no,
    date_requested
FROM ris
WHERE ris_no LIKE 'TEST-FORECAST-%'
ORDER BY date_requested;


-- =========================================================
-- 4. INSERT TEST ISSUANCE DATA
--
-- Item being tested:
-- item_id      = resolved from A.03.a, falling back to the first item
-- stock_number = A.03.a
--
-- Demand pattern:
-- Oct 2025 = 15
-- Nov 2025 = 18
-- Dec 2025 = 20
-- Jan 2026 = 22
-- Feb 2026 = 24
-- Mar 2026 = 26
-- Apr 2026 = 28
-- May 2026 = 30
-- Jun 2026 = 32
-- Jul 2026 = 35
-- Aug 2026 = 38
-- Sep 2026 = 40
--
-- This creates a generally increasing demand trend.
-- =========================================================

INSERT INTO ris_items (
    item_id,
    ris_id,
    stock_number,
    stock_available,
    issued_quantity,
    remarks,
    unit_cost_at_issue
)
SELECT
    i.item_id,
    r.ris_id,
    i.stock_number,
    'YES',

    CASE r.ris_no
        WHEN 'TEST-FORECAST-OCT-2025' THEN 15
        WHEN 'TEST-FORECAST-NOV-2025' THEN 18
        WHEN 'TEST-FORECAST-DEC-2025' THEN 20
        WHEN 'TEST-FORECAST-JAN-2026' THEN 22
        WHEN 'TEST-FORECAST-FEB-2026' THEN 24
        WHEN 'TEST-FORECAST-MAR-2026' THEN 26
        WHEN 'TEST-FORECAST-APR-2026' THEN 28
        WHEN 'TEST-FORECAST-MAY-2026' THEN 30
        WHEN 'TEST-FORECAST-JUN-2026' THEN 32
        WHEN 'TEST-FORECAST-JUL-2026' THEN 35
        WHEN 'TEST-FORECAST-AUG-2026' THEN 38
        WHEN 'TEST-FORECAST-SEP-2026' THEN 40
    END,

    'TEMPORARY FORECAST TEST',
    0.00

FROM ris r
JOIN items i ON i.item_id = @forecast_item_id
WHERE r.ris_no LIKE 'TEST-FORECAST-%';


-- =========================================================
-- 5. VERIFY THE INSERTED TEST DATA
-- =========================================================

SELECT
    r.ris_id,
    r.ris_no,
    r.date_requested,
    ri.item_id,
    ri.stock_number,
    ri.issued_quantity
FROM ris r
JOIN ris_items ri
    ON ri.ris_id = r.ris_id
WHERE r.ris_no LIKE 'TEST-FORECAST-%'
ORDER BY r.date_requested;


-- =========================================================
-- 6. CHECK MONTHLY ISSUANCE FOR THE SELECTED ITEM
-- =========================================================

SELECT
    DATE_FORMAT(r.date_requested, '%Y-%m') AS month,
    SUM(ri.issued_quantity) AS total_issued
FROM ris_items ri
JOIN ris r
    ON r.ris_id = ri.ris_id
WHERE ri.item_id = @forecast_item_id
AND r.date_requested >= DATE_SUB(
    DATE_FORMAT(CURDATE(), '%Y-%m-01'),
    INTERVAL 12 MONTH
)
AND r.date_requested < DATE_FORMAT(CURDATE(), '%Y-%m-01')
AND ri.issued_quantity > 0
GROUP BY DATE_FORMAT(r.date_requested, '%Y-%m')
ORDER BY month;


-- =========================================================
-- 7. CHECK BASIC FORECAST VALUES
-- =========================================================

SELECT
    ri.item_id,
    ri.stock_number,

    COUNT(DISTINCT DATE_FORMAT(r.date_requested, '%Y-%m'))
        AS months_of_data,

    SUM(ri.issued_quantity)
        AS total_issued,

    ROUND(
        SUM(ri.issued_quantity) /
        COUNT(DISTINCT DATE_FORMAT(r.date_requested, '%Y-%m')),
        2
    ) AS average_monthly_demand,

    MIN(ri.issued_quantity)
        AS lowest_monthly_demand,

    MAX(ri.issued_quantity)
        AS highest_monthly_demand

FROM ris_items ri
JOIN ris r
    ON r.ris_id = ri.ris_id

WHERE ri.item_id = @forecast_item_id

AND r.date_requested >= DATE_SUB(
    DATE_FORMAT(CURDATE(), '%Y-%m-01'),
    INTERVAL 12 MONTH
)

AND r.date_requested < DATE_FORMAT(CURDATE(), '%Y-%m-01')

AND ri.issued_quantity > 0

GROUP BY
    ri.item_id,
    ri.stock_number;


-- =========================================================
-- 8. PPE SAMPLE ITEMS
-- Reserved TEST-PPE identifiers; existing records are never overwritten.
-- These are opening inventory records, without fabricated PAR assignments.
-- =========================================================

INSERT INTO ppe_property
    (PPE_no, property_no, item_name, item_description, amount, quantity, unit,
     officer_incharge, custodian, entity_name, date_acquired, `condition`, status, fund_cluster, remarks)
SELECT sample.PPE_no, sample.property_no, sample.item_name, sample.item_description,
       sample.amount, 1, 'unit', '', 'TEST Property Custodian', 'TESDA Regional Office',
       sample.date_acquired, sample.item_condition, sample.item_status, '101', 'QTEST dummy PPE item'
FROM (
    SELECT 'TEST-PPE-001' AS PPE_no, 'TEST-PROP-001' AS property_no, 'Desktop Computer' AS item_name, 'Training workstation, Core i7, 16 GB RAM, 512 GB SSD' AS item_description, 65000.00 AS amount, '2026-01-12' AS date_acquired, 'Good' AS item_condition, 'Active' AS item_status
    UNION ALL SELECT 'TEST-PPE-002', 'TEST-PROP-002', 'Laptop Computer', 'Portable training laptop, Core i7, 16 GB RAM, 1 TB SSD', 78000.00, '2026-02-05', 'Good', 'Active'
    UNION ALL SELECT 'TEST-PPE-003', 'TEST-PROP-003', 'Multimedia Projector', 'Full HD laser projector for training room', 95000.00, '2025-11-18', 'Good', 'Active'
    UNION ALL SELECT 'TEST-PPE-004', 'TEST-PROP-004', 'Multifunction Copier', 'Network copier with automatic duplex printing and scanning', 185000.00, '2025-10-22', 'Fair', 'Active'
    UNION ALL SELECT 'TEST-PPE-005', 'TEST-PROP-005', 'Air Conditioning Unit', '3 HP inverter split-type air conditioner', 92000.00, '2026-03-10', 'Good', 'Active'
    UNION ALL SELECT 'TEST-PPE-006', 'TEST-PROP-006', 'Server Computer', 'Rack server, 32 GB ECC RAM, redundant storage', 250000.00, '2025-09-15', 'Good', 'Active'
    UNION ALL SELECT 'TEST-PPE-007', 'TEST-PROP-007', 'Interactive Display', '75-inch interactive touchscreen for classroom instruction', 165000.00, '2026-04-08', 'Good', 'Active'
    UNION ALL SELECT 'TEST-PPE-008', 'TEST-PROP-008', 'Diesel Generator', '10 kVA standby diesel generator', 320000.00, '2024-06-20', 'Fair', 'Active'
    UNION ALL SELECT 'TEST-PPE-009', 'TEST-PROP-009', 'Industrial Sewing Machine', 'Computerized industrial sewing machine for training', 85000.00, '2025-07-11', 'Poor', 'For Repair'
    UNION ALL SELECT 'TEST-PPE-010', 'TEST-PROP-010', 'Welding Machine', 'Industrial TIG welding machine with accessories', 110000.00, '2025-08-14', 'Good', 'Active'
    UNION ALL SELECT 'TEST-PPE-011', 'TEST-PROP-011', 'Heavy Duty Scanner', 'High-speed document scanner with automatic feeder', 62000.00, '2023-05-16', 'Unserviceable', 'Unserviceable'
    UNION ALL SELECT 'TEST-PPE-012', 'TEST-PROP-012', 'Training Lathe', 'Bench metal lathe with tool set and safety guard', 275000.00, '2024-09-03', 'Fair', 'Active'
) AS sample
WHERE NOT EXISTS (SELECT 1 FROM ppe_property existing WHERE existing.PPE_no = sample.PPE_no);

INSERT INTO ppe_pc
    (date_created, ppe_property_no, property_no, item_name, item_description, amount,
     quantity, unit, custodian, officer, entity_name, fund_cluster, remarks)
SELECT p.date_acquired, p.PPE_no, p.property_no, p.item_name, p.item_description,
       p.amount, p.quantity, p.unit, p.custodian, p.officer_incharge, p.entity_name,
       p.fund_cluster, p.remarks
FROM ppe_property p
WHERE p.PPE_no IN ('TEST-PPE-001', 'TEST-PPE-002', 'TEST-PPE-003', 'TEST-PPE-004',
                   'TEST-PPE-005', 'TEST-PPE-006', 'TEST-PPE-007', 'TEST-PPE-008',
                   'TEST-PPE-009', 'TEST-PPE-010', 'TEST-PPE-011', 'TEST-PPE-012')
  AND p.remarks = 'QTEST dummy PPE item'
  AND NOT EXISTS (SELECT 1 FROM ppe_pc pc WHERE pc.ppe_property_no = p.PPE_no);

INSERT INTO item_history_ppe
    (PPE_no, property_no, item_name, description, unit, unit_cost, quantity_on_hand,
     quantity_change, receipt_qty, issue_qty, balance_qty, officer_incharge,
     change_direction, change_type, changed_at)
SELECT p.PPE_no, p.property_no, p.item_name, p.item_description, p.unit, p.amount,
       p.quantity, p.quantity, p.quantity, 0, p.quantity, p.custodian,
       'increase', 'add', CONCAT(p.date_acquired, ' 09:00:00')
FROM ppe_property p
WHERE p.PPE_no IN ('TEST-PPE-001', 'TEST-PPE-002', 'TEST-PPE-003', 'TEST-PPE-004',
                   'TEST-PPE-005', 'TEST-PPE-006', 'TEST-PPE-007', 'TEST-PPE-008',
                   'TEST-PPE-009', 'TEST-PPE-010', 'TEST-PPE-011', 'TEST-PPE-012')
  AND p.remarks = 'QTEST dummy PPE item'
  AND NOT EXISTS (SELECT 1 FROM item_history_ppe h WHERE h.PPE_no = p.PPE_no);

COMMIT;

SELECT PPE_no, property_no, item_name, amount, quantity, `condition`, status
FROM ppe_property
WHERE remarks = 'QTEST dummy PPE item'
ORDER BY PPE_no;