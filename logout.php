<?php
session_start();

// Check if user was actually logged in
$was_logged_in = isset($_SESSION['user_id']);

// Update last login time if user was logged in
if ($was_logged_in && isset($_SESSION['user_id'])) {
    require_once 'connection.php';
    
    // Check if last_login column exists, if not add it
    $columns_check = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if ($columns_check && $columns_check->num_rows == 0) {
        // Add missing columns
        $conn->query("ALTER TABLE users ADD COLUMN last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
    
    // Update last_login timestamp
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}

// Clear all session variables
$_SESSION = array();

// Delete the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect directly to login page
header("Location: login.php");
exit();
?>
