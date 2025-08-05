<?php
require_once '../connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$user = [];

// Get user data
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_photo'])) {
    $target_dir = "../uploads/profile_photos/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
    $target_file = $target_dir . "profile_" . $user_id . "." . $file_extension;
    
    // Check if image file is a actual image
    $check = getimagesize($_FILES["profile_photo"]["tmp_name"]);
    if ($check === false) {
        $error = "File is not an image.";
    } elseif ($_FILES["profile_photo"]["size"] > 500000) {
        $error = "Sorry, your file is too large (max 500KB).";
    } elseif (!in_array(strtolower($file_extension), ['jpg', 'png', 'jpeg', 'gif'])) {
        $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
    } elseif (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
        // Update database with new photo path
        $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
        $stmt->bind_param('si', $target_file, $user_id);
        if ($stmt->execute()) {
            $success = "Profile photo updated successfully.";
            $user['profile_photo'] = $target_file; // Update local user data
        } else {
            $error = "Error updating profile photo in database.";
        }
    } else {
        $error = "Sorry, there was an error uploading your file.";
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $hashed_password, $user_id);
        if ($stmt->execute()) {
            $success = "Password changed successfully.";
        } else {
            $error = "Error updating password.";
        }
    }
}

// Fetch doctor data for navbar display
$stmt = $conn->prepare("SELECT name FROM doctors WHERE user_id = ?");
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Clinic - Doctor Profile</title>
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
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
        }
        
        .sidebar {
            height: 100vh;
            width: 250px;
            background: #ffffff;
            box-shadow: var(--card-shadow);
            position: fixed;
            padding-top: 70px;
            left: 0;
            top: 0;
            z-index: 100;
            overflow-y: auto;
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
            left: 0;
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
        
        .main-content {
            margin-left: 250px;
            padding-top: 70px;
            min-height: 100vh;
            width: calc(100% - 250px);
        }
        
        .content-wrapper {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .profile-section, .password-section {
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
        }
        
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .profile-photo-container {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-photo:hover {
            opacity: 0.8;
            transform: scale(1.03);
        }
        
        .profile-photo-upload {
            display: none;
        }
        
        .profile-details {
            width: 100%;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            font-weight: 600;
            width: 120px;
            color: var(--dark-text);
        }
        
        .detail-value {
            flex: 1;
            color: #555;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.5rem;
        }
        
        .alert {
            margin-bottom: 1.5rem;
        }
        
        .password-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .password-section h4 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }
        
        .user-name {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark-text);
        }
        
        .user-role {
            font-size: 0.9rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
        }

        .doctor-details-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                width: calc(100% - 200px);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="navbar">
        <div class="admin-info">
            <img src="<?= htmlspecialchars($user['profile_photo'] ?? '../assets/default-profile.jpg') ?>" class="profile-img">
            <span class="admin-name-nav">Dr. <?= htmlspecialchars($doctor['name'] ?? $user['name'] ?? 'Doctor') ?></span>
        </div>
    </div>
    
    <div class="sidebar">
        <div class="w-100 d-flex flex-column align-items-start">
            <a class="link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a class="link active" href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a class="link" href="patients.php"><i class="fas fa-paw"></i> Patients</a>
            <a class="link" href="add_health.php"><i class="fas fa-heartbeat"></i> Add Health Details</a>
            <a class="link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-wrapper">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="profile-container">
                <!-- Left Column - User Profile Details -->
                <div class="profile-section">
                    <div class="profile-header">
                        <form method="post" enctype="multipart/form-data" id="profile-photo-form">
                            <div class="profile-photo-container">
                                <label for="profile-photo-upload">
                                    <img src="<?= htmlspecialchars($user['profile_photo'] ?? '../assets/default-profile.jpg') ?>" 
                                         class="profile-photo" 
                                         id="profile-photo-preview">
                                    <input type="file" 
                                           id="profile-photo-upload" 
                                           name="profile_photo" 
                                           class="profile-photo-upload" 
                                           accept="image/*">
                                </label>
                            </div>
                        </form>
                        <h2 class="user-name"><?= htmlspecialchars($user['email'] ?? 'Doctor') ?></h2>
                        <span class="user-role badge bg-primary"><?= htmlspecialchars(ucfirst($user['role'] ?? 'doctor')) ?></span>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-row">
                            <div class="detail-label">Email:</div>
                            <div class="detail-value"><?= htmlspecialchars($user['email'] ?? 'Not set') ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Role:</div>
                            <div class="detail-value"><?= htmlspecialchars(ucfirst($user['role'] ?? 'doctor')) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Member Since:</div>
                            <div class="detail-value"><?= date('M j, Y', strtotime($user['created_at'] ?? 'now')) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Last Login:</div>
                            <div class="detail-value"><?= date('M j, Y g:i A', strtotime($user['last_login'] ?? 'now')) ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Password Change -->
                <div class="password-section">
                    <h4>Change Password</h4>
                    <form method="post" class="password-form">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="mt-3">
                            <button type="submit" name="change_password" class="btn btn-primary w-100">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit profile photo when selected
        document.getElementById('profile-photo-upload').addEventListener('change', function() {
            if (this.files.length > 0) {
                document.getElementById('profile-photo-form').submit();
            }
        });

        // Preview profile photo before upload
        document.getElementById('profile-photo-upload').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profile-photo-preview').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    </script>

</body>
</html>
