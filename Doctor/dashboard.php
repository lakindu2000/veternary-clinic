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

// Fetch doctor details
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

// Cancel appointment if requested
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

// Total medical records
$sqlRecords = "SELECT COUNT(*) AS count FROM medical_records WHERE doctor_id = ?";
$stmtRecords = $conn->prepare($sqlRecords);
$stmtRecords->bind_param("i", $doctorId);
$stmtRecords->execute();
$resultRecords = $stmtRecords->get_result();
$totalPatients = ($row = $resultRecords->fetch_assoc()) ? $row['count'] : 0;
$stmtRecords->close();

// Today's appointments count
$sqlAppointments = "SELECT COUNT(*) AS count FROM appointments WHERE doctor_id = ? AND appointment_date = CURDATE()";
$stmtAppointments = $conn->prepare($sqlAppointments);
$stmtAppointments->bind_param("i", $doctorId);
$stmtAppointments->execute();
$resultAppointments = $stmtAppointments->get_result();
$todayAppointments = ($row = $resultAppointments->fetch_assoc()) ? $row['count'] : 0;
$stmtAppointments->close();

// Monthly patient counts
$year = date('Y');
$months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthKeys = ['01','02','03','04','05','06','07','08','09','10','11','12'];
$monthlyCounts = array_fill_keys($monthKeys, 0);

$sqlMonthly = "SELECT DATE_FORMAT(created_at, '%m') AS month_num, COUNT(*) AS patient_count
               FROM medical_records WHERE doctor_id = ? AND YEAR(created_at) = ? GROUP BY month_num";
$stmtMonthly = $conn->prepare($sqlMonthly);
$stmtMonthly->bind_param("ii", $doctorId, $year);
$stmtMonthly->execute();
$resultMonthly = $stmtMonthly->get_result();
while ($row = $resultMonthly->fetch_assoc()) {
    $monthlyCounts[$row['month_num']] = (int)$row['patient_count'];
}
$stmtMonthly->close();
$monthlyData = array_values($monthlyCounts);

// Daily patient counts for current month
$dailyCounts = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, date('m'), $year);
for ($i = 1; $i <= $daysInMonth; $i++) {
    $day = str_pad($i, 2, '0', STR_PAD_LEFT);
    $dailyCounts[$day] = 0;
}
$sqlDaily = "SELECT DATE_FORMAT(created_at, '%d') AS day_num, COUNT(*) AS count
             FROM medical_records 
             WHERE doctor_id = ? 
             AND MONTH(created_at) = MONTH(CURDATE()) 
             AND YEAR(created_at) = YEAR(CURDATE())
             GROUP BY day_num";
$stmtDaily = $conn->prepare($sqlDaily);
$stmtDaily->bind_param("i", $doctorId);
$stmtDaily->execute();
$resultDaily = $stmtDaily->get_result();
while ($row = $resultDaily->fetch_assoc()) {
    $dailyCounts[$row['day_num']] = (int)$row['count'];
}
$stmtDaily->close();
$dayLabels = array_keys($dailyCounts);
$dayValues = array_values($dailyCounts);

// Auto cancel past appointments
$cancelPast = "UPDATE appointments SET status = 'cancelled' 
               WHERE doctor_id = ? AND appointment_date = CURDATE() AND appointment_time < (SELECT TIME(NOW())) AND status = 'scheduled'";
$stmtAutoCancel = $conn->prepare($cancelPast);
$stmtAutoCancel->bind_param("i", $doctorId);
$stmtAutoCancel->execute();
$stmtAutoCancel->close();
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
                    <i class="fas fa-user me-3"></i>View Profile
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
            <div class="col-8">
                <div class="row">  
                    <div class="col-md-6">
                        <div class="card text-white bg-primary shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-user-md me-2"></i>Total Medical Records</h5>
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

                <!-- Chart Section -->
                <div class="row mt-4">
                    <div class="col-md">
                        <div class="card shadow-sm p-4 position-relative">
                            <h4 class="mb-3">Patient Count Summary (<?php echo $year; ?>)</h4>

                            <button id="toggleChartBtn" class="btn btn-outline-primary mb-3 float-end" onclick="toggleChart()">
                                Switch to Monthly Chart
                            </button>

                            <canvas id="dailyChart" style="max-height: 300px;"></canvas>
                            <canvas id="monthlyChart" style="max-height: 300px; display:none;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointments -->
            <div class="col-md-4">
                <div class="card shadow-sm p-3" style="max-height: 500px; overflow-y: auto;">
                    <h5 class="mb-3">Upcoming Appointments Today</h5>
                    <?php
                    $sqlTodayAppointments = "SELECT a.id, a.appointment_time, p.name, p.species 
                        FROM appointments a 
                        JOIN patients p ON a.patient_id = p.id 
                        WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() 
                        AND a.status = 'scheduled' AND a.appointment_time > (SELECT TIME(NOW())) 
                        ORDER BY a.appointment_time ASC";
                    $stmtToday = $conn->prepare($sqlTodayAppointments);
                    $stmtToday->bind_param("i", $doctorId);
                    $stmtToday->execute();
                    $resultToday = $stmtToday->get_result();

                    if ($resultToday->num_rows > 0) {
                        while ($appointment = $resultToday->fetch_assoc()) {
                            echo '<div class="border p-2 rounded mb-2">';
                            echo '<strong>Patient Name:</strong> ' . htmlspecialchars($appointment['name']) . '<br>';
                            echo '<strong>Species:</strong> ' . htmlspecialchars($appointment['species']) . '<br>';
                            echo '<strong>Time:</strong> ' . date('h:i A', strtotime($appointment['appointment_time'])) . '<br>';
                            echo '<div class="d-flex justify-content-end">';
                            echo '<form method="post" onsubmit="return confirm(\'Are you sure you want to cancel this appointment?\');">';
                            echo '<input type="hidden" name="cancel_appointment_id" value="' . $appointment['id'] . '">';
                            echo '<button type="submit" class="btn btn-sm btn-danger mt-2">Cancel</button>';
                            echo '</form>';
                            echo '</div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<p class="text-muted">No upcoming appointments today.</p>';
                    }

                    $stmtToday->close();
                    $conn->close();
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart Script -->
<script>
    const dayLabels = <?php echo json_encode($dayLabels); ?>;
    const dayData = <?php echo json_encode($dayValues); ?>;
    const monthLabels = <?php echo json_encode($months); ?>;
    const monthData = <?php echo json_encode($monthlyData); ?>;

    const currentMonthName = new Date().toLocaleString('default', { month: 'long' });

    const dailyChart = new Chart(document.getElementById('dailyChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: dayLabels,
            datasets: [{
                label: 'Patients (Daily)',
                data: dayData,
                backgroundColor: '#4e8cff',
                borderColor: '#2e6be0',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Day of ' + currentMonthName
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Patients'
                    },
                    ticks: { precision: 0 }
                }
            },
            plugins: { legend: { display: false } }
        }
    });

    const monthlyChart = new Chart(document.getElementById('monthlyChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'Patients (Monthly)',
                data: monthData,
                backgroundColor: '#4e8cff',
                borderColor: '#2e6be0',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            scales: {
                x: { title: { display: true, text: 'Month' } },
                y: { beginAtZero: true, title: { display: true, text: 'Records' }, ticks: { precision: 0 } }
            },
            plugins: { legend: { display: false } }
        }
    });

    function toggleChart() {
        const daily = document.getElementById('dailyChart');
        const monthly = document.getElementById('monthlyChart');
        const toggleBtn = document.getElementById('toggleChartBtn');

        if (daily.style.display === 'none') {
            daily.style.display = 'block';
            monthly.style.display = 'none';
            toggleBtn.textContent = 'Switch to Monthly Chart';
        } else {
            daily.style.display = 'none';
            monthly.style.display = 'block';
            toggleBtn.textContent = 'Switch to Daily Chart';
        }
    }
</script>

</body>
</html>
