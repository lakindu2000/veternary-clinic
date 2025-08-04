<?php
    session_start();
    require_once '../connection.php';

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

    // Check if user exists
    if (!$current_user) {
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    // Initialize variables
    $search_owner_id = $_GET['owner_id_num'] ?? '';
    $search_date = $_GET['appointment_date'] ?? date('Y-m-d');
    $patient = null;
    $appointment = null;
    $doctor = null;
    $msg = '';
    $fieldsShouldPopulate = false;
    $search_performed = false;
    $show_receipt = false;
    $receipt_data = [];

    // Get charges from database (set in settings page)
    $charges = [
        'report' => 0.00,
        'medication' => 0.00
    ];

    // Fetch charges from database
    $charges_result = $conn->query("SELECT charge_type, amount FROM charges");
    if ($charges_result && $charges_result->num_rows > 0) {
        while ($row = $charges_result->fetch_assoc()) {
            if ($row['charge_type'] == 'appointment') {
                $charges['appointment'] = $row['amount'];
            } elseif ($row['charge_type'] == 'doctor_consultation') {
                $charges['doctor'] = $row['amount'];
            }
        }
    }

    // Check for success flag in URL and show receipt if present
    if (isset($_GET['payment_success']) && isset($_SESSION['receipt_data'])) {
        $show_receipt = true;
        $receipt_data = $_SESSION['receipt_data'];
        $msg = '<div class="alert alert-success">Payment processed successfully! <a href="#" onclick="printReceipt()">Print Receipt</a></div>';
        unset($_SESSION['receipt_data']);
    }

    // Search for patient by owner ID and date
    if ($search_owner_id && $search_date) {
        $search_performed = true;
        
        // 1. Check if the owner_id_num exists
        $stmt = $conn->prepare("SELECT * FROM patients WHERE owner_id_num = ?");
        $stmt->bind_param('i', $search_owner_id);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();

        if ($patient) {
            // 2. Check for appointment on given date
            $stmt = $conn->prepare("SELECT a.*, d.name AS doctor_name, d.specialization 
                                FROM appointments a
                                JOIN doctors d ON a.doctor_id = d.id
                                WHERE a.patient_id = ? 
                                AND DATE(a.appointment_date) = ?
                                LIMIT 1");
            $stmt->bind_param('is', $patient['id'], $search_date);
            $stmt->execute();
            $appointment = $stmt->get_result()->fetch_assoc();

            if ($appointment) {
                $fieldsShouldPopulate = true;
            } else {
                $msg = '<div class="alert alert-warning">Patient found, but no appointment on selected date</div>';
            }
        } else {
            $msg = '<div class="alert alert-warning">No patient found with that ID number</div>';
        }
    } elseif ($search_performed) {
        $msg = '<div class="alert alert-warning">Please provide both owner ID and appointment date</div>';
    }

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['process_payment'])) {
            $appointment_id = $_POST['appointment_id'];
            $patient_id = $_POST['patient_id'];
            $amount = $_POST['amount'];
            $services = "Appointment Fee: ".$_POST['appointment_fee']."\n";
            $services .= "Doctor Fee: ".$_POST['doctor_fee']."\n";
            $services .= "Reports: ".$_POST['report_fee']."\n";
            $services .= "Medications: ".$_POST['injection_fee'];
            
            // Prepare receipt data before processing payment
            $_SESSION['receipt_data'] = [
                'patient' => [
                    'name' => $patient['name'] ?? 'Not Available',
                    'species' => $patient['species'] ?? 'Not Available',
                    'breed' => $patient['breed'] ?? 'Not Available',
                    'age' => $patient['age'] ?? 'Not Available',
                    'owner_name' => $patient['owner_name'] ?? 'Not Available',
                    'owner_id_num' => $patient['owner_id_num'] ?? 'Not Available',
                    'owner_phone' => $patient['owner_phone'] ?? 'Not Available'
                ],
                'appointment' => [
                    'id' => $appointment['id'] ?? 'N/A',
                    'appointment_number' => $appointment['appointment_number'] ?? 'N/A',
                    'appointment_date' => $appointment['appointment_date'] ?? 'Not Available',
                    'appointment_time' => $appointment['appointment_time'] ?? 'Not Available',
                    'doctor_name' => $appointment['doctor_name'] ?? 'Not Available',
                    'specialization' => $appointment['specialization'] ?? 'Not Available'
                ],
                'charges' => [
                    'appointment_fee' => $_POST['appointment_fee'] ?? 0,
                    'doctor_fee' => $_POST['doctor_fee'] ?? 0,
                    'report_fee' => $_POST['report_fee'] ?? 0,
                    'injection_fee' => $_POST['injection_fee'] ?? 0,
                    'total_amount' => $_POST['amount'] ?? 0
                ]
            ];

            $stmt = $conn->prepare("INSERT INTO billing (appointment_id, patient_id, amount, services, payment_status, payment_date) 
                                VALUES (?, ?, ?, ?, 'paid', CURDATE())");
            $stmt->bind_param('iids', $appointment_id, $patient_id, $amount, $services);
            
            if ($stmt->execute()) {
                // Update appointment status to 'completed' after successful payment
                $update_stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
                $update_stmt->bind_param('i', $appointment_id);
                $update_stmt->execute();
                
                header("Location: billing.php?payment_success=1");
                exit();
            } else {
                $msg = '<div class="alert alert-danger">Failed to process payment: ' . $conn->error . '</div>';
            }
        }
    }
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

            .form-section {
                background: white;
                border-radius: 8px;
                box-shadow: var(--card-shadow);
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }

            .form-section h4 {
                color: var(--primary-color);
                margin-bottom: 1.5rem;
                font-size: 20px;
            }

            .form-label{
                font-weight: 400;
                color: var(--dark-text);
                margin-bottom: 0.5rem;
                font-size: 15px;
            }

            .form-control:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 0.2rem rgba(78, 140, 255, 0.25);
            }

            .form-control[readonly] {
                background-color: #f8f9fa;
                cursor: not-allowed;
            }

            .form-control.empty-field {
                font-style: italic;
                color: #3a7bd5;
            }

            .input-group-text {
                background-color: #f8f9fa;
                color: #495057;
                border: 1px solid #e2e8f0;
            }

            textarea.form-control {
                min-height: 100px;
            }

            .charge-input {
                background-color: #f8fafc;
                font-weight: 500;
            }

            .charge-input:focus {
                background-color: white;
            }

            .receipt-container {
                display: none;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background: white;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            
            .receipt-header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #4e8cff;
                padding-bottom: 20px;
            }
            
            .receipt-header h1 {
                color: #4e8cff;
                margin-bottom: 5px;
            }
            
            .receipt-header p {
                margin: 5px 0;
                color: #555;
            }
            
            .receipt-section {
                margin-bottom: 20px;
            }
            
            .receipt-section h2 {
                color: #4e8cff;
                font-size: 18px;
                border-bottom: 1px solid #eee;
                padding-bottom: 5px;
                margin-bottom: 10px;
            }
            
            .receipt-row {
                display: flex;
                margin-bottom: 8px;
            }
            
            .receipt-label {
                font-weight: bold;
                width: 200px;
            }
            
            .receipt-value {
                flex: 1;
            }
            
            .receipt-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            
            .receipt-table th {
                background: #f5f5f5;
                text-align: left;
                padding: 8px;
            }
            
            .receipt-table td {
                padding: 8px;
            }
            
            .receipt-total {
                font-weight: bold;
                font-size: 18px;
                margin-top: 20px;
                text-align: right;
                border-top: 2px solid #4e8cff;
                padding-top: 10px;
            }
            
            .receipt-footer {
                text-align: center;
                margin-top: 30px;
                font-style: italic;
                color: #777;
            }
            
            @media print {
                body * {
                    visibility: hidden;
                    margin: 0;
                    padding: 0;
                }

                .receipt-container, .receipt-container * {
                    visibility: visible;
                }

                .receipt-container {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                    margin: 0;
                    padding: 20px;
                    box-shadow: none;
                    background: white;
                }

                .no-print, .msg-alert, .navbar, .sidebar {
                    display: none !important;
                }

                
                .receipt-header h1 {
                    font-size: 16pt;
                }
                .receipt-section h2 {
                    font-size: 12pt;
                }
                .receipt-row, .receipt-table {
                    font-size: 10pt;
                }
                .receipt-total {
                    font-size: 11pt;
                }

                .content-wrapper {
                    margin-left: 0 !important;
                    padding: 0 !important;
                }
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
                <span class="admin-name-nav"><?= htmlspecialchars($current_user['name'] ?? 'Admin') ?></span>
            </div>
        </div>
        
        <div class="main-container">
            <div class="sidebar">
                <div class="w-100 d-flex flex-column align-items-start">
                    <a class="link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="link" href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
                    <a class="link" href="patients.php"><i class="fas fa-paw"></i> Patients</a>
                    <a class="link active" href="billing.php"><i class="fas fa-receipt"></i> Billing</a>
                    <a class="link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a class="link" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <div class="content-wrapper">
                <?= $msg ?>
                
                <!-- Search Form -->
                <div class="form-section no-print">
                    <h4>Patient Search</h4>
                    <form method="get" action="billing.php" class="row g-3">
                        <div class="col-md-4">
                            <input type="text"
                                class="form-control form-control-md fs-6" 
                                name="owner_id_num"
                                placeholder="Owner Identity Card No" 
                                value="<?= htmlspecialchars($search_owner_id) ?>"
                                autofocus>
                        </div>
                        <div class="col-md-3">
                            <input type="date"
                                class="form-control form-control-md fs-6" 
                                name="appointment_date"
                                value="<?= htmlspecialchars($search_date) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                        <div class="col-md-2">
                            <a href="billing.php" class="btn btn-secondary w-100">Clear</a>
                        </div>
                    </form>
                </div>

                <form method="post">
                    <input type="hidden" name="appointment_id" value="<?= $appointment ? $appointment['id'] : '' ?>">
                    <input type="hidden" name="patient_id" value="<?= $patient ? $patient['id'] : '' ?>">

                    <div class="form-section no-print">
                        <!-- Patient Information Section -->
                        <h4>Patient Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Patient Name</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['name']) : 'No patient data' ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Species/Breed</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['species']).'/'.htmlspecialchars($patient['breed']) : '--' ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Age</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['age']) : '--' ?>" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Owner Name</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['owner_name']) : '--' ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Owner Identity Card No</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['owner_id_num']) : '--' ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Owner Phone</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['owner_phone']) : '--' ?>" readonly>
                            </div>
                        </div>

                        <!-- Appointment Information Section -->
                        <h4>Appointment Details</h4>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Appointment Number</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? htmlspecialchars($appointment['appointment_number']) : 'No appointment' ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date & Time</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? date('M j, Y', strtotime($appointment['appointment_date'])).' at '.date('g:i A', strtotime($appointment['appointment_time'])) : '--' ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Doctor</label>
                                <input type="text" class="form-control <?= !$fieldsShouldPopulate ? 'empty-field' : '' ?>" 
                                    value="<?= $fieldsShouldPopulate ? 'Dr. '.htmlspecialchars($appointment['doctor_name']).' ('.htmlspecialchars($appointment['specialization']).')' : '--' ?>" readonly>
                            </div>
                        </div>

                        <!-- Billing Information Section -->
                        <h4>Billing Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Appointment Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" class="form-control charge-input" name="appointment_fee"
                                        value="<?= $charges['appointment'] ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Doctor Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" class="form-control charge-input" name="doctor_fee"
                                        value="<?= $charges['doctor'] ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Reports</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" id="reports" class="form-control charge-input" name="report_fee" 
                                        value="<?= $charges['report'] ?>" oninput="calculateTotal()">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Medications</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" id="medications" class="form-control charge-input" name="injection_fee"
                                        value="<?= $charges['medication'] ?>" oninput="calculateTotal()">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label mb-2">Total Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" step="0.01" class="form-control" name="amount" id="total_amount" readonly>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Button -->
                        <div class="row">
                            <div class="col-md-4">
                                <button type="submit" name="process_payment" class="btn btn-success w-100" <?= !$fieldsShouldPopulate ? 'disabled' : '' ?>>
                                    <i class="fas fa-credit-card me-2"></i> Process Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Receipt Template (hidden until payment is processed) -->
                <?php if ($show_receipt && isset($receipt_data)): ?>
                    <div class="receipt-container" id="receipt" style="display: block;">
                        <div class="receipt-header">
                            <h1>VET CARE ANIMAL HOSPITAL</h1>
                            <p>123 Main Street, Colombo, Sri Lanka</p>
                            <p>Phone: +94 11 2345678 | Email: info@vetcare.lk</p>
                            <h3>PAYMENT RECEIPT</h3>
                            <p>Receipt #: <?= 'RC'.date('Ymd').$receipt_data['appointment']['id'] ?></p>
                        </div>

                        <div class="receipt-section">
                            <h2>Patient Information</h2>
                            <div class="receipt-row">
                                <div class="receipt-label">Patient Name:</div>
                                <div class="receipt-value"><?= isset($receipt_data['patient']['name']) ? htmlspecialchars($receipt_data['patient']['name']) : 'N/A' ?></div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Species/Breed:</div>
                                <div class="receipt-value">
                                    <?= isset($receipt_data['patient']['species']) ? htmlspecialchars($receipt_data['patient']['species']) : 'N/A' ?>
                                    /
                                    <?= isset($receipt_data['patient']['breed']) ? htmlspecialchars($receipt_data['patient']['breed']) : 'N/A' ?>
                                </div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Age:</div>
                                <div class="receipt-value"><?= isset($receipt_data['patient']['age']) ? htmlspecialchars($receipt_data['patient']['age']) : 'N/A' ?></div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Owner Name:</div>
                                <div class="receipt-value"><?= isset($receipt_data['patient']['owner_name']) ? htmlspecialchars($receipt_data['patient']['owner_name']) : 'N/A' ?></div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Owner ID:</div>
                                <div class="receipt-value"><?= isset($receipt_data['patient']['owner_id_num']) ? htmlspecialchars($receipt_data['patient']['owner_id_num']) : 'N/A' ?></div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Owner Phone:</div>
                                <div class="receipt-value"><?= isset($receipt_data['patient']['owner_phone']) ? htmlspecialchars($receipt_data['patient']['owner_phone']) : 'N/A' ?></div>
                            </div>
                        </div>

                        <div class="receipt-section">
                            <h2>Appointment Details</h2>
                            <div class="receipt-row">
                                <div class="receipt-label">Appointment Number:</div>
                                <div class="receipt-value"><?= htmlspecialchars($receipt_data['appointment']['appointment_number']) ?></div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Date & Time:</div>
                                <div class="receipt-value"><?= date('M j, Y', strtotime($receipt_data['appointment']['appointment_date'])) ?> at <?= date('g:i A', strtotime($receipt_data['appointment']['appointment_time'])) ?></div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Doctor:</div>
                                <div class="receipt-value"><?= htmlspecialchars($receipt_data['appointment']['doctor_name']) ?> (<?= htmlspecialchars($receipt_data['appointment']['specialization']) ?>)</div>
                            </div>
                        </div>

                        <div class="receipt-section">
                            <h2>Billing Details</h2>
                            <table class="receipt-table">
                                <tr>
                                    <th>Description</th>
                                    <th>Amount (Rs.)</th>
                                </tr>
                                <tr>
                                    <td>Appointment Fee</td>
                                    <td><?= number_format($receipt_data['charges']['appointment_fee'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Doctor Fee</td>
                                    <td><?= number_format($receipt_data['charges']['doctor_fee'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Reports</td>
                                    <td><?= number_format($receipt_data['charges']['report_fee'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Medications</td>
                                    <td><?= number_format($receipt_data['charges']['injection_fee'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Amount</strong></td>
                                    <td style="color: #4e8cff;"><strong><?= number_format($receipt_data['charges']['total_amount'], 2) ?></strong></td>
                                </tr>
                            </table>
                        </div>

                        <div class="receipt-section">
                            <h2>Payment Information</h2>
                            <div class="receipt-row">
                                <div class="receipt-label">Payment Date:</div>
                                <div class="receipt-value"><?= date('M j, Y') ?></div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Payment Status:</div>
                                <div class="receipt-value">Paid</div>
                            </div>
                            <div class="receipt-row">
                                <div class="receipt-label">Payment Method:</div>
                                <div class="receipt-value">Cash</div>
                            </div>
                        </div>

                        <div class="receipt-footer">
                            <p>Thank you for choosing Vet Care Animal Hospital!</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            function calculateTotal() {
                const appointment = parseFloat(document.querySelector('[name="appointment_fee"]').value) || 0;
                const doctor = parseFloat(document.querySelector('[name="doctor_fee"]').value) || 0;
                const report = parseFloat(document.getElementById('reports').value) || 0;
                const medications = parseFloat(document.getElementById('medications').value) || 0;

                const total = appointment + doctor + report + medications;
                document.getElementById('total_amount').value = total.toFixed(2);
            }
            
            window.onload = function() {
                calculateTotal();
                
                <?php if ($show_receipt): ?>
                // Clear form fields
                document.getElementById('reports').value = '0.00';
                document.getElementById('medications').value = '0.00';
                document.getElementById('total_amount').value = '0.00';
                
                // Focus on search field
                document.querySelector('[name="owner_id_num"]').focus();
                
                // Trigger dashboard update after successful payment
                localStorage.setItem('dashboard_update', 'true');
                
                // Also trigger update for other tabs/windows
                window.dispatchEvent(new StorageEvent('storage', {
                    key: 'dashboard_update',
                    newValue: 'true'
                }));
                <?php endif; ?>
            };

            function printReceipt() {
                const receipt = document.getElementById('receipt');
                if (receipt) {
                    const originalDisplay = receipt.style.display;
                    receipt.style.display = 'block';
                    
                    window.print();
                    
                    setTimeout(() => {
                        receipt.style.display = originalDisplay;
                    }, 500);
                }
            }
        </script>
    </body>
</html>