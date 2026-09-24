<?php
require '../config.php';

echo "Adding officer_id column to officers table...\n";

// Check if table exists
$check = $conn->query("SHOW TABLES LIKE 'officers'");
if ($check->num_rows == 0) {
    echo "Officers table does not exist yet. Creating it...\n";
    $conn->query("
        CREATE TABLE IF NOT EXISTS `officers` (
            officer_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            officer_name varchar(255) NOT NULL,
            officer_position varchar(255) NOT NULL
        )
    ");
    echo "Officers table created successfully!\n";
} else {
    // Check if officer_id column already exists
    $result = $conn->query("SHOW COLUMNS FROM officers LIKE 'officer_id'");
    
    if ($result->num_rows == 0) {
        echo "Adding officer_id column...\n";
        
        // Add the officer_id column
        $conn->query("ALTER TABLE officers ADD COLUMN officer_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST");
        
        echo "Successfully added officer_id column to officers table!\n";
    } else {
        echo "officer_id column already exists. No changes needed.\n";
    }
}

$conn->close();
echo "Migration completed!\n";
?>
