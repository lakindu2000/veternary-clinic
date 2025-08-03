<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

require_once '../connection.php';

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
    <title>Add Health Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .doctor-photo {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #fff;
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

<!-- Main -->
<div class="row g-0 min-vh-100">
    <!-- Sidebar -->
    <div class="col-3 bg-white shadow-sm">
        <div class="p-3">
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                    <i class="fas fa-home me-3"></i>Dashboard
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                    <i class="fas fa-user me-3"></i>Profile View
                </a>
                <a href="patients.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                    <i class="fas fa-users me-3"></i>Patients
                </a>
                <a href="add_health.php" class="list-group-item list-group-item-action active border-0 rounded mb-2">
                    <i class="fas fa-plus-circle me-3"></i>Add Health Details
                </a>
                <a href="../logout.php" class="list-group-item list-group-item-action border-0 rounded mb-2 text-danger">
                    <i class="fas fa-sign-out-alt me-3"></i>Log Out
                </a>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="col-9 p-4">
        <div class="card shadow-sm p-4">
            <h4 class="mb-4">Add Health Record</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="appointment_id" class="form-label">Select Appointment</label>
                    <select id="appointment_id" name="appointment_id" class="form-select" required onchange="autoFill()">
                        <option value="">-- Select --</option>
                        <?php foreach ($appointments as $appt): ?>
                            <option value="<?php echo $appt['appointment_id']; ?>"
                                    data-patient-id="<?php echo $appt['patient_id']; ?>"
                                    data-reason="<?php echo htmlspecialchars($appt['reason']); ?>">
                                <?php echo "Appt #" . $appt['appointment_id'] . " - " . $appt['patient_name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Patient ID</label>
                    <input type="text" id="patient_id" name="patient_id" class="form-control" readonly required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <input type="text" id="reason" class="form-control" readonly required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" rows="2" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Diagnosis</label>
                    <textarea name="diagnosis" class="form-control" rows="2" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Treatment</label>
                    <textarea name="treatment" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Medications</label>
                    <textarea name="medications" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Follow Up Date</label>
                    <input type="date" name="follow_up_date" class="form-control">
                </div>

                <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

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
