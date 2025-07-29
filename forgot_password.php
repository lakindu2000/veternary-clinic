<?php
require_once 'connection.php';
session_start();

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        // Generate 6-digit verification code
        $verification_code = rand(100000, 999999);
        $expiry_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Update user record with verification code
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $verification_code, $expiry_time, $email);
        $update_stmt->execute();
        
        try {
            // Send email with verification code using PHPMailer
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'dnithyamaheshi@gmail.com';
            $mail->Password   = 'bupu xclo wguo hpud';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Recipients
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Verification Code';
            $mail->Body    = "Your verification code is: <b>$verification_code</b><br><br>This code will expire in 15 minutes.";
            $mail->AltBody = "Your verification code is: $verification_code\n\nThis code will expire in 15 minutes.";
            
            $mail->send();
            
            // For development/testing, also show the code on screen
            $message = "A verification code has been sent to your email. ";
            $message .= "For testing purposes, your code is: $verification_code";
            
            // Store email in session for verification
            $_SESSION['reset_email'] = $email;
            $_SESSION['verification_sent'] = true;
            
            // Redirect to verification page
            header("Location: verify_code.php");
            exit();
            
        } catch (Exception $e) {
            $message = "Could not send verification email. Error: {$mail->ErrorInfo}";
            // For development, still show the code
            $message .= "<br>For testing purposes, your code would be: $verification_code";
        }
    } else {
        $message = "Email not found in our system.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
            height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .password-reset-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
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
        }
        
        .form-control {
            height: 50px;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="password-reset-card">
                    <div class="card-header">
                        <div class="brand-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>Reset Your Password</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($message)): ?>
                            <div class="alert alert-info"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <p class="text-muted mb-4">Enter your email address and we'll send you a verification code to reset your password.</p>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="your@email.com" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Send Verification Code
                            </button>
                            
                            <a href="login.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Login
                            </a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>