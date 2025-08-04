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

    $msg = '';
    $activeTab = $_GET['tab'] ?? 'doctors';

    // Check for success message from redirect
    if (isset($_SESSION['delete_success'])) {
        $msg = '<div class="alert alert-success">' . $_SESSION['delete_success'] . '</div>';
        unset($_SESSION['delete_success']);
    }

    // Create charges table if it doesn't exist
    $create_charges_table = "CREATE TABLE IF NOT EXISTS charges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        charge_type VARCHAR(50) NOT NULL UNIQUE,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_charges_table);

    // Insert default charges if table is empty
    $check_charges = $conn->query("SELECT COUNT(*) as count FROM charges");
    if ($check_charges->fetch_assoc()['count'] == 0) {
        $default_charges = [
            ['appointment', 1000.00, 'Standard appointment fee'],
            ['doctor_consultation', 500.00, 'Doctor consultation fee'],
            ['report', 0.00, 'Medical reports and test results'],
            ['medication', 0.00, 'Medications and injections']
        ];
        
        $insert_stmt = $conn->prepare("INSERT INTO charges (charge_type, amount, description) VALUES (?, ?, ?)");
        foreach ($default_charges as $charge) {
            $insert_stmt->bind_param('sds', $charge[0], $charge[1], $charge[2]);
            $insert_stmt->execute();
        }
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_doctor'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $specialization = trim($_POST['specialization']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            $specification = trim($_POST['specification']);

            // Check if email already exists
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->bind_param('s', $email);
            $check_email->execute();
            
            if ($check_email->get_result()->num_rows > 0) {
                $msg = '<div class="alert alert-danger">Email already exists!</div>';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $user_stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'doctor')");
                $user_stmt->bind_param('sss', $name, $email, $hashed_password);
                
                if ($user_stmt->execute()) {
                    $new_user_id = $conn->insert_id;
                    
                    // Insert doctor
                    $doctor_stmt = $conn->prepare("INSERT INTO doctors (user_id, name, specialization, phone, address, specification) VALUES (?, ?, ?, ?, ?, ?)");
                    $doctor_stmt->bind_param('isssss', $new_user_id, $name, $specialization, $phone, $address, $specification);
                    
                    if ($doctor_stmt->execute()) {
                        $msg = '<div class="alert alert-success">Doctor added successfully!</div>';
                    } else {
                        // Delete the user if doctor insert fails
                        $conn->prepare("DELETE FROM users WHERE id = ?")->execute([$new_user_id]);
                        $msg = '<div class="alert alert-danger">Failed to add doctor details: ' . $conn->error . '</div>';
                    }
                } else {
                    $msg = '<div class="alert alert-danger">Failed to create user account: ' . $conn->error . '</div>';
                }
            }
        }

        if (isset($_POST['update_charges'])) {
            $updated = 0;
            foreach ($_POST['charges'] as $charge_id => $amount) {
                $amount = floatval($amount);
                $update_stmt = $conn->prepare("UPDATE charges SET amount = ? WHERE id = ?");
                $update_stmt->bind_param('di', $amount, $charge_id);
                if ($update_stmt->execute()) {
                    $updated++;
                }
            }
            $msg = '<div class="alert alert-success">Updated ' . $updated . ' charge(s) successfully!</div>';
        }

        if (isset($_POST['delete_doctor'])) {
            $doctor_id = intval($_POST['doctor_id']);
            
            // Debug: Log the deletion attempt
            error_log("Attempting to delete doctor with ID: " . $doctor_id);
            
            // Get user_id and doctor name before deleting
            $get_user = $conn->prepare("SELECT d.user_id, d.name FROM doctors d WHERE d.id = ?");
            $get_user->bind_param('i', $doctor_id);
            $get_user->execute();
            $result = $get_user->get_result();
            
            if ($result->num_rows > 0) {
                $doctor_data = $result->fetch_assoc();
                $user_id_to_delete = $doctor_data['user_id'];
                $doctor_name = $doctor_data['name'];
                
                error_log("Found doctor: " . $doctor_name . " with user_id: " . $user_id_to_delete);
                
                // Start transaction for safe deletion
                $conn->begin_transaction();
                
                try {
                    // Delete the user (this will cascade delete the doctor due to foreign key constraint)
                    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $delete_stmt->bind_param('i', $user_id_to_delete);
                    
                    if ($delete_stmt->execute()) {
                        $affected_rows = $conn->affected_rows;
                        error_log("Deleted user, affected rows: " . $affected_rows);
                        
                        if ($affected_rows > 0) {
                            $conn->commit();
                            $_SESSION['delete_success'] = "Dr. {$doctor_name} has been deleted successfully!";
                            header("Location: settings.php?tab=doctors");
                            exit();
                        } else {
                            throw new Exception("No records were deleted. Doctor may not exist.");
                        }
                    } else {
                        throw new Exception("Failed to execute delete query: " . $conn->error);
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    error_log("Delete failed: " . $e->getMessage());
                    $msg = '<div class="alert alert-danger">Failed to delete doctor: ' . $e->getMessage() . '</div>';
                }
            } else {
                error_log("Doctor with ID " . $doctor_id . " not found");
                $msg = '<div class="alert alert-danger">Doctor not found!</div>';
            }
        }
    }

    // Get all doctors
    $doctors_result = $conn->query("SELECT d.*, u.email FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY d.name");
    $doctors = $doctors_result->fetch_all(MYSQLI_ASSOC);

    // Get all charges
    $charges_result = $conn->query("SELECT * FROM charges ORDER BY charge_type");
    $charges = $charges_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Settings - Vet Clinic</title>
        <style>
            :root {
                --primary-color: #4e8cff;
                --secondary-color: #3a7bd5;
                --accent-color: #ff7e5f;
                --light-bg: #f8fafc;
                --dark-text: #2d3748;
                --light-text: #f8fafc;
                --success-color: #48bb78;
                --danger-color: #e53e3e;
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

            .nav-tabs {
                border-bottom: 2px solid #e2e8f0;
                margin-bottom: 2rem;
            }

            .nav-tabs .nav-link {
                border: none;
                color: var(--dark-text);
                padding: 1rem 1.5rem;
                font-weight: 500;
                border-bottom: 3px solid transparent;
            }

            .nav-tabs .nav-link:hover {
                border-color: transparent;
                background-color: rgba(78, 140, 255, 0.05);
                color: var(--primary-color);
            }

            .nav-tabs .nav-link.active {
                color: var(--primary-color);
                background-color: transparent;
                border-bottom-color: var(--primary-color);
            }

            .table {
                margin-bottom: 0;
            }

            .table th {
                background-color: #f8fafc;
                border-top: none;
                font-weight: 600;
                color: var(--dark-text);
            }

            .btn-danger {
                background-color: var(--danger-color);
                border-color: var(--danger-color);
            }

            .btn-danger:hover {
                background-color: #c53030;
                border-color: #c53030;
            }

            .charge-input {
                font-weight: 500;
                text-align: right;
            }

            .doctor-card {
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
                background: white;
            }

            .doctor-card h6 {
                color: var(--primary-color);
                margin-bottom: 0.5rem;
            }

            .doctor-info {
                font-size: 14px;
                color: #6b7280;
            }

            .delete-btn {
                background: none;
                border: none;
                color: var(--danger-color);
                font-size: 18px;
                cursor: pointer;
                padding: 0.25rem;
            }

            .delete-btn:hover {
                color: #c53030;
            }
        </style>

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <a class="link" href="billing.php"><i class="fas fa-receipt"></i> Billing</a>
                    <a class="link" href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a class="link active" href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>

            <div class="content-wrapper">
                <?= $msg ?>
                
                <!-- Tab Navigation -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab === 'doctors' ? 'active' : '' ?>" 
                           href="?tab=doctors">
                            <i class="fas fa-user-md me-2"></i>Manage Doctors
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $activeTab === 'charges' ? 'active' : '' ?>" 
                           href="?tab=charges">
                            <i class="fas fa-dollar-sign me-2"></i>Hospital Charges
                        </a>
                    </li>
                </ul>

                <!-- Doctors Tab -->
                <?php if ($activeTab === 'doctors'): ?>
                    <div class="row">
                        <!-- Left Side: Add New Doctor -->
                        <div class="col-md-6">
                            <div class="form-section">
                                <h4><i class="fas fa-plus-circle me-2"></i>Add New Doctor</h4>
                                <form method="post" class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Password</label>
                                        <input type="password" class="form-control" name="password" required minlength="6">
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Specialization</label>
                                        <input type="text" class="form-control" name="specialization" 
                                               placeholder="e.g., General Practice, Surgery" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" name="phone" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Specification/Details</label>
                                        <textarea class="form-control" name="specification" rows="3" 
                                                  placeholder="Additional qualifications, experience, etc."></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" name="add_doctor" class="btn btn-primary w-100">
                                            <i class="fas fa-plus me-2"></i>Add Doctor
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Right Side: Current Doctors -->
                        <div class="col-md-6">
                            <div class="form-section">
                                <h4><i class="fas fa-users me-2"></i>Current Doctors (<?= count($doctors) ?>)</h4>
                                <?php if (empty($doctors)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>No doctors found. Add your first doctor using the form on the left.
                                    </div>
                                <?php else: ?>
                                    <div style="max-height: 600px; overflow-y: auto;">
                                        <?php foreach ($doctors as $doctor): ?>
                                            <div class="doctor-card">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <h6><i class="fas fa-user-md me-2"></i><?= htmlspecialchars($doctor['name']) ?></h6>
                                                        <div class="doctor-info">
                                                            <div><strong>Specialization:</strong> <?= htmlspecialchars($doctor['specialization']) ?></div>
                                                            <div><strong>Email:</strong> <?= htmlspecialchars($doctor['email']) ?></div>
                                                            <div><strong>Phone:</strong> <?= htmlspecialchars($doctor['phone']) ?></div>
                                                            <div><strong>Address:</strong> <?= htmlspecialchars($doctor['address']) ?></div>
                                                            <?php if ($doctor['specification']): ?>
                                                                <div><strong>Details:</strong> <?= htmlspecialchars($doctor['specification']) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <form method="post" style="display: inline;" class="delete-doctor-form">
                                                        <input type="hidden" name="doctor_id" value="<?= $doctor['id'] ?>">
                                                        <button type="button" name="delete_doctor" class="delete-btn" 
                                                                title="Delete Doctor" 
                                                                onclick="confirmDoctorDeletion('<?= htmlspecialchars($doctor['name']) ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Charges Tab -->
                <?php if ($activeTab === 'charges'): ?>
                    <div class="form-section">
                        <h4><i class="fas fa-dollar-sign me-2"></i>Hospital Charges</h4>
                        <p class="text-muted mb-4">Update the standard charges for various services. These rates will be used as defaults in billing.</p>
                        
                        <form method="post">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Service Type</th>
                                            <th>Description</th>
                                            <th width="200">Amount (LKR)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($charges as $charge): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= ucwords(str_replace('_', ' ', $charge['charge_type'])) ?></strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($charge['description']) ?></small>
                                                </td>
                                                <td>
                                                    <div class="input-group">
                                                        <span class="input-group-text">LKR</span>
                                                        <input type="number" 
                                                               class="form-control charge-input" 
                                                               name="charges[<?= $charge['id'] ?>]" 
                                                               value="<?= number_format($charge['amount'], 2, '.', '') ?>" 
                                                               step="0.01" 
                                                               min="0" 
                                                               required>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="update_charges" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Charges
                                </button>
                                <small class="text-muted ms-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Changes will apply to new billing
                                </small>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Custom Delete Confirmation Modal -->
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white py-2">
                        <h6 class="modal-title" id="deleteConfirmModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Delete Doctor
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body py-3">
                        <p class="text-center mb-2">
                            Delete Dr <strong id="doctorNameToDelete"></strong>?
                        </p>
                        <small class="text-muted d-block text-center">This action cannot be undone.</small>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            Cancel Deletion
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">
                            Permanently Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            let formToSubmit = null;

            function confirmDoctorDeletion(doctorName) {
                // Set the doctor name in the modal
                document.getElementById('doctorNameToDelete').textContent = doctorName;
                
                // Store reference to the form that triggered the deletion
                formToSubmit = event.target.closest('.delete-doctor-form');
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                modal.show();
                
                // Prevent default action
                event.preventDefault();
                return false;
            }

            // Handle the confirm delete button click
            document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                if (formToSubmit) {
                    // Add the submit button name to the form data
                    const submitInput = document.createElement('input');
                    submitInput.type = 'hidden';
                    submitInput.name = 'delete_doctor';
                    submitInput.value = '1';
                    formToSubmit.appendChild(submitInput);
                    
                    // Hide the modal first
                    const modal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
                    if (modal) {
                        modal.hide();
                    }
                    
                    // Submit the form
                    formToSubmit.submit();
                }
            });

            // Clear the form reference when modal is hidden
            document.getElementById('deleteConfirmModal').addEventListener('hidden.bs.modal', function() {
                formToSubmit = null;
            });
        </script>
    </body>
</html>
