<?php
// Include database connection
require_once 'connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trusted Veterinary Clinic</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            overflow: hidden;
            background: #f0f8ff;
        }
        
        .main-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .vet-banner {
            background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
            color: white;
            padding: 2rem;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .clinic-name {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .tagline {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .btn-getstart {
            background: white;
            color: #4b6cb7;
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            width: fit-content;
            margin: 0 auto;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-getstart:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
            color: #182848;
        }
        
        .paw-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: rgba(255,255,255,0.9);
        }
        
        @media (max-width: 768px) {
            .clinic-name {
                font-size: 2rem;
            }
            .tagline {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="vet-banner">
            <div class="paw-icon">
                <i class="fas fa-paw"></i>
            </div>
            <h1 class="clinic-name">TRUSTED VETERINARY CLINIC</h1>
            <p class="tagline">Exceptional care for your beloved pets</p>
            <a href="login.php" class="btn-getstart">
                Get Started <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>

    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>