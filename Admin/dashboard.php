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
                <span class="admin-name-nav">Timasha Wanninayaka</span>
            </div>
        </div>
        
        <div class="main-container">
            <div class="sidebar">
                <div class="w-100 d-flex flex-column align-items-start">
                    <a class="link active" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="link" href="appointment.php"><i class="fas fa-calendar-check"></i> Appointments</a>
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
                                    <p class="card-text" id="patients-count">0</p>
                                </div>
                            </div>

                            <div class="card card-yellow">
                                <div class="card-body">
                                    <h5 class="card-title">Active<br /> Doctors</h5>
                                    <p class="card-text" id="doctors-count">0</p>
                                </div>
                            </div>

                            <div class="card card-red">
                                <div class="card-body">
                                    <h5 class="card-title">Upcoming Appointments</h5>
                                    <p class="card-text" id="appointments-count">0</p>
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
                            <div class="appointments-list">
                                <div class="appointment-item">
                                    <span class="appointment-time">09:00 AM</span>
                                    <span class="appointment-patient">Max (Golden Retriever) - Annual Checkup</span>
                                </div>
                                <div class="appointment-item">
                                    <span class="appointment-time">11:30 AM</span>
                                    <span class="appointment-patient">Bella (Persian Cat) - Vaccination</span>
                                </div>
                                <div class="appointment-item">
                                    <span class="appointment-time">02:15 PM</span>
                                    <span class="appointment-patient">Rocky (German Shepherd) - Injury Follow-up</span>
                                </div>
                                <div class="appointment-item">
                                    <span class="appointment-time">04:45 PM</span>
                                    <span class="appointment-patient">Luna (Siamese Cat) - Dental Cleaning</span>
                                </div>
                                <div class="appointment-item">
                                    <span class="appointment-time">05:15 PM</span>
                                    <span class="appointment-patient">Sina (German Shepherd) - Dental Cleaning</span>
                                </div>
                                <div class="appointment-item">
                                    <span class="appointment-time">11:30 AM</span>
                                    <span class="appointment-patient">Bella (Persian Cat) - Vaccination</span>
                                </div>
                                <div class="appointment-item">
                                    <span class="appointment-time">03:00 PM</span>
                                    <span class="appointment-patient">Charlie (Bulldog) - Skin Allergy</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        

        <script>
            const patientsCount = 142;
            const doctorsCount = 8;
            const appointmentsCount = 17;
            
            document.getElementById('patients-count').textContent = patientsCount;
            document.getElementById('doctors-count').textContent = doctorsCount;
            document.getElementById('appointments-count').textContent = appointmentsCount;
            
            const ctx = document.getElementById('lineChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Appointments',
                        data: [120, 135, 150, 140, 175, 180, 150, 200, 170, 220, 230, 240],
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