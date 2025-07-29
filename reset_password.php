<?php
require_once 'connection.php';
session_start();

// Check if user has verified their code
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['code_verified'])) {
    header("Location: forgot_password.php");
    exit();
}

$message = '';
$email = $_SESSION['reset_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['password']) && isset($_POST['confirm_password'])) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $message = "Passwords do not match.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE email = ?");
            $stmt->bind_param("ss", $hashed_password, $email);
            
            if ($stmt->execute()) {
                $message = "Password updated successfully. You can now <a href='login.php'>login</a> with your new password.";
                
                // Clear all reset-related session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['verification_sent']);
                unset($_SESSION['code_verified']);
            } else {
                $message = "Error updating password. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --primary-dark: #3a5bbf;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            margin: 0;
        }
        
        .reset-container {
            width: 100%;
            max-width: 500px;
        }
        
        .reset-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .card-header h3 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 2rem;
            background: white;
        }
        
        .form-control {
            height: 50px;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            border: 1px solid #e0e0e0;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: white;
            color: var(--dark-color);
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            font-weight: 600;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s;
            margin-top: 1rem;
            text-align: center;
            display: block;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .brand-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: white;
        }
        
        .email-display {
            font-weight: 600;
            color: var(--primary-color);
            word-break: break-all;
            margin-bottom: 1.5rem;
            display: block;
        }
        
        .password-instructions {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="card-header">
                <div class="brand-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h3>Reset Your Password</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-info"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <span class="email-display"><?php echo htmlspecialchars($email); ?></span>
                <p class="password-instructions">Create a new strong password for your account</p>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Password
                    </button>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt me-2"></i> Back to Login
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-focus on the first password field -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').focus();
        });
    </script>
</body>
</html>