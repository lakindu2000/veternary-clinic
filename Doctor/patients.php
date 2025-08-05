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

// Initialize search variables
$search_owner_name = $_GET['owner_name'] ?? '';
$search_owner_phone = $_GET['owner_phone'] ?? '';
$search_pet_name = $_GET['pet_name'] ?? '';
$patients = [];
$selected_patient = null;
$msg = '';

// Search for patients
if (!empty($search_owner_name) || !empty($search_owner_phone) || !empty($search_pet_name)) {
    $where = [];
    $params = [];
    $types = '';
    
    if (!empty($search_owner_name)) {
        $where[] = "owner_name LIKE ?";
        $params[] = '%' . $search_owner_name . '%';
        $types .= 's';
    }
    
    if (!empty($search_owner_phone)) {
        $where[] = "owner_phone LIKE ?";
        $params[] = '%' . $search_owner_phone . '%';
        $types .= 's';
    }
    
    if (!empty($search_pet_name)) {
        $where[] = "name LIKE ?";
        $params[] = '%' . $search_pet_name . '%';
        $types .= 's';
    }
    
    $query = "SELECT * FROM patients";
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    $query .= " ORDER BY created_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    // Show recent patients by default
    $stmt = $conn->prepare("SELECT * FROM patients ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get detailed patient info for modal
if (isset($_GET['view_id'])) {
    $patient_id = (int)$_GET['view_id'];
    $stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $selected_patient = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Clinic - Patient Records</title>
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
        
        .admin-info {
            display: flex;
            align-items: center;
            margin-left: 35px;
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
        
        .search-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .patient-table th {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .action-btn {
            padding: 5px 10px;
            font-size: 0.9rem;
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
<body>
    <!-- Navbar -->
    <div class="navbar">
        <div class="admin-info">
            <img src="<?= htmlspecialchars($current_user['profile_photo'] ?? '../assets/default-profile.jpg') ?>" class="profile-img">
            <span class="admin-name-nav">Dr. <?= htmlspecialchars($doctorName ?? 'Doctor') ?></span>
        </div>
    </div>
    
    <!-- Sidebar with simplified menu -->
    <div class="sidebar">
        <div class="w-100 d-flex flex-column align-items-start">
            <a class="link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a class="link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="link active" href="patients.php"><i class="fas fa-paw"></i> Patients</a>
            <a class="link" href="add_health.php"><i class="fas fa-heartbeat"></i> Add Health Details</a>
            <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content-wrapper">
        <!-- Search Form -->
        <div class="card search-card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-search me-2"></i>Search Patients</h5>
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Pet Name</label>
                        <input type="text" class="form-control" name="pet_name" 
                               value="<?= htmlspecialchars($search_pet_name) ?>" placeholder="Enter pet name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Owner Name</label>
                        <input type="text" class="form-control" name="owner_name" 
                               value="<?= htmlspecialchars($search_owner_name) ?>" placeholder="Enter owner name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Owner Phone</label>
                        <input type="text" class="form-control" name="owner_phone" 
                               value="<?= htmlspecialchars($search_owner_phone) ?>" placeholder="Enter phone number">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                        <a href="patients.php" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Patients Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-paw me-2"></i>Patient Records</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover patient-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pet Name</th>
                                <th>Species</th>
                                <th>Breed</th>
                                <th>Age</th>
                                <th>Owner</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($patients)): ?>
                                <?php foreach ($patients as $patient): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($patient['id']) ?></td>
                                        <td><?= htmlspecialchars($patient['name']) ?></td>
                                        <td><?= htmlspecialchars($patient['species']) ?></td>
                                        <td><?= htmlspecialchars($patient['breed'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($patient['age'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($patient['owner_name']) ?></td>
                                        <td><?= htmlspecialchars($patient['owner_phone']) ?></td>
                                        <td>
                                            <a href="patients.php?view_id=<?= $patient['id'] ?>" 
                                               class="btn btn-sm btn-info action-btn" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="add_health.php?patient_id=<?= $patient['id'] ?>" 
                                               class="btn btn-sm btn-success action-btn" title="Add Health Details">
                                                <i class="fas fa-heartbeat"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        No patients found. <?php if ($search_owner_name || $search_owner_phone || $search_pet_name): ?>
                                            Try different search criteria.
                                        <?php else: ?>
                                            No patients in database yet.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Patient Details Modal -->
    <?php if ($selected_patient): ?>
        <div class="modal fade show" id="patientModal" tabindex="-1" style="display: block; padding-right: 17px;" aria-modal="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Patient Full Details</h5>
                        <a href="patients.php" class="btn-close btn-close-white"></a>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Pet Information</h6>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Name:</span>
                                        <span><?= htmlspecialchars($selected_patient['name']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Species:</span>
                                        <span><?= htmlspecialchars($selected_patient['species']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Breed:</span>
                                        <span><?= htmlspecialchars($selected_patient['breed'] ?? 'N/A') ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Age:</span>
                                        <span><?= htmlspecialchars($selected_patient['age'] ?? 'N/A') ?></span>
                                    </li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">Owner Information</h6>
                                <ul class="list-group list-group-flush mb-3">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Name:</span>
                                        <span><?= htmlspecialchars($selected_patient['owner_name']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Phone:</span>
                                        <span><?= htmlspecialchars($selected_patient['owner_phone']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">ID Number:</span>
                                        <span><?= htmlspecialchars($selected_patient['owner_id_num'] ?? 'N/A') ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Email:</span>
                                        <span><?= htmlspecialchars($selected_patient['email'] ?? 'N/A') ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <h6 class="text-primary">Registration Details</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span class="fw-bold">Registered On:</span>
                                        <span><?= date('M j, Y g:i A', strtotime($selected_patient['created_at'])) ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="patients.php" class="btn btn-secondary">Close</a>
                        <a href="add_health.php?patient_id=<?= $selected_patient['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-heartbeat me-1"></i> Add Health Details
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Close modal when clicking backdrop
        document.addEventListener('DOMContentLoaded', function() {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.addEventListener('click', function() {
                    window.location.href = 'patients.php';
                });
            }
        });
    </script>
</body>
</html>