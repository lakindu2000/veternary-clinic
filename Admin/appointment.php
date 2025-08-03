<?php
    require_once '../connection.php';
    require_once '../Lib/phpqrcode/qrlib.php'; 
    session_start();

    // Initialize variables
    $msg = '';
    $search_owner_id = '';
    $fieldsShouldPopulate = false;
    $show_receipt = false;
    $patient = [];
    $appointment = [];
    $receipt_data = [];

    // Check if form is submitted for search
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['owner_id_num'])) {
        $search_owner_id = trim($_GET['owner_id_num']);
        
        // Search for patient by owner ID
        $stmt = $conn->prepare("SELECT * FROM patients WHERE owner_id_num = ?");
        $stmt->bind_param("s", $search_owner_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
            $fieldsShouldPopulate = true;
        }
    }

    // Process appointment form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
        $patient_id = $_POST['patient_id'] ?? null;
        $appointment_number = $_POST['appointment_number'];
        $appointment_date = $_POST['appointment_date'];
        $appointment_time = $_POST['appointment_time'];
        $doctor_id = $_POST['doctor_id'];
        $reason = $_POST['reason'];
        
        // Check if this is a new patient (fields not populated)
        if (empty($patient_id)) {
            // Get new patient details from form
            $patient_name = $_POST['patient_name'];
            $species = $_POST['species'];
            $breed = $_POST['breed'];
            $age = $_POST['age'];
            $owner_name = $_POST['owner_name'];
            $owner_id_num = $_POST['owner_id_num'];
            $owner_phone = $_POST['owner_phone'];
            $email = $_POST['email'];
            
            // Insert new patient
            $stmt = $conn->prepare("INSERT INTO patients (name, species, breed, age, owner_name, owner_id_num, owner_phone, email) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $patient_name, $species, $breed, $age, $owner_name, $owner_id_num, $owner_phone, $email);
            $stmt->execute();
            $patient_id = $conn->insert_id;

            //Prepare data for QR code
            $qr_data = "Patient ID: $patient_id\nName: $patient_name\nSpecies: $species\nBreed: $breed\nAge: $age\nOwner: $owner_name\nPhone: $owner_phone";

             //Generate QR code image
            $qr_dir = "../qrcodes/";
            if (!file_exists($qr_dir)) {
                mkdir($qr_dir, 0777, true);
            }
            $qr_filename = $qr_dir . "patient_" . $patient_id . ".png";
            QRcode::png($qr_data, $qr_filename, QR_ECLEVEL_L, 5);

            $patient['qr_code'] = "qrcodes/patient_" . $patient_id . ".png";



            $patient = [
                'id' => $patient_id,
                'name' => $patient_name,
                'species' => $species,
                'breed' => $breed,
                'age' => $age,
                'owner_name' => $owner_name,
                'owner_id_num' => $owner_id_num,
                'owner_phone' => $owner_phone,
                'email' => $email
            ];
        } else {
            // Get existing patient details
            $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $patient = $result->fetch_assoc();
        }
        
        // Create new appointment
        $stmt = $conn->prepare("INSERT INTO appointments (patient_id, appointment_number, appointment_date, appointment_time, doctor_id, reason, status) 
                            VALUES (?, ?, ?, ?, ?, ?, 'Scheduled')");
        $stmt->bind_param("isssis", $patient_id, $appointment_number, $appointment_date, $appointment_time, $doctor_id, $reason);
        $stmt->execute();
        $appointment_id = $conn->insert_id;
        
        // Get doctor details for receipt
        $stmt = $conn->prepare("SELECT name, specialization FROM doctors WHERE id = ?");
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $doctor_result = $stmt->get_result();
        $doctor = $doctor_result->fetch_assoc();
        
        // Prepare receipt data
        $receipt_data = [
            'patient' => $patient,
            'appointment' => [
                'id' => $appointment_id,
                'appointment_number' => $appointment_number,
                'appointment_date' => $appointment_date,
                'appointment_time' => $appointment_time,
                'doctor_name' => $doctor['name'],
                'specialization' => $doctor['specialization'],
                'reason' => $reason
            ]
        ];
        
        $show_receipt = true;
        $fieldsShouldPopulate = true;
        
        // Set success message
         $msg = '<div class="alert alert-success">Appointment scheduled successfully! 
            <a href="javascript:void(0);" onclick="printReceipt()" class="alert-link">Print Receipt</a></div>';
    }

    // Get doctors for dropdown
    $doctors = [];
    $result = $conn->query("SELECT id, name, specialization FROM doctors");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $doctors[] = $row;
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
                font-size: 20px;
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
                font-size: 20px;
            }
            
            .receipt-value {
                flex: 1;
                font-size: 20px;
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
                <img src="../assets/cat.jpg" class="profile-img">
                <span class="admin-name-nav">Timasha Wanninayaka</span>
            </div>
        </div>
        
        <div class="main-container">
            <div class="sidebar">
                <div class="w-100 d-flex flex-column align-items-start">
                    <a class="link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a class="link active" href="appointment.php"><i class="fas fa-calendar-check"></i> Appointments</a>
                    <a class="link" href="patients.php"><i class="fas fa-paw"></i> Patients</a>
                    <a class="link" href="billing.php"><i class="fas fa-receipt"></i> Billing</a>
                    <a class="link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a class="link" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <div class="content-wrapper">
            <?= $msg ?>

            <?php if (!empty($patient['qr_code'])): ?>
                <div style="margin-top: 20px;">
                    <h3>Patient QR Code</h3>
                    <img src="<?= $patient['qr_code'] ?>" alt="QR Code" style="width: 200px; height: 200px;">
                    <br><br>
                    <a href="<?= $patient['qr_code'] ?>" download class="btn btn-primary">Download QR Code</a>
                </div>
            <?php endif; ?>
            

            <!-- Search Form -->
            <div class="form-section no-print">
                <h4>Patient Search</h4>
                <form method="get" action="" class="row g-3">
                    <div class="col-md-4">
                        <input type="text"
                            class="form-control form-control-md fs-6" 
                            name="owner_id_num"
                            placeholder="Owner Identity Card No" 
                            value="<?= htmlspecialchars($search_owner_id) ?>"
                            autofocus>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Search</button>
                    </div>
                    <div class="col-md-2">
                        <a href="appointment.php" class="btn btn-secondary w-100">Clear</a>
                    </div>
                </form>
            </div>

            <form method="post">
                <input type="hidden" name="patient_id" value="<?= $patient ? $patient['id'] : '' ?>">

                <div class="form-section no-print">
                    <!-- Patient Information Section -->
                    <h4>Patient Information</h4>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Patient Name</label>
                            <input type="text" class="form-control" name="patient_name" 
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['name']) : '' ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Species</label>
                            <input type="text" class="form-control" name="species"
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['species']) : '' ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Breed</label>
                            <input type="text" class="form-control" name="breed"
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['breed']) : '' ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Age</label>
                            <input type="text" class="form-control" name="age"
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['age']) : '' ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Owner Name</label>
                            <input type="text" class="form-control" name="owner_name"
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['owner_name']) : '' ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner Identity Card No</label>
                            <input type="text" class="form-control" name="owner_id_num"
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['owner_id_num']) : '' ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner Phone</label>
                            <input type="text" class="form-control" name="owner_phone"
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['owner_phone']) : '' ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Owner Email</label>
                            <input type="text" class="form-control" name="email"
                                value="<?= $fieldsShouldPopulate ? htmlspecialchars($patient['email']) : '' ?>" required>
                        </div>
                    </div>

                    <!-- Appointment Information Section -->
                    <h4>Appointment Details</h4>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Appointment Number</label>
                            <input type="text" class="form-control" name="appointment_number" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Appointment Date</label>
                            <input type="date" class="form-control" name="appointment_date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Appointment Time</label>
                            <input type="time" class="form-control" name="appointment_time" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Doctor</label>
                            <select class="form-select" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?= $doctor['id'] ?>"><?= htmlspecialchars($doctor['name']) ?> (<?= htmlspecialchars($doctor['specialization']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Reason for Appointment</label>
                            <textarea class="form-control" name="reason" required></textarea>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <button type="submit" name="process_payment" class="btn btn-primary w-100">Set Appointment</button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Receipt Template -->
            <?php if ($show_receipt && isset($receipt_data)): ?>
                <div class="receipt-container" id="receipt" style="display: none;">
                    <div class="receipt-header">
                        <h1>VET CARE ANIMAL HOSPITAL</h1>
                        <p>123 Main Street, Colombo, Sri Lanka</p>
                        <p>Phone: +94 11 2345678 | Email: info@vetcare.lk</p>
                        <h3>APPOINTMENT RECEIPT</h3>
                        <p>Receipt #: <?= htmlspecialchars($receipt_data['appointment']['appointment_number']) ?></p>
                    </div>

                    <div class="receipt-section">
                        <h2>Patient Information</h2>
                        <div class="receipt-row">
                            <div class="receipt-label">Patient Name:</div>
                            <div class="receipt-value"><?= htmlspecialchars($receipt_data['patient']['name']) ?></div>
                        </div>
                        <div class="receipt-row">
                            <div class="receipt-label">Species/Breed:</div>
                            <div class="receipt-value">
                                <?= htmlspecialchars($receipt_data['patient']['species']) ?>
                                /
                                <?= htmlspecialchars($receipt_data['patient']['breed']) ?>
                            </div>
                        </div>
                        <div class="receipt-row">
                            <div class="receipt-label">Age:</div>
                            <div class="receipt-value"><?= htmlspecialchars($receipt_data['patient']['age']) ?></div>
                        </div>
                        <div class="receipt-row">
                            <div class="receipt-label">Owner Name:</div>
                            <div class="receipt-value"><?= htmlspecialchars($receipt_data['patient']['owner_name']) ?></div>
                        </div>
                        <div class="receipt-row">
                            <div class="receipt-label">Owner ID:</div>
                            <div class="receipt-value"><?= htmlspecialchars($receipt_data['patient']['owner_id_num']) ?></div>
                        </div>
                        <div class="receipt-row">
                            <div class="receipt-label">Owner Phone:</div>
                            <div class="receipt-value"><?= htmlspecialchars($receipt_data['patient']['owner_phone']) ?></div>
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
                            <div class="receipt-value">
                                <?= date('M j, Y', strtotime($receipt_data['appointment']['appointment_date'])) ?>
                                at <?= date('g:i A', strtotime($receipt_data['appointment']['appointment_time'])) ?>
                            </div>
                        </div>
                        <div class="receipt-row">
                            <div class="receipt-label">Doctor:</div>
                            <div class="receipt-value">
                                <?= htmlspecialchars($receipt_data['appointment']['doctor_name']) ?>
                                (<?= htmlspecialchars($receipt_data['appointment']['specialization']) ?>)
                            </div>
                        </div>
                        <div class="receipt-row">
                            <div class="receipt-label">Reason:</div>
                            <div class="receipt-value"><?= htmlspecialchars($receipt_data['appointment']['reason']) ?></div>
                        </div>
                    </div>

                    <div class="receipt-footer">
                        <p>Thank you for choosing Vet Care Animal Hospital!</p>
                    </div>

                </div>

            <?php endif; ?>
        </div>

        <script>
            function printReceipt() {
                const receipt = document.getElementById('receipt');
                if (receipt) {
                    receipt.style.display = 'block';
                    window.print();
                    receipt.style.display = 'none';
                }
            }
        </script>
    </body>
</html>