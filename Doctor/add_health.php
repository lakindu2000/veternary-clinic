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

$doctor_user_id = $_SESSION['user_id'];
$doctor_id = null;
$error = '';
$success = '';

// Get doctor info
$stmt = $conn->prepare("SELECT id, name, photo FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $doctor_user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $doctor_id = $row['id'];
    $doctorName = $row['name'];
    $photoPath = !empty($row['photo']) ? '../uploads/' . $row['photo'] : '../assets/default_doctor.png';
} else {
    $doctorName = "Doctor";
    $photoPath = '../assets/default_doctor.png';
}
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    $appointment_id = $_POST['appointment_id'];
    $patient_id = $_POST['patient_id'];
    $symptoms = $_POST['symptoms'];
    $diagnosis = $_POST['diagnosis'];
    $treatment = $_POST['treatment'];
    $medications = $_POST['medications'];
    $notes = $_POST['notes'];
    $follow_up_date = $_POST['follow_up_date'];

    $stmt = $conn->prepare("INSERT INTO medical_records 
        (appointment_id, doctor_id, patient_id, symptoms, diagnosis, treatment, medications, notes, follow_up_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiissssss", $appointment_id, $doctor_id, $patient_id, $symptoms, $diagnosis, $treatment, $medications, $notes, $follow_up_date);

    if ($stmt->execute()) {
        $update_stmt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE id = ?");
        $update_stmt->bind_param("i", $appointment_id);
        $update_stmt->execute();
        $update_stmt->close();

        echo "<script>location.href='add_health.php?success=1';</script>";
        exit();
    } else {
        $error = "Failed to add medical record.";
    }

    $stmt->close();
}

if (isset($_GET['success'])) {
    $success = "Medical record added successfully.";
}

// Fetch today's scheduled appointments
$appointments = [];
$stmt = $conn->prepare("SELECT a.id AS appointment_id, p.id AS patient_id, p.name AS patient_name, a.reason 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id 
    WHERE a.doctor_id = ? 
      AND a.status = 'scheduled' 
      AND a.appointment_date = CURDATE()
    ORDER BY a.appointment_time ASC");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Clinic - Add Health Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e8cff;
            --secondary-color: #3a7bd5;
            --light-bg: #f8fafc;
        }
        
        .sidebar {
            height: 100vh;
            width: 250px;
            background: #ffffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
            position: fixed;
            padding-top: 120px;
            padding-left: 20px;
            top: 0;
        }
        
        .sidebar .link {
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            margin-bottom: 0;
            font-weight: 600;
            color: #2d3748;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        
        .sidebar .link:hover {
            background-color: rgba(78, 140, 255, 0.05);
            color: #4e8cff;
        }
        
        .sidebar .link.active {
            background-color: rgba(78, 140, 255, 0.1);
            color: #4e8cff;
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
        
        .profile-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .admin-name-nav {
            font-weight: 400;
            color: white;
            font-size: 20px;
            margin-left: 15px;
        }
        
        .content-wrapper {
            margin-left: 250px;
            padding: 20px;
            margin-top: 70px;
        }
        
        .health-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #d1d5db;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 140, 255, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            padding: 0.6rem 1.5rem;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            font-weight: 600;
            padding: 0.6rem 1.5rem;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                padding-top: 0;
            }
            
            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-light">

<!-- Navbar -->
<div class="navbar">
    <div class="admin-info">
        <img src="<?= htmlspecialchars($current_user['profile_photo'] ?? '../assets/default-profile.jpg') ?>" class="profile-img">
        <span class="admin-name-nav">Dr. <?= htmlspecialchars($current_user['name'] ?? 'Doctor') ?></span>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="w-100 d-flex flex-column align-items-start">
        <a class="link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a class="link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
        <a class="link" href="patients.php"><i class="fas fa-paw"></i> Patients</a>
        <a class="link active" href="add_health.php"><i class="fas fa-heartbeat"></i> Add Health Details</a>
        <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="content-wrapper">
    <!-- Health Record Form -->
    <div class="card health-card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-heartbeat me-2"></i>Add Health Record</h5>
        </div>
        <div class="card-body">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="appointment_id" class="form-label">Select Appointment</label>
                            <select id="appointment_id" name="appointment_id" class="form-select" required onchange="autoFill()">
                                <option value="">-- Select Appointment --</option>
                                <?php foreach ($appointments as $appt): ?>
                                    <option value="<?php echo $appt['appointment_id']; ?>"
                                            data-patient-id="<?php echo $appt['patient_id']; ?>"
                                            data-reason="<?php echo htmlspecialchars($appt['reason']); ?>">
                                        <?php echo "Appt #" . $appt['appointment_id'] . " - " . $appt['patient_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">Patient ID</label>
                            <input type="text" id="patient_id" name="patient_id" class="form-control" readonly required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label class="form-label">Reason for Visit</label>
                            <input type="text" id="reason" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Symptoms</label>
                            <textarea name="symptoms" class="form-control" rows="3" required placeholder="Describe the observed symptoms..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Diagnosis</label>
                            <textarea name="diagnosis" class="form-control" rows="3" required placeholder="Enter diagnosis..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Treatment</label>
                            <textarea name="treatment" class="form-control" rows="3" placeholder="Describe treatment plan..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Medications</label>
                            <textarea name="medications" class="form-control" rows="3" placeholder="List prescribed medications..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Any additional notes or observations..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Follow Up Date</label>
                            <input type="date" name="follow_up_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Health Record
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function autoFill() {
    const select = document.getElementById('appointment_id');
    const selectedOption = select.options[select.selectedIndex];
    const patientId = selectedOption.getAttribute('data-patient-id');
    const reason = selectedOption.getAttribute('data-reason');
    document.getElementById('patient_id').value = patientId || '';
    document.getElementById('reason').value = reason || '';
}
</script>

</body>
</html>
