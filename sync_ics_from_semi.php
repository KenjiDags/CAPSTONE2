<?php
require_once 'config.php';
require_once 'functions.php';

// This script creates ICS entries for semi_expendable_property records that have an ics_rrsp_no but no matching ICS record

$synced = 0;
$skipped = 0;
$errors = [];
$ics_groups = [];

echo "<h2>ICS Sync from Semi-Expendable Records</h2>";
echo "<p>Scanning for records with ICS numbers but no corresponding ICS entries...</p>";

try {
    // Find all semi-expendable records with ICS numbers that don't exist in the ics table
    $query = "SELECT s.* FROM semi_expendable_property s 
              WHERE s.ics_rrsp_no IS NOT NULL 
              AND s.ics_rrsp_no != '' 
              ORDER BY s.ics_rrsp_no ASC, s.date ASC, s.id ASC";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        // Group records by ICS number
        while ($row = $result->fetch_assoc()) {
            $ics_no = $row['ics_rrsp_no'];
            if (!isset($ics_groups[$ics_no])) {
                $ics_groups[$ics_no] = [];
            }
            $ics_groups[$ics_no][] = $row;
        }
    }
    
    echo "<p>Found " . count($ics_groups) . " unique ICS numbers to process.</p>";
    echo "<hr>";
    
    // Process each ICS number group
    foreach ($ics_groups as $ics_no => $items) {
        // Check if ICS already exists
        $checkStmt = $conn->prepare("SELECT ics_id FROM ics WHERE ics_no = ? LIMIT 1");
        $checkStmt->bind_param("s", $ics_no);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult && $checkResult->num_rows > 0) {
            // ICS already exists, check if items need to be added
            $existing_ics = $checkResult->fetch_assoc();
            $ics_id = $existing_ics['ics_id'];
            $checkStmt->close();
            
            echo "<strong>ICS $ics_no</strong> already exists (ID: $ics_id). Adding missing items...<br>";
            
            // Add items that don't exist
            foreach ($items as $row) {
                $stock_no = $row['semi_expendable_property_no'];
                
                // Check if this item already exists in ics_items
                $checkItem = $conn->prepare("SELECT ics_item_id FROM ics_items WHERE ics_id = ? AND stock_number = ? LIMIT 1");
                $checkItem->bind_param("is", $ics_id, $stock_no);
                $checkItem->execute();
                $itemResult = $checkItem->get_result();
                
                if ($itemResult && $itemResult->num_rows > 0) {
                    $checkItem->close();
                    $skipped++;
                    echo "&nbsp;&nbsp;→ Item $stock_no already exists, skipping<br>";
                } else {
                    $checkItem->close();
                    
                    // Add the item - use quantity_issued for ICS items
                    // In semi_expendable_property: quantity = base/receipt qty, quantity_issued = issued-out qty
                    $quantity = (float)($row['quantity_issued'] ?? 0);
                    if ($quantity <= 0) {
                        // If no quantity_issued, skip this item
                        $skipped++;
                        echo "&nbsp;&nbsp;→ Item $stock_no has no issued quantity, skipping<br>";
                        continue;
                    }
                    $unit = $row['unit'] ?? '';
                    
                    // Calculate unit cost: if amount_total exists, divide by quantity; otherwise use amount
                    $amount_total = (float)($row['amount_total'] ?? 0);
                    if ($amount_total > 0 && $quantity > 0) {
                        $unit_cost = $amount_total / $quantity;
                        $total_cost = $amount_total;
                    } else {
                        $unit_cost = (float)($row['amount'] ?? 0);
                        $total_cost = $quantity * $unit_cost;
                    }
                    $description = $row['item_description'];
                    $estimated_useful_life = (string)($row['estimated_useful_life'] ?? '');
                    $serial_no = '';
                    
                    echo "&nbsp;&nbsp;→ Qty: $quantity, Unit Cost: $unit_cost, Total: $total_cost<br>";
                    
                    $stmt2 = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, description, inventory_item_no, estimated_useful_life, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt2) {
                        $stmt2->bind_param("isdsddssss", $ics_id, $stock_no, $quantity, $unit, $unit_cost, $total_cost, $description, $stock_no, $estimated_useful_life, $serial_no);
                        if ($stmt2->execute()) {
                            $synced++;
                            echo "&nbsp;&nbsp;✓ Added item: $description ($stock_no)<br>";
                        } else {
                            $errors[] = "Failed to add item $stock_no to ICS $ics_no: " . $stmt2->error;
                        }
                        $stmt2->close();
                    }
                }
            }
            
        } else {
            // ICS doesn't exist, create it
            $checkStmt->close();
            
            $first_item = $items[0];
            $date = $first_item['date'];
            $entity_name = 'TESDA Regional Office';
            $fund_cluster = $first_item['fund_cluster'] ?? '101';
            $received_by = $first_item['office_officer_issued'] ?? 'N/A';
            $received_by_position = '(imported)';
            $received_from = 'Property Custodian';
            $received_from_position = '(imported)';
            
            echo "<strong>Creating new ICS $ics_no</strong> with " . count($items) . " item(s)...<br>";
            
            // Create ICS header
            $stmt = $conn->prepare("INSERT INTO ics (ics_no, entity_name, fund_cluster, date_issued, received_by, received_by_position, received_from, received_from_position) VALUES (?,?,?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param("ssssssss", $ics_no, $entity_name, $fund_cluster, $date, $received_by, $received_by_position, $received_from, $received_from_position);
                if ($stmt->execute()) {
                    $new_ics_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Add all items for this ICS
                    foreach ($items as $row) {
                        $stock_no = $row['semi_expendable_property_no'];
                        // In semi_expendable_property: quantity = base/receipt qty, quantity_issued = issued-out qty
                        $quantity = (float)($row['quantity_issued'] ?? 0);
                        if ($quantity <= 0) {
                            // If no quantity_issued, skip this item
                            $skipped++;
                            echo "&nbsp;&nbsp;→ Item $stock_no has no issued quantity, skipping<br>";
                            continue;
                        }
                        $unit = $row['unit'] ?? '';
                        
                        // Calculate unit cost: if amount_total exists, divide by quantity; otherwise use amount
                        $amount_total = (float)($row['amount_total'] ?? 0);
                        if ($amount_total > 0 && $quantity > 0) {
                            $unit_cost = $amount_total / $quantity;
                            $total_cost = $amount_total;
                        } else {
                            $unit_cost = (float)($row['amount'] ?? 0);
                            $total_cost = $quantity * $unit_cost;
                        }
                        $description = $row['item_description'];
                        $estimated_useful_life = (string)($row['estimated_useful_life'] ?? '');
                        $serial_no = '';
                        
                        echo "&nbsp;&nbsp;→ Qty: $quantity, Unit Cost: $unit_cost, Total: $total_cost<br>";
                        
                        $stmt2 = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, description, inventory_item_no, estimated_useful_life, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt2) {
                            $stmt2->bind_param("isdsddssss", $new_ics_id, $stock_no, $quantity, $unit, $unit_cost, $total_cost, $description, $stock_no, $estimated_useful_life, $serial_no);
                            if ($stmt2->execute()) {
                                $synced++;
                                echo "&nbsp;&nbsp;✓ Added item: $description ($stock_no)<br>";
                            } else {
                                $errors[] = "Failed to create ICS item for $ics_no ($stock_no): " . $stmt2->error;
                            }
                            $stmt2->close();
                        }
                    }
                } else {
                    $errors[] = "Failed to create ICS header for $ics_no: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
        echo "<br>";
    }
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $ics_no = $row['ics_rrsp_no'];
            $date = $row['date'];
            $entity_name = 'TESDA Regional Office';
            $fund_cluster = $row['fund_cluster'] ?? '101';
            $received_by = $row['office_officer_issued'] ?? 'N/A';
            $received_by_position = '(imported)';
            $received_from = 'Property Custodian';
            $received_from_position = '(imported)';
            
            // Create ICS header
            $stmt = $conn->prepare("INSERT INTO ics (ics_no, entity_name, fund_cluster, date_issued, received_by, received_by_position, received_from, received_from_position) VALUES (?,?,?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param("ssssssss", $ics_no, $entity_name, $fund_cluster, $date, $received_by, $received_by_position, $received_from, $received_from_position);
                if ($stmt->execute()) {
                    $new_ics_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Create ICS item
                    $stock_no = $row['semi_expendable_property_no'];
                    $quantity = (float)($row['quantity_issued'] ?? 1);
                    $unit = $row['unit'] ?? '';
                    $unit_cost = (float)($row['amount'] ?? 0);
                    $total_cost = $quantity * $unit_cost;
                    $description = $row['item_description'];
                    $estimated_useful_life = (string)($row['estimated_useful_life'] ?? '');
                    $serial_no = '';
                    
                    $stmt2 = $conn->prepare("INSERT INTO ics_items (ics_id, stock_number, quantity, unit, unit_cost, total_cost, description, inventory_item_no, estimated_useful_life, serial_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt2) {
                        $stmt2->bind_param("isdsddssss", $new_ics_id, $stock_no, $quantity, $unit, $unit_cost, $total_cost, $description, $stock_no, $estimated_useful_life, $serial_no);
                        if ($stmt2->execute()) {
                            $synced++;
                            echo "✓ Created ICS $ics_no for item: $description<br>";
                        } else {
                            $errors[] = "Failed to create ICS item for $ics_no: " . $stmt2->error;
                        }
                        $stmt2->close();
                    }
                } else {
                    $errors[] = "Failed to create ICS header for $ics_no: " . $stmt->error;
                    $stmt->close();
                }
            }
        }
    }
    
    echo "<hr>";
    echo "<h3>Summary</h3>";
    echo "<p><strong>Items synced:</strong> $synced</p>";
    echo "<p><strong>Items skipped (already exist):</strong> $skipped</p>";
    echo "<p><strong>Unique ICS numbers processed:</strong> " . count($ics_groups) . "</p>";
    
    if (!empty($errors)) {
        echo "<p><strong>Errors:</strong></p><ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
    }
    
    if ($synced === 0 && $skipped === 0 && empty($errors)) {
        echo "<p>No records to sync. All semi-expendable items with ICS numbers already have corresponding ICS entries.</p>";
    }
    
    echo "<br><br><a href='ics.php' style='padding:10px 15px; background:#007bff; color:white; text-decoration:none; border-radius:4px;'>Go to ICS List</a> ";
    echo "<a href='semi_expendible.php' style='padding:10px 15px; background:#6c757d; color:white; text-decoration:none; border-radius:4px;'>Go to Semi-Expendable List</a>";
    
} catch (Exception $e) {
    echo "<p style='color:red; padding:15px; background:#fee; border:1px solid #fcc;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

$conn->close();
?>
