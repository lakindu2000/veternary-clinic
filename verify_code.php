<?php
require_once 'connection.php';
session_start();

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['verification_sent'])) {
    header("Location: forgot_password.php");
    exit();
}

$message = '';
$email = $_SESSION['reset_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['verification_code'])) {
    $user_code = trim($_POST['verification_code']);
    
    // Check if code matches and isn't expired
    $stmt = $conn->prepare("SELECT reset_token, reset_token_expiry FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $db_code = $user['reset_token'];
        $expiry_time = $user['reset_token_expiry'];
        
        // Check if code matches and isn't expired
        if ($user_code === $db_code && strtotime($expiry_time) > time()) {
            // Code is valid, allow password reset
            $_SESSION['code_verified'] = true;
            header("Location: reset_password.php");
            exit();
        } else {
            $message = "Invalid or expired verification code. Please try again.";
        }
    } else {
        $message = "Error verifying code. Please start the process again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code</title>
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
        
        .verification-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .verification-card {
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
            font-size: 1.1rem;
            text-align: center;
            letter-spacing: 0.2em;
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
        }
        
        .instruction-text {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-card">
            <div class="card-header">
                <div class="brand-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Verify Your Code</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <p class="instruction-text">We've sent a 6-digit verification code to:</p>
                <p class="email-display mb-4"><?php echo htmlspecialchars($email); ?></p>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="verification_code" class="form-label mb-2">Enter Verification Code</label>
                        <input type="text" class="form-control" id="verification_code" name="verification_code" 
                               required maxlength="6" pattern="\d{6}" title="Please enter a 6-digit code"
                               inputmode="numeric" autocomplete="one-time-code">
                    </div>
                    
                    <button type="submit" class="btn btn-primary mb-2">
                        <i class="fas fa-check-circle me-2"></i> Verify Code
                    </button>
                    
                    <a href="forgot_password.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-2"></i> Resend Code
                    </a>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-focus on the input field -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('verification_code').focus();
        });
    </script>
</body>
</html>