<?php
$servername = "localhost"; 
$username = "root";        
$password = ""; 
$database = "vet_clinic"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";

// Email configuration (update with your SMTP details)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@example.com');
define('SMTP_PASSWORD', 'your_email_password');
define('SMTP_FROM', 'no-reply@vetclinic.com');
define('SMTP_FROM_NAME', 'Vet Clinic System');
?>
?>
