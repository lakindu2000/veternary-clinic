<?php
include 'db_connection.php';

$sql = "SELECT * FROM doctors";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Doctor: " . $row["name"] . "<br>";
    }
} else {
    echo "No data found.";
}
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Header Section -->
    <div class="row g-0" style="background-color: #4e8cff;">
        <div class="col-12 p-3">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <img src="Loosy.png" alt="Doctor Photo" class="rounded-circle border border-white border-3" style="width: 60px; height: 60px;">
                </div>
                <div class="flex-grow-1 text-center">
                    <h3 class="text-white mb-0 fw-bold">Dr. Sarah Johnson</h3>
                    <p class="text-white-50 mb-0">Cardiologist</p>
                </div>
                <div class="me-3">
                    <i class="fas fa-bell text-white fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Layout -->
    <div class="row g-0 min-vh-100">
        <!-- Left Sidebar -->
        <div class="col-3 bg-white shadow-sm">
            <div class="p-3">
                <div class="list-group list-group-flush">
                    <a href="dashboard.php" class="list-group-item list-group-item-action border-0 rounded mb-2">
                        <i class="fas fa-home me-3"></i>Dashboard
                    </a>
                    <a href="profile.php" class="list-group-item list-group-item-action active border-0 rounded mb-2">
                        <i class="fas fa-user me-3"></i>Profile View
                    </a>
                    <a href="#" class="list-group-item list-group-item-action border-0 rounded mb-2">
                        <i class="fas fa-users me-3"></i>Patients
                    </a>
                    <a href="#" class="list-group-item list-group-item-action border-0 rounded mb-2">
                        <i class="fas fa-plus-circle me-3"></i>Add Health Details
                    </a>
                    <a href="#" class="list-group-item list-group-item-action border-0 rounded mb-2 text-danger">
                        <i class="fas fa-sign-out-alt me-3"></i>Log Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Profile View Content -->
        <div class="col-9 p-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h4 class="mb-0">Doctor Profile</h4>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img id="doctorPhoto" src="doctor_photo.jpg" alt="Doctor Photo" class="rounded-circle border border-primary" style="width: 120px; height: 120px; object-fit: cover;">
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Doctor ID</label>
                            <input type="text" class="form-control" id="doctorId" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">User ID</label>
                            <input type="text" class="form-control" id="userId" disabled>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" class="form-control editable" id="name" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Specialization</label>
                            <input type="text" class="form-control editable" id="specification" disabled>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" class="form-control editable" id="phone" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" class="form-control editable" id="address" disabled>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control editable" id="description" rows="3" disabled></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Upload New Photo</label>
                        <input type="file" class="form-control editable" id="photoInput" disabled>
                    </div>

                    <div class="text-end">
                        <button class="btn btn-primary me-2" onclick="enableEdit()" id="editBtn">Edit Profile</button>
                        <button class="btn btn-success d-none" onclick="saveProfile()" id="saveBtn">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const doctorData = {
            id: 1,
            user_id: 101,
            name: "Dr. Sarah Johnson",
            specification: "Cardiologist",
            phone: "0771234567",
            address: "123, Colombo Road, Sri Lanka",
            description: "Experienced cardiologist with over 10 years in the field.",
            photo: "doctor_photo.jpg"
        };

        window.onload = function () {
            document.getElementById('doctorId').value = doctorData.id;
            document.getElementById('userId').value = doctorData.user_id;
            document.getElementById('name').value = doctorData.name;
            document.getElementById('specification').value = doctorData.specification;
            document.getElementById('phone').value = doctorData.phone;
            document.getElementById('address').value = doctorData.address;
            document.getElementById('description').value = doctorData.description;
            document.getElementById('doctorPhoto').src = doctorData.photo;
        };

        function enableEdit() {
            document.querySelectorAll('.editable').forEach(input => {
                input.disabled = false;
            });
            document.getElementById('editBtn').classList.add('d-none');
            document.getElementById('saveBtn').classList.remove('d-none');
        }

        function saveProfile() {
            // You can add code here to send the updated data to the server via AJAX or form submission.
            alert('Profile changes saved!');
            document.querySelectorAll('.editable').forEach(input => {
                input.disabled = true;
            });
            document.getElementById('editBtn').classList.remove('d-none');
            document.getElementById('saveBtn').classList.add('d-none');
        }
    </script>
</body>
</html>