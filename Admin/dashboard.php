<?php
    require_once '../connection.php';

    // Function to get counts from database
    function getDashboardCounts($conn) {
        $counts = [
            'patients' => 0,
            'doctors' => 0,
            'appointments' => 0
        ];
        
        // Get total patients count
        $query = "SELECT COUNT(id) as count FROM patients";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $counts['patients'] = $row['count'];
        }
        
        // Get active doctors count
        $query = "SELECT COUNT(id) as count FROM doctors";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $counts['doctors'] = $row['count'];
        }
        
        // Get today's upcoming appointments count
        $today = date('Y-m-d');
        $query = "SELECT COUNT(id) as count FROM appointments WHERE appointment_date = '$today' AND status = 'scheduled'";
        $result = $conn->query($query);
        if ($result && $row = $result->fetch_assoc()) {
            $counts['appointments'] = $row['count'];
        }
        
        return $counts;
    }

    // Function to get today's appointments
    function getTodaysAppointments($conn) {
        $appointments = [];
        $today = date('Y-m-d');

        $query = "SELECT a.appointment_time, p.name, p.species, a.reason 
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                WHERE a.appointment_date = '$today' AND a.status = 'scheduled'
                ORDER BY a.appointment_time ASC";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $appointments[] = $row;
            }
        }
        
        return $appointments;
    }

    // Function to get monthly appointment data for chart
    function getMonthlyAppointmentData($conn) {
        $monthlyData = array_fill(0, 12, 0); // Initialize array for 12 months
        
        // Get current year
        $currentYear = date('Y');
        
        $query = "SELECT MONTH(appointment_date) as month, COUNT(id) as count 
                FROM appointments 
                WHERE YEAR(appointment_date) = '$currentYear'
                GROUP BY MONTH(appointment_date)";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $monthIndex = $row['month'] - 1; // Convert to 0-based index
                $monthlyData[$monthIndex] = $row['count'];
            }
        }
        
        return $monthlyData;
    }

    // Get all data
    $counts = getDashboardCounts($conn);
    $todaysAppointments = getTodaysAppointments($conn);
    $monthlyAppointments = getMonthlyAppointmentData($conn);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard - Vet Clinic</title>
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
                display: flex;
                align-items: center;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                margin-bottom: 0.7rem;
                background-color: rgba(78, 140, 255, 0.1);
                transition: all 0.2s ease;
            }
            
            .appointment-item:hover {
                background-color: #eef2f7;
            }
            
            .appointment-time {
                font-weight: 600;
                color: var(--primary-color);
                min-width: 80px;
                font-size: 0.85rem;
            }
            
            .appointment-patient {
                flex-grow: 1;
                font-weight: 500;
                font-size: 0.9rem;
                color: var(--dark-text);
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
                <img src="../assets/cat.jpg" class="profile-img">
                <span class="admin-name-nav"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></span>
            </div>
        </div>
        
        <div class="main-container">
            <div class="sidebar">
                <div class="w-100 d-flex flex-column align-items-start">
                    <a class="link active" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="link" href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
                    <a class="link" href="patients.php"><i class="fas fa-paw"></i> Patients</a>
                    <a class="link" href="billing.php"><i class="fas fa-receipt"></i> Billing</a>
                    <a class="link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a class="link" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <div class="content-wrapper">
                <div class="dashboard-grid">
                    <div class="left-column">
                        <div class="cards-container">
                            <div class="card card-green">
                                <div class="card-body">
                                    <h5 class="card-title">Total<br /> Patients</h5>
                                    <p class="card-text" id="patients-count"><?php echo $counts['patients']; ?></p>
                                </div>
                            </div>

                            <div class="card card-yellow">
                                <div class="card-body">
                                    <h5 class="card-title">Active<br /> Doctors</h5>
                                    <p class="card-text" id="doctors-count"><?php echo $counts['doctors']; ?></p>
                                </div>
                            </div>

                            <div class="card card-red">
                                <div class="card-body">
                                    <h5 class="card-title">Upcoming Appointments</h5>
                                    <p class="card-text" id="appointments-count"><?php echo $counts['appointments']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chart-container">
                            <h5 class="section-title">Patients Overview</h5>
                            <div class="chart-wrapper">
                                <canvas id="lineChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="right-column">
                        <div class="appointments-container">
                            <h5 class="section-title">Today's Appointments</h5>
                            <p class="current-date" style="font-size: 12px; color: black;"><?php echo date('F j, Y'); ?></p>
                            <div class="appointments-list">
                                <?php foreach ($todaysAppointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <span class="appointment-time"><?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?></span>
                                        <span class="appointment-patient">
                                            <?php echo htmlspecialchars($appointment['name'] . ' (' . htmlspecialchars($appointment['species']) . ') - ' . htmlspecialchars($appointment['reason'])); ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($todaysAppointments)): ?>
                                    <div class="text-center py-3">No appointments scheduled for today</div>
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
                        label: 'Appointments',
                        data: <?php echo json_encode($monthlyAppointments); ?>,
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
                                size: 14,
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
                                padding: 10
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                padding: 0.5
                            }
                        }
                    }
                }
            });
        </script>
    </body>
</html>