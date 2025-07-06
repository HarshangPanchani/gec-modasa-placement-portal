<?php
// gecm/admin/dashboard.php
session_start();

// Auth check - If not logged in, redirect to login page
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</h3>
        <div>
            <a href="profile.php" class="btn btn-info">My Profile</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h4 class="mb-0">Admin Dashboard</h4></div>
        <div class="card-body p-4">
            <p>This is the main dashboard for the administration panel.</p>
            <p>From here, you will be able to manage students, companies, and other site settings based on your role.</p>
            <hr>
            <h5>Your Details:</h5>
            <ul>
                <li><strong>Role:</strong> <span class="badge bg-success"><?php echo str_replace('_', ' ', ucwords($_SESSION['admin_role'])); ?></span></li>
                <?php if ($_SESSION['admin_role'] === 'sub_admin'): ?>
                    <li><strong>Assigned Branch:</strong> <span class="badge bg-primary"><?php echo htmlspecialchars($_SESSION['admin_branch']); ?></span></li>
                <?php endif; ?>
            </ul>
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Manage Students</h5>
                            <p class="card-text">View, edit, and delete student records.</p>
                            <a href="manage_students.php" class="btn btn-primary">Go to Student Management</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                     <div class="card text-center mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Manage Companies</h5>
                            <p class="card-text">View, edit, and delete company records.</p>
                            <a href="manage_companies.php" class="btn btn-primary">Go to Company Management</a>
                        </div>
                    </div>
                </div>
                <?php if ($_SESSION['admin_role'] === 'super_admin'): ?>
                <div class="col-md-4">
                    <div class="card text-center mb-3">
                        <div class="card-body">
                            <h5 class="card-title">Manage Admins</h5>
                            <p class="card-text">Create, edit, and delete admin accounts.</p>
                            <a href="manage_admins.php" class="btn btn-warning">Go to Admin Management</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>