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
-- 2. CREATE THREE TEST RIS RECORDS
-- =========================================================

INSERT INTO ris (
    ris_no,
    date_requested,
    purpose,
    requested_by
)
VALUES
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
);


-- =========================================================
-- 3. GET THE ACTUAL RIS IDs
-- =========================================================

SELECT
    ris_id,
    ris_no,
    date_requested
FROM ris
WHERE ris_no LIKE 'TEST-FORECAST-%'
ORDER BY date_requested;

INSERT INTO ris_items (
    item_id,
    ris_id,
    stock_number,
    stock_available,
    issued_quantity,
    remarks,
    unit_cost_at_issue
)
VALUES
(3, 45, 'A.03.a', 'YES', 20, 'TEMPORARY FORECAST TEST', 0.00),
(3, 46, 'A.03.a', 'YES', 30, 'TEMPORARY FORECAST TEST', 0.00),
(3, 47, 'A.03.a', 'YES', 25, 'TEMPORARY FORECAST TEST', 0.00);

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


SELECT
    DATE_FORMAT(r.date_requested, '%Y-%m') AS month,
    SUM(ri.issued_quantity) AS total_issued
FROM ris_items ri
JOIN ris r
    ON r.ris_id = ri.ris_id
WHERE r.date_requested >= DATE_SUB(
    DATE_FORMAT(CURDATE(), '%Y-%m-01'),
    INTERVAL 12 MONTH
)
AND r.date_requested < DATE_FORMAT(CURDATE(), '%Y-%m-01')
AND ri.issued_quantity > 0
GROUP BY DATE_FORMAT(r.date_requested, '%Y-%m')
ORDER BY month;


-- Cleanup test data after verification -- 

DELETE ri
FROM ris_items ri
JOIN ris r
    ON r.ris_id = ri.ris_id
WHERE r.ris_no LIKE 'TEST-FORECAST-%';

DELETE FROM ris
WHERE ris_no LIKE 'TEST-FORECAST-%';

SELECT
    ris_id,
    ris_no,
    date_requested
FROM ris
WHERE ris_no LIKE 'TEST-FORECAST-%'
ORDER BY date_requested;