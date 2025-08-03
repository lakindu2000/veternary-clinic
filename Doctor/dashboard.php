<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

require_once '../connection.php';

$user_id = $_SESSION['user_id'];
$doctorName = '';
$doctorId = 0;
$photoPath = '';

// Fetch doctor data
$sql = "SELECT d.name, d.id, d.photo FROM doctors d JOIN users u ON d.user_id = u.id WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $doctor = $result->fetch_assoc();
    $doctorName = $doctor['name'];
    $doctorId = $doctor['id'];
    $photoPath = !empty($doctor['photo']) ? '../uploads/' . $doctor['photo'] : '../uploads/default.png';
} else {
    $doctorName = "Doctor";
    $photoPath = '../uploads/default.png';
}
$stmt->close();

// Cancel appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment_id'])) {
    $cancelId = intval($_POST['cancel_appointment_id']);
    $cancelQuery = "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND doctor_id = ?";
    $cancelStmt = $conn->prepare($cancelQuery);
    $cancelStmt->bind_param("ii", $cancelId, $doctorId);
    $cancelStmt->execute();
    $cancelStmt->close();
    header("Location: dashboard.php");
    exit();
}

// Total patients
$sqlPatients = "SELECT COUNT(DISTINCT patient_id) AS count FROM medical_records WHERE doctor_id = ?";
$stmtPatients = $conn->prepare($sqlPatients);
$stmtPatients->bind_param("i", $doctorId);
$stmtPatients->execute();
$resultPatients = $stmtPatients->get_result();
$totalPatients = ($row = $resultPatients->fetch_assoc()) ? $row['count'] : 0;
$stmtPatients->close();

// Today's appointments
$currentDate = date('Y-m-d');
$sqlAppointments = "SELECT COUNT(*) AS count FROM appointments WHERE doctor_id = ? AND appointment_date = ?";
$stmtAppointments = $conn->prepare($sqlAppointments);
$stmtAppointments->bind_param("is", $doctorId, $currentDate);
$stmtAppointments->execute();
$resultAppointments = $stmtAppointments->get_result();
$todayAppointments = ($row = $resultAppointments->fetch_assoc()) ? $row['count'] : 0;
$stmtAppointments->close();

// Monthly counts
$year = date('Y');
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthKeys = ['01','02','03','04','05','06','07','08','09','10','11','12'];
$monthlyCounts = array_fill_keys($monthKeys, 0);
$sqlMonthly = "SELECT DATE_FORMAT(created_at, '%m') AS month_num, COUNT(DISTINCT patient_id) AS patient_count
               FROM medical_records WHERE doctor_id = ? AND YEAR(created_at) = ? GROUP BY month_num";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("ii", $doctorId, $year);
$stmtMonthly->execute();
$resultMonthly = $stmtMonthly->get_result();
while ($row = $resultMonthly->fetch_assoc()) {
    $monthlyCounts[$row['month_num']] = (int)$row['patient_count'];
}
$stmtMonthly->close();
$counts = array_values($monthlyCounts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .doctor-photo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid white;
        }
    </style>
</head>
<body class="bg-light">

<!-- Header -->
<div class="row g-0 align-items-center" style="background-color: #4e8cff; min-height: 120px;">
    <div class="col-md-1 text-center">
        <img src="<?php echo htmlspecialchars($photoPath); ?>" class="doctor-photo mt-3 mb-3" alt="Doctor Photo">
    </div>
    <div class="col-md-11 d-flex flex-column justify-content-center align-items-center text-white">
        <h3 class="fw-bold">Welcome, Dr.<?php echo htmlspecialchars($doctorName); ?></h3>
        <p class="mb-0">Here is your activity summary for today.</p>
    </div>
</div>

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

    <!-- Main Content -->
    <div class="col-9 p-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card text-white bg-primary shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-md me-2"></i>Total Patients</h5>
                        <h2 class="card-text"><?php echo $totalPatients; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-white bg-success shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-check me-2"></i>Today's Appointments</h5>
                        <h2 class="card-text"><?php echo $todayAppointments; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Bar Chart -->
            <div class="col-md-8">
                <div class="card shadow-sm p-4">
                    <h4 class="mb-4">Monthly Patient Count (<?php echo $year; ?>)</h4>
                    <canvas id="patientBarChart" width="100%" height="60"></canvas>
                </div>
            </div>

            <!-- Appointments -->
            <div class="col-md-4">
                <div class="card shadow-sm p-3" style="max-height: 500px; overflow-y: auto;">
                    <h5 class="mb-3">Today's Appointments</h5>
                    <?php
                    $sqlTodayAppointments = "SELECT id, patient_id, appointment_time FROM appointments 
                                             WHERE doctor_id = ? AND appointment_date = ? AND status = 'scheduled' 
                                             ORDER BY appointment_time ASC";
                    $stmtToday = $conn->prepare($sqlTodayAppointments);
                    $stmtToday->bind_param("is", $doctorId, $currentDate);
                    $stmtToday->execute();
                    $resultToday = $stmtToday->get_result();

                    if ($resultToday->num_rows > 0) {
                        while ($appointment = $resultToday->fetch_assoc()) {
                            echo '<div class="border p-2 rounded mb-2">';
                            echo '<strong>Appointment ID:</strong> ' . $appointment['id'] . '<br>';
                            echo '<strong>Patient ID:</strong> ' . $appointment['patient_id'] . '<br>';
                            echo '<strong>Time:</strong> ' . date('h:i A', strtotime($appointment['appointment_time'])) . '<br>';
                            echo '<form method="post" onsubmit="return confirm(\'Are you sure you want to cancel this appointment?\');">';
                            echo '<input type="hidden" name="cancel_appointment_id" value="' . $appointment['id'] . '">';
                            echo '<button type="submit" class="btn btn-sm btn-danger mt-2">Cancel</button>';
                            echo '</form>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p class="text-muted">No scheduled appointments today.</p>';
                    }

                    $stmtToday->close();
                    $conn->close();
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script>
    const ctx = document.getElementById('patientBarChart').getContext('2d');
    const barChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Patients',
                data: <?php echo json_encode($counts); ?>,
                backgroundColor: '#4e8cff',
                borderColor: '#2e6be0',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            scales: {
                x: { title: { display: true, text: 'Month' } },
                y: { beginAtZero: true, title: { display: true, text: 'Number of Patients' }, ticks: { precision: 0 } }
            },
            plugins: { legend: { display: false } }
        }
    });
</script>

</body>
</html>
