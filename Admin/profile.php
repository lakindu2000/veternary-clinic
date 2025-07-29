<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit;
}

require_once '../connection.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, email, role, profile_photo FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["update_profile_photo"])) {
        $profile_photo = $user['profile_photo'];

        if (!empty($_FILES["profile_photo"]["name"])) {
            $target_dir = "../uploads/profile_photos/";
            $filename = time() . '_' . basename($_FILES["profile_photo"]["name"]);
            $target_file = $target_dir . $filename;

            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
                if (!empty($profile_photo) && file_exists($profile_photo)) {
                    unlink($profile_photo);
                }
                $profile_photo = $target_file;
            } else {
                $_SESSION['error'] = "Error uploading image.";
                header("Location: admin_profile.php");
                exit;
            }

            $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
            $stmt->bind_param("si", $profile_photo, $user_id);
            $stmt->execute();

            $_SESSION['success'] = "Profile photo updated successfully!";
            $user['profile_photo'] = $profile_photo;
        }
    }

    if (isset($_POST["change_password"])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $_SESSION['error'] = "Passwords do not match.";
            header("Location: admin_profile.php");
            exit;
        }

        $verification_code = random_int(100000, 999999);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your_email@gmail.com';
            $mail->Password   = 'your_password';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('your_email@gmail.com', 'Admin');
            $mail->addAddress($user['email']);

            $mail->isHTML(true);
            $mail->Subject = 'Password Change Verification';
            $mail->Body    = "Your verification code is <b>$verification_code</b>";

            $mail->send();

            $_SESSION['verification_code'] = $verification_code;
            $_SESSION['new_password'] = password_hash($new_password, PASSWORD_DEFAULT);

            header("Location: verify_code.php");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = "Mailer Error: {$mail->ErrorInfo}";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .profile-header {
            background-color: #4e73df;
            color: white;
            padding: 2rem 0;
            text-align: center;
            position: relative;
        }
        .profile-photo {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            cursor: pointer;
        }
        .hidden-file-input {
            display: none;
        }
        .profile-info {
            background: white;
            padding: 2rem;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .section-title {
            color: #4e73df;
            margin-bottom: 1rem;
            border-bottom: 2px solid #4e73df;
            padding-bottom: 0.5rem;
        }
        .btn-primary {
            background-color: #4e73df;
            border: none;
        }
        .btn-primary:hover {
            background-color: #3a5bbf;
        }
        .position-relative .password-toggle {
            position: absolute;
            right: 10px;
            top: 10px;
            cursor: pointer;
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="container-fluid p-0">
    <div class="profile-header">
        <form method="post" enctype="multipart/form-data" id="photoForm">
            <input type="file" name="profile_photo" id="profileInput" class="hidden-file-input" accept="image/*" onchange="document.getElementById('photoForm').submit();">
            <input type="hidden" name="update_profile_photo" value="1">
            <img src="<?php echo htmlspecialchars($user['profile_photo'] ?? '../images/default-avatar.png'); ?>" 
                class="profile-photo" onclick="document.getElementById('profileInput').click();" alt="Profile Photo">
        </form>
        <h2 class="mt-3">Admin Profile</h2>
    </div>

    <div class="container profile-info mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <h4 class="section-title">User Information</h4>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
            </div>
            <div class="col-md-6">
                <h4 class="section-title">Change Password</h4>
                <form method="post">
                    <div class="mb-3 position-relative">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password')"></i>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary w-100">
                        <i class="fas fa-key me-2"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS and password toggle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling;
        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = "password";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
</script>
</body>
</html>
