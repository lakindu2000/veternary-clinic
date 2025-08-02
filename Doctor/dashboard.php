<?php
session_start();

// Check if doctor is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

// DB Connection
require_once '../connection.php';

// Fetch doctor's name
$doctorName = '';
$user_id = $_SESSION['user_id'];

$sql = "SELECT d.name 
        FROM doctors d 
        JOIN users u ON d.user_id = u.id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $doctor = $result->fetch_assoc();
    $doctorName = $doctor['name'];
} else {
    $doctorName = "Doctor";
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap & Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<!-- Header Section -->
<div class="row g-0" style="background-color: #4e8cff;">
    <div class="col-12 p-3">
        <div class="d-flex align-items-center">
            <div class="flex-grow-1 text-center">
                <h3 class="text-white mb-0 fw-bold"><?php echo htmlspecialchars($doctorName); ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Main Area -->
<div class="row g-0 min-vh-100">
    <!-- Sidebar -->
    <div class="col-3 bg-white shadow-sm">
        <div class="p-3">
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action active border-0 rounded mb-2">
                    <i class="fas fa-home me-3"></i>Dashboard
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                    <i class="fas fa-user me-3"></i>Profile View
                </a>
                <a href="patients.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                    <i class="fas fa-users me-3"></i>Patients
                </a>
                <a href="add_health.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                    <i class="fas fa-plus-circle me-3"></i>Add Health Details
                </a>
                <a href="../logout.php" class="list-group-item list-group-item-action border-0 rounded mb-2 text-danger">
                    <i class="fas fa-sign-out-alt me-3"></i>Log Out
                </a>
            </div>
        </div>
    </div>

    <!-- Dashboard Content Area -->
    <div class="col-9 p-4">
        <div class="card shadow-sm p-4">
            <h4 class="mb-3">Welcome, Dr. <?php echo htmlspecialchars($doctorName); ?> 👨‍⚕️</h4>
            <p>This is your dashboard. Use the sidebar to navigate through the system.</p>
            <!-- Add future dashboard widgets here -->
        </div>
    </div>
</div>

</body>
</html>
