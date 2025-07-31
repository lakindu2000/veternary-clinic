<?php
include 'db_connection.php';
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="bg-light">
    <!-- Header Section -->
    <div class="row g-0" style="background-color: #4e8cff;">
        <div class="col-12 p-3">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <img src="Loosy.png" alt="Doctor Photo" class="rounded-circle border border-white border-3" style="width: 60px; height: 60px;">
                </div>
                <div class="flex-grow-1 text-center">
                    <h3 class="text-white mb-0 fw-bold">Dr. Sarah Johnson</h3>
                    <p class="text-white-50 mb-0">Cardiologist</p>
                </div>
                <div class="me-3">
                    <i class="fas fa-bell text-white fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="row g-0 min-vh-100">
        <!-- Left Sidebar -->
        <div class="col-3 bg-white shadow-sm">
            <div class="p-3">
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action active border-0 rounded mb-2">
                        <i class="fas fa-home me-3"></i>Dashboard
                    </a>
                    <a href="profile.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                        <i class="fas fa-user me-3"></i>Profile View
                    </a>

                    <a href="#" class="list-group-item list-group-item-action border-0 rounded mb-2">
                        <i class="fas fa-users me-3"></i>Patients
                    </a>
                    <a href="#" class="list-group-item list-group-item-action border-0 rounded mb-2">
                        <i class="fas fa-plus-circle me-3"></i>Add Health Details
                    </a>
                    <a href="#" class="list-group-item list-group-item-action border-0 rounded mb-2 text-danger">
                        <i class="fas fa-sign-out-alt me-3"></i>Log Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Middle Content -->
        <div class="col-6 p-4">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h2 class="fw-bold text-primary mb-0">248</h2>
                                    <p class="text-muted mb-0">Total Patients</p>
                                </div>
                                <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                    <i class="fas fa-users text-primary fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <h2 class="fw-bold text-success mb-0">12</h2>
                                    <p class="text-muted mb-0">Upcoming Appointments</p>
                                </div>
                                <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                    <i class="fas fa-calendar-check text-success fs-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patients Overview Chart -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">Patients Overview</h5>
                    <small class="text-muted">Monthly patient visits</small>
                </div>
                <div class="card-body">
                    <canvas id="patientsChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Right Sidebar - Today's Appointments -->
        <div class="col-3 p-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <h5 class="card-title mb-0">Today's Appointments</h5>
                    <small class="text-muted">July 30, 2025</small>
                </div>
                <div class="card-body p-0">
                    <div class="overflow-auto" style="max-height: 600px;">
                        <!-- Appointment Item 1 -->
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center mb-2">
                                <img src="https://via.placeholder.com/40x40?text=JD" alt="Patient" class="rounded-circle me-3" style="width: 40px; height: 40px;">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">John Doe</h6>
                                    <small class="text-muted">Routine Checkup</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary">9:00 AM</span>
                                <small class="text-success"><i class="fas fa-check-circle"></i> Confirmed</small>
                            </div>
                            
                        </div>

                        <!-- Appointment Item 2 -->
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center mb-2">
                                <img src="https://via.placeholder.com/40x40?text=MS" alt="Patient" class="rounded-circle me-3" style="width: 40px; height: 40px;">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Mary Smith</h6>
                                    <small class="text-muted">Follow-up</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary">10:30 AM</span>
                                <small class="text-success"><i class="fas fa-check-circle"></i> Confirmed</small>
                            </div>
                        </div>

                        <!-- Appointment Item 3 -->
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center mb-2">
                                <img src="https://via.placeholder.com/40x40?text=RJ" alt="Patient" class="rounded-circle me-3" style="width: 40px; height: 40px;">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">Robert Johnson</h6>
                                    <small class="text-muted">Consultation</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary">11:45 AM</span>
                                <small class="text-warning"><i class="fas fa-clock"></i> Pending</small>
                            </div>
                        </div>


                     
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Patients Overview Chart
        const ctx = document.getElementById('patientsChart').getContext('2d');
        const patientsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Monthly Patients',
                    data: [65, 78, 90, 81, 95, 88, 102, 87, 94, 89, 76, 85],
                    backgroundColor: '#4e8cff',
                    borderColor: '#4e8cff',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 30
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        },
                        ticks: {
                            color: '#666'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#666'
                        }
                    }
                }
            }
        });

        // Interactive sidebar navigation
        document.querySelectorAll('.list-group-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.list-group-item').forEach(link => {
                    link.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>