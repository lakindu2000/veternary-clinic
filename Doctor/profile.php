<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    header("Location: ../login.php");
    exit();
}

require_once '../connection.php';

$user_id = $_SESSION['user_id'];
$message = "";

// Handle form submission for updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    $name = $_POST['name'];
    $specialization = $_POST['specialization'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $specification = $_POST['specification'];

    $photoPath = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "../uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = basename($_FILES["photo"]["name"]);
        $targetFile = $targetDir . uniqid() . "_" . $fileName;

        if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFile)) {
            $photoPath = $targetFile;
        }
    }

    $query = "UPDATE doctors SET name=?, specialization=?, phone=?, address=?, specification=?";
    if ($photoPath !== '') {
        $query .= ", photo=?";
    }
    $query .= " WHERE user_id=?";

    $stmt = $conn->prepare($query);
    if ($photoPath !== '') {
        $stmt->bind_param("ssssssi", $name, $specialization, $phone, $address, $specification, $photoPath, $user_id);
    } else {
        $stmt->bind_param("sssssi", $name, $specialization, $phone, $address, $specification, $user_id);
    }

    if ($stmt->execute()) {
        $message = "Profile updated successfully.";
    } else {
        $message = "Failed to update profile.";
    }
    $stmt->close();
}

// Fetch doctor data
$stmt = $conn->prepare("SELECT * FROM doctors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-pic {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #4e8cff;
        }
        .upload-label {
            font-size: 0.9rem;
            color: #555;
        }
        input[readonly], textarea[readonly] {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">

<!-- Header -->
<div class="row g-0" style="background-color: #4e8cff;">
    <div class="col-12 p-3 text-center">
        <h3 class="text-white fw-bold"><?php echo htmlspecialchars($doctor['name']); ?> - Profile</h3>
    </div>
</div>

<!-- Layout -->
<div class="row g-0 min-vh-100">
    <!-- Sidebar -->
    <div class="col-3 bg-white shadow-sm">
        <div class="p-3">
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action border-0 mb-2">
                    <i class="fas fa-home me-2"></i> Dashboard
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action active border-0 mb-2">
                    <i class="fas fa-user me-2"></i> Profile View
                </a>
                <a href="patients.php" class="list-group-item list-group-item-action border-0 mb-2">
                    <i class="fas fa-users me-2"></i> Patients
                </a>
                <a href="add_health.php" class="list-group-item list-group-item-action border-0 mb-2">
                    <i class="fas fa-notes-medical me-2"></i> Add Health Details
                </a>
                <a href="../logout.php" class="list-group-item list-group-item-action border-0 mb-2 text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i> Log Out
                </a>
            </div>
        </div>
    </div>

    <!-- Profile Content -->
    <div class="col-9 p-4">
        <div class="card shadow-sm p-4">
            <h4 class="mb-3">Doctor Profile</h4>

            <?php if (!empty($message)): ?>
                <div class="alert alert-info"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <div class="mb-4 text-center">
                    <img src="<?php echo $doctor['photo'] ? htmlspecialchars($doctor['photo']) : '../assets/default-avatar.png'; ?>" class="profile-pic mb-2" alt="Profile Picture">
                    <div class="upload-label w-25 mx-auto text-center">
                        <input type="file" name="photo" id="photoInput" class="form-control form-control-sm d-none">
                        
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>ID</label>
                        <input type="text" name="id" class="form-control" value="<?php echo $doctor['id']; ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($doctor['name']); ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="form-control" value="<?php echo htmlspecialchars($doctor['specialization']); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" style="max-width: 300px;" value="<?php echo htmlspecialchars($doctor['phone']); ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2" style="max-width: 500px;" readonly><?php echo htmlspecialchars($doctor['address']); ?></textarea>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="md-12">
                        <label>Specification</label>
                        <textarea name="specification" class="form-control" rows="2" readonly><?php echo htmlspecialchars($doctor['specification']); ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="button" class="btn btn-primary me-2 w-25" id="editBtn">Edit</button>
                    <button type="submit" name="save_changes" class="btn btn-success me-2 d-none" id="saveBtn">Save Changes</button>
                    <button type="button" class="btn btn-secondary d-none" id="cancelBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script -->
<script>
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const form = document.getElementById('profileForm');
    const inputs = form.querySelectorAll('input[name]:not([name="id"]):not([type="file"]), textarea[name]');

    let initialValues = [];

    editBtn.addEventListener('click', () => {
        inputs.forEach((input, i) => {
            initialValues[i] = input.value;
            input.removeAttribute('readonly');
        });
        document.getElementById('photoInput').classList.remove('d-none');
        editBtn.classList.add('d-none');
        saveBtn.classList.remove('d-none');
        cancelBtn.classList.remove('d-none');
    });

    cancelBtn.addEventListener('click', () => {
        inputs.forEach((input, i) => {
            input.value = initialValues[i];
            input.setAttribute('readonly', true);
        });
        document.getElementById('photoInput').classList.add('d-none');
        editBtn.classList.remove('d-none');
        saveBtn.classList.add('d-none');
        cancelBtn.classList.add('d-none');
    });
</script>

</body>
</html>
