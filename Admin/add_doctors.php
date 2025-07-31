<?php 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinary Doctor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
       <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-user-md"></i> Veterinary Doctor Management System</h2>
                <hr>
            </div>
        </div>
        <!-- Doctors search section -->
        <div class="row mb-3">
            <div class="col-md-8">
                <form method="GET" class="d-flex">
                    <input type="text" class="form-control me-3" name="search" 
                           placeholder="Search by name or phone number" 
                           value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">

                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>

                    <!-- clear button logic -->
                    <?php if (isset($_GET['search'])): ?>
                        <a href="doctor-dashboard.php" class="btn btn-outline-danger ms-2">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- doctors add button -->
            <div class="col-md-4 text-end">
                <button class="btn btn-success h-100" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Doctor
                </button>
            </div>
        </div>

    </div>
    
</body>
</html>