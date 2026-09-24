<?php
/**
 * Database Reset Script
 * Clears all inventory data while preserving table structure
 * WARNING: This will delete ALL data from the inventory system!
 */

require '../config.php';

// Check if user confirms the reset
$confirmed = isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'yes';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Reset</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 50px auto; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .warning h2 { margin-top: 0; color: #856404; }
        .success { background: #d4edda; border: 1px solid #28a745; padding: 15px; border-radius: 4px; margin-bottom: 20px; color: #155724; }
        button { padding: 10px 20px; margin-right: 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn-reset { background: #dc3545; color: white; }
        .btn-reset:hover { background: #c82333; }
        .btn-cancel { background: #6c757d; color: white; }
        .btn-cancel:hover { background: #5a6268; }
        .form-group { margin-top: 20px; }
        input[type="checkbox"] { margin-right: 10px; }
        label { font-weight: bold; }
    </style>
</head>
<body>

<?php if ($confirmed): ?>
    <?php
    try {
        $conn->begin_transaction();
        
        // Disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Clear child/items tables first
        $tables_items = [
            'semi_expendable_history',
            'ics_items',
            'ics_history',
            'rrsp_items',
            'rrsp_history',
            'itr_items',
            'itr_history',
            'iirusp_items',
            'iirusp_history',
            'regspi_entries'
        ];
        
        foreach ($tables_items as $table) {
            $conn->query("DELETE FROM $table");
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
        }
        
        // Clear master/header tables
        $tables_master = [
            'semi_expendable_property',
            'ics',
            'rrsp',
            'itr',
            'iirusp',
            'regspi'
        ];
        
        foreach ($tables_master as $table) {
            $conn->query("DELETE FROM $table");
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
        }
        
        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        
        $conn->commit();
        ?>
        <div class="success">
            <h2>✅ Database Reset Successful!</h2>
            <p>All inventory data has been cleared. The database is now ready for fresh data entry.</p>
            <p>All tables have been reset with AUTO_INCREMENT starting at 1.</p>
            <a href="../inventory.php" style="display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">Return to Inventory</a>
        </div>
        <?php
    } catch (Exception $e) {
        $conn->rollback();
        ?>
        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; color: #721c24;">
            <h2>❌ Reset Failed</h2>
            <p>Error: <?= htmlspecialchars($e->getMessage()) ?></p>
        </div>
        <?php
    }
else:
    ?>
    <div class="warning">
        <h2>⚠️ WARNING: Database Reset</h2>
        <p><strong>This action will permanently delete ALL inventory data:</strong></p>
        <ul>
            <li>All ICS entries and history</li>
            <li>All ITR (Transfer) records</li>
            <li>All RRSP records</li>
            <li>All IIRUSP (Disposal) records</li>
            <li>All Semi-Expendable Property records</li>
            <li>All RegSPI records</li>
            <li>All transaction history</li>
        </ul>
        <p><strong>This action CANNOT be undone!</strong></p>
        <p>Only table structure will be preserved. AUTO_INCREMENT counters will be reset to 1.</p>
    </div>

    <form method="post">
        <div class="form-group">
            <input type="checkbox" id="confirm" name="confirm_reset" value="yes" required>
            <label for="confirm">I understand this will delete ALL data and cannot be undone</label>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn-reset">🗑️ Reset Database</button>
            <a href="../inventory.php" class="btn-cancel" style="text-decoration: none; display: inline-block;">Cancel</a>
        </div>
    </form>
<?php endif; ?>

</body>
</html>
