<?php
$servername = "localhost";
$username = "root"; // Default XAMPP usernames
$password = "";     // Default XAMPP has no password
$database = "vet_clinic";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
