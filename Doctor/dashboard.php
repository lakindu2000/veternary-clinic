<?php
require_once '../connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Get current user data for navbar
$user_id = $_SESSION['user_id'];
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$current_user = $user_stmt->get_result()->fetch_assoc();

// Check if user exists and is a doctor
if (!$current_user || $current_user['role'] !== 'doctor') {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

$doctorName = '';
$doctorId = 0;

// Fetch doctor data
$sql = "SELECT d.name, d.id FROM doctors d WHERE d.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $doctor = $result->fetch_assoc();
    $doctorName = $doctor['name'];
    $doctorId = $doctor['id'];
} else {
    $doctorName = $current_user['name'] ?? "Doctor";
}
$stmt->close();

// Functions to get dashboard data
function getDoctorDashboardCounts($conn, $doctorId) {
    $counts = [
        'total_patients' => 0,
        'today_appointments' => 0,
        'completed_today' => 0
    ];
    
    // Total patients treated by this doctor
    $query = "SELECT COUNT(DISTINCT patient_id) as count FROM medical_records WHERE doctor_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $doctorId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $counts['total_patients'] = $row['count'];
    }
    $stmt->close();
    
    // Today's appointments
    $currentDate = date('Y-m-d');
    $query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status = 'scheduled'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $doctorId, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $counts['today_appointments'] = $row['count'];
    }
    $stmt->close();
    
    // Completed appointments today
    $query = "SELECT COUNT(*) as count FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status = 'completed'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $doctorId, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $counts['completed_today'] = $row['count'];
    }
    $stmt->close();
    
    return $counts;
}

function getTodaysAppointments($conn, $doctorId) {
    $appointments = [];
    $currentDate = date('Y-m-d');

    $query = "SELECT a.id, a.appointment_time, a.reason, a.status, p.name as patient_name, p.species 
              FROM appointments a
              JOIN patients p ON a.patient_id = p.id
              WHERE a.doctor_id = ? AND a.appointment_date = ?
              ORDER BY a.appointment_time ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $doctorId, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
    }
    $stmt->close();
    
    return $appointments;
}

function getMonthlyPatientData($conn, $doctorId) {
    $monthlyData = array_fill(0, 12, 0);
    
    $currentYear = date('Y');
    
    $query = "SELECT MONTH(created_at) as month, COUNT(DISTINCT patient_id) as count 
              FROM medical_records 
              WHERE doctor_id = ? AND YEAR(created_at) = ?
              GROUP BY MONTH(created_at)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $doctorId, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $monthIndex = $row['month'] - 1;
            $monthlyData[$monthIndex] = $row['count'];
        }
    }
    $stmt->close();
    
    return $monthlyData;
}

// AJAX handler for real-time dashboard updates
if (isset($_GET['action']) && $_GET['action'] === 'get_dashboard_data') {
    $counts = getDoctorDashboardCounts($conn, $doctorId);
    $todaysAppointments = getTodaysAppointments($conn, $doctorId);
    
    header('Content-Type: application/json');
    echo json_encode([
        'counts' => $counts,
        'appointments' => $todaysAppointments
    ]);
    exit();
}

// Handle appointment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_appointment_id'])) {
        $cancelId = intval($_POST['cancel_appointment_id']);
        $cancelQuery = "UPDATE appointments SET status = 'cancelled' WHERE id = ? AND doctor_id = ?";
        $cancelStmt = $conn->prepare($cancelQuery);
        $cancelStmt->bind_param("ii", $cancelId, $doctorId);
        $cancelStmt->execute();
        $cancelStmt->close();
        header("Location: dashboard.php");
        exit();
    }
    
    if (isset($_POST['complete_appointment_id'])) {
        $completeId = intval($_POST['complete_appointment_id']);
        $completeQuery = "UPDATE appointments SET status = 'completed' WHERE id = ? AND doctor_id = ?";
        $completeStmt = $conn->prepare($completeQuery);
        $completeStmt->bind_param("ii", $completeId, $doctorId);
        $completeStmt->execute();
        $completeStmt->close();
        header("Location: dashboard.php");
        exit();
    }
}

// Get all data
$counts = getDoctorDashboardCounts($conn, $doctorId);
$todaysAppointments = getTodaysAppointments($conn, $doctorId);
$monthlyPatients = getMonthlyPatientData($conn, $doctorId);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Clinic - Doctor Dashboard</title>
    <style>
        :root {
            --primary-color: #4e8cff;
            --secondary-color: #3a7bd5;
            --accent-color: #ff7e5f;
            --light-bg: #f8fafc;
            --dark-text: #2d3748;
            --light-text: #f8fafc;
            --success-color: #48bb78;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
        }
        
        html, body {
            height: auto;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar {
            height: 100vh;
            width: 250px;
            background: #ffffffff;
            box-shadow: var(--card-shadow);
            position: fixed;
            padding-top: 120px;
            padding-left: 20px;
            top: 0;
        }
        
        .profile-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .sidebar .link {
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            margin-bottom: 0;
            font-weight: 600;
            color: var(--dark-text);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .sidebar .link:hover {
            background-color: rgba(78, 140, 255, 0.05);
            color: var(--primary-color);
        }
        
        .sidebar .link.active {
            background-color: rgba(78, 140, 255, 0.1);
            color: var(--primary-color);
        }
        
        .sidebar .link i {
            margin-right: 1rem;
            width: 20px;
            text-align: center;
            color: inherit;
        }
        
        .navbar {
            background: #4e8cff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 0.5rem 2rem;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            margin-left: 35px;
        }
        
        .admin-name-nav {
            font-weight: 400;
            color: white;
            font-size: 20px;
            margin-left: 15px;
        }
        
        .cards-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 2rem;
        }
        
        .card {
            border: none;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease;
            text-align: center;
            padding: 1.5rem;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .card-green {
            background: linear-gradient(135deg, #c6f7e2, #a0e9c6);
            color: white;
        }
        
        .card-yellow {
            background: linear-gradient(135deg, #fff9db, #ffe59e);
            color: #2d3748;
        }
        
        .card-red {
            background: linear-gradient(135deg, #ffd6d6, #ffb3b3);
            color: white;
        }
        
        .card-title {
            font-weight: 500;
            font-size: 18px;
            opacity: 0.9;
            margin-top: 0.2rem;
        }
        
        .card-text {
            font-size: 1.75rem;
            font-weight: 500;
            margin: 0.5rem 0;
        }
        
        .main-container {
            flex: 1;
            display: flex;
            margin-top: 5px;
        }
        
        .content-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            margin-left: 250px;
            height: calc(100vh - 70px);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .appointments-container {
            background: #ffffffff;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            height: 440px;
            display: flex;
            flex-direction: column;
        }
        
        .appointments-list {
            overflow-y: auto;
            flex-grow: 1;
            padding-right: 5px;
        }
        
        .appointment-item {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.7rem;
            background-color: rgba(78, 140, 255, 0.1);
            transition: all 0.2s ease;
            border-left: 4px solid var(--primary-color);
        }
        
        .appointment-item:hover {
            background-color: #eef2f7;
        }
        
        .appointment-item.completed {
            background-color: rgba(72, 187, 120, 0.1);
            border-left-color: var(--success-color);
        }
        
        .appointment-item.cancelled {
            background-color: rgba(255, 99, 99, 0.1);
            border-left-color: #ff6363;
        }
        
        .appointment-time {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        
        .appointment-patient {
            font-weight: 500;
            font-size: 1rem;
            color: var(--dark-text);
            margin: 0.25rem 0;
        }
        
        .appointment-reason {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .appointment-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .chart-container {
            background: #ffffffff;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--card-shadow);
            height: 280px;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .chart-wrapper {
            width: 100%;
            height: calc(100% - 40px);
            position: relative;
        }
        
        #lineChart {
            width: 100% !important;
            height: 100% !important;
        }
        
        .section-title {
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.2rem;
            color: var(--dark-text);
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.775rem;
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-danger {
            background-color: #ff6363;
            border-color: #ff6363;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-scheduled {
            background-color: rgba(78, 140, 255, 0.2);
            color: var(--primary-color);
        }
        
        .status-completed {
            background-color: rgba(72, 187, 120, 0.2);
            color: var(--success-color);
        }
        
        .status-cancelled {
            background-color: rgba(255, 99, 99, 0.2);
            color: #ff6363;
        }
        
        /* Custom scrollbar for appointments */
        .appointments-list::-webkit-scrollbar {
            width: 3px;
        }
        
        .appointments-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .appointments-list::-webkit-scrollbar-thumb {
            background: #4e8cff;
            border-radius: 10px;
        }
        
        .appointments-list::-webkit-scrollbar-thumb:hover {
            background: #3a7bd5;
        }
    </style>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="navbar">
        <div class="admin-info">
            <img src="<?= htmlspecialchars($current_user['profile_photo'] ?? '../assets/default-profile.jpg') ?>" class="profile-img">
            <span class="admin-name-nav">Dr. <?= htmlspecialchars($doctorName ?? 'Doctor') ?></span>
        </div>
    </div>
    
    <div class="main-container">
        <div class="sidebar">
            <div class="w-100 d-flex flex-column align-items-start">
                <a class="link active" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a class="link" href="patients.php"><i class="fas fa-paw"></i> Patients</a>
                <a class="link" href="add_health.php"><i class="fas fa-heartbeat"></i> Add Health Details</a>
                <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="content-wrapper">
            <div class="dashboard-grid">
                <div class="left-column">
                    <div class="cards-container">
                        <div class="card card-green">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-user-md me-2"></i>Total Patients</h5>
                                <h2 class="card-text" id="total-patients"><?php echo $counts['total_patients']; ?></h2>
                            </div>
                        </div>

                        <div class="card card-yellow">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-calendar-check me-2"></i>Today's Appointments</h5>
                                <h2 class="card-text" id="today-appointments"><?php echo $counts['today_appointments']; ?></h2>
                            </div>
                        </div>

                        <div class="card card-red">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-check-circle me-2"></i>Completed Today</h5>
                                <h2 class="card-text" id="completed-today"><?php echo $counts['completed_today']; ?></h2>
                            </div>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <h5 class="section-title">Monthly Patient Count (<?php echo date('Y'); ?>)</h5>
                        <div class="chart-wrapper">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="right-column">
                    <div class="appointments-container">
                        <h5 class="section-title">Today's Appointments</h5>
                        <p class="current-date" style="font-size: 12px; color: black;"><?php echo date('F j, Y'); ?></p>
                        <div class="appointments-list" id="appointments-list">
                            <?php if (!empty($todaysAppointments)): ?>
                                <?php foreach ($todaysAppointments as $appointment): ?>
                                    <div class="appointment-item <?php echo $appointment['status']; ?>">
                                        <div class="appointment-time">
                                            <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
                                        </div>
                                        <div class="appointment-patient">
                                            <?php echo htmlspecialchars($appointment['patient_name']); ?> 
                                            <small>(<?php echo htmlspecialchars($appointment['species']); ?>)</small>
                                        </div>
                                        <div class="appointment-reason">
                                            <?php echo htmlspecialchars($appointment['reason']); ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                            <?php if ($appointment['status'] === 'scheduled'): ?>
                                                <div class="appointment-actions">
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('Mark as completed?');">
                                                        <input type="hidden" name="complete_appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Complete
                                                        </button>
                                                    </form>
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('Cancel this appointment?');">
                                                        <input type="hidden" name="cancel_appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No appointments scheduled for today.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('lineChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Patients',
                    data: <?php echo json_encode($monthlyPatients); ?>,
                    borderColor: 'rgba(78, 140, 255, 1)',
                    backgroundColor: 'rgba(78, 140, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: 'white',
                    pointBorderColor: 'rgba(78, 140, 255, 1)',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 12
                        },
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });

        // Function to update dashboard data in real-time
        function updateDashboardData() {
            fetch('dashboard.php?action=get_dashboard_data')
                .then(response => response.json())
                .then(data => {
                    // Update counts
                    document.getElementById('total-patients').textContent = data.counts.total_patients;
                    document.getElementById('today-appointments').textContent = data.counts.today_appointments;
                    document.getElementById('completed-today').textContent = data.counts.completed_today;
                    
                    // Update today's appointments list
                    const appointmentsList = document.getElementById('appointments-list');
                    if (data.appointments.length > 0) {
                        appointmentsList.innerHTML = '';
                        data.appointments.forEach(appointment => {
                            const appointmentDiv = document.createElement('div');
                            appointmentDiv.className = `appointment-item ${appointment.status}`;
                            
                            const time = new Date(`2000-01-01 ${appointment.appointment_time}`);
                            const timeString = time.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                            
                            let actionsHtml = '';
                            if (appointment.status === 'scheduled') {
                                actionsHtml = `
                                    <div class="appointment-actions">
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Mark as completed?');">
                                            <input type="hidden" name="complete_appointment_id" value="${appointment.id}">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Cancel this appointment?');">
                                            <input type="hidden" name="cancel_appointment_id" value="${appointment.id}">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                        </form>
                                    </div>
                                `;
                            }
                            
                            appointmentDiv.innerHTML = `
                                <div class="appointment-time">${timeString}</div>
                                <div class="appointment-patient">
                                    ${appointment.patient_name} <small>(${appointment.species})</small>
                                </div>
                                <div class="appointment-reason">${appointment.reason}</div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="status-badge status-${appointment.status}">
                                        ${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}
                                    </span>
                                    ${actionsHtml}
                                </div>
                            `;
                            appointmentsList.appendChild(appointmentDiv);
                        });
                    } else {
                        appointmentsList.innerHTML = '<p class="text-muted">No appointments scheduled for today.</p>';
                    }
                })
                .catch(error => console.error('Error updating dashboard:', error));
        }

        // Listen for storage events to detect updates from other pages
        window.addEventListener('storage', function(e) {
            if (e.key === 'dashboard_update') {
                updateDashboardData();
                localStorage.removeItem('dashboard_update');
            }
        });

        // Also check on page load/focus for pending updates
        window.addEventListener('focus', function() {
            if (localStorage.getItem('dashboard_update')) {
                updateDashboardData();
                localStorage.removeItem('dashboard_update');
            }
        });

        // Check immediately on load
        if (localStorage.getItem('dashboard_update')) {
            setTimeout(() => {
                updateDashboardData();
                localStorage.removeItem('dashboard_update');
            }, 500);
        }

        // Auto-refresh every 30 seconds
        setInterval(updateDashboardData, 30000);
    </script>

</body>
</html>
