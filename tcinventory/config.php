<?php
$servername = "localhost";
$username   = "root"; // default for XAMPP
$password   = "";     // default for XAMPP

// Connect to MySQL (choose any default database, it doesn’t matter)
$conn = new mysqli($servername, $username, $password, "tesda_inventory");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
