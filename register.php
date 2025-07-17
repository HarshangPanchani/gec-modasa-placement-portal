<?php
include 'db_connect.php'; // ensure this connects properly

session_start();

// If user is already logged in, destroy session and start fresh
if (isset($_SESSION['enrollment_no'])) {
    session_unset();
    session_destroy();
    session_start();
} 

// If user is already logged in, stop them from accessing register page
if (isset($_SESSION['enrollment_no'])) {
    header("Location: login.php");  // Redirect to login page instead of profile
    exit();
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>Student Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="card shadow-lg">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0">Student Registration</h3>
        </div>
        <div class="card-body">
            <form action="register_process.php" method="POST">

                <div class="mb-3">
                    <label for="name" class="form-label">Student Name</label>
                    <input type="text" name="name" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="enrollment" class="form-label">Enrollment Number</label>
                    <input type="text" name="enrollment" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="mobile" class="form-label">Mobile Number</label>
                    <input type="text" name="mobile" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required />
                </div>
                <button type="submit" class="btn btn-success w-100">Register</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
