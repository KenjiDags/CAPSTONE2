USE tesda_inventory;

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
-- item_id      = 3
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
    3,
    r.ris_id,
    'A.03.a',
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
-- 6. CHECK MONTHLY ISSUANCE FOR ITEM 3
-- =========================================================

SELECT
    DATE_FORMAT(r.date_requested, '%Y-%m') AS month,
    SUM(ri.issued_quantity) AS total_issued
FROM ris_items ri
JOIN ris r
    ON r.ris_id = ri.ris_id
WHERE ri.item_id = 3
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

WHERE ri.item_id = 3

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
-- CLEANUP FORECAST TEST DATA
-- =========================================================

DELETE ri
FROM ris_items ri
JOIN ris r
    ON r.ris_id = ri.ris_id
WHERE r.ris_no LIKE 'TEST-FORECAST-%';

DELETE FROM ris
WHERE ris_no LIKE 'TEST-FORECAST-%';


-- =========================================================
-- VERIFY CLEANUP
-- Should return 0 rows
-- =========================================================

SELECT
    ris_id,
    ris_no,
    date_requested
FROM ris
WHERE ris_no LIKE 'TEST-FORECAST-%'
ORDER BY date_requested;