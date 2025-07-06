<?php
// gecm/admin/add_admin.php
session_start();
require_once '../db_connect.php';

// SUPER ADMIN ONLY
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_name = $_POST['admin_name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    // Assign branch only if role is 'sub_admin', otherwise it's NULL
    $branch_assigned = ($role === 'sub_admin') ? $_POST['branch_assigned'] : null;

    // Check if email already exists
    $stmt_check = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $_SESSION['message'] = ['text' => 'Error: An admin with this email already exists.', 'type' => 'danger'];
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt_insert = $conn->prepare("INSERT INTO admins (admin_name, email, password, role, branch_assigned) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("sssss", $admin_name, $email, $hashed_password, $role, $branch_assigned);
        
        if ($stmt_insert->execute()) {
            $_SESSION['message'] = ['text' => 'New admin created successfully.', 'type' => 'success'];
            header("Location: manage_admins.php");
            exit();
        } else {
            $_SESSION['message'] = ['text' => 'Error creating admin: ' . $stmt_insert->error, 'type' => 'danger'];
        }
    }
    // Redirect back to the form if there's an error
    header("Location: add_admin.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Create New Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3>Create a New Admin Account</h3>
    <hr>
    <div class="card shadow-sm">
        <div class="card-body">
            <form action="add_admin.php" method="POST" id="admin-form">
                <div class="mb-3">
                    <label class="form-label">Admin Name *</label>
                    <input type="text" name="admin_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Email *</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role *</label>
                    <select name="role" id="role-select" class="form-select" required>
                        <option value="sub_admin">Sub Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="mb-3" id="branch-assignment">
                    <label class="form-label">Assign Branch *</label>
                    <select name="branch_assigned" class="form-select">
                        <option value="">Choose a branch...</option>
                        <option>Computer Engineering</option>
                        <option>Information Technology</option>
                        <option>Mechanical Engineering</option>
                        <option>Civil Engineering</option>
                        <option>Electrical Engineering</option>
                        <option>Electronics & Communication</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Create Admin</button>
                <a href="manage_admins.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript to show/hide the branch assignment based on selected role
document.getElementById('role-select').addEventListener('change', function() {
    var branchDiv = document.getElementById('branch-assignment');
    if (this.value === 'sub_admin') {
        branchDiv.style.display = 'block';
    } else {
        branchDiv.style.display = 'none';
    }
});
</script>

</body>
</html>