<?php
// gecm/admin/login.php
session_start();
require_once '../db_connect.php';

$errorMessage = "";

// If already logged in, redirect to the dashboard, NOT the login page itself.
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php"); 
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, admin_name, email, password, role, branch_assigned FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            // Login successful, store crucial info in the session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['admin_name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_branch'] = $admin['branch_assigned']; // Will be NULL for super_admin
            
            // On successful login, redirect to the new dashboard page.
            header("Location: dashboard.php"); 
            exit();
        }
    }
    // If login fails for any reason
    $errorMessage = "Invalid email or password.";
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-sm" style="width: 25rem;">
        <div class="card-body p-4">
            <h2 class="text-center mb-4">Admin Portal Login</h2>
            <?php if(!empty($errorMessage)): ?><div class="alert alert-danger"><?php echo $errorMessage; ?></div><?php endif; ?>
            <form action="login.php" method="post">
                <div class="mb-3"><label class="form-label">Admin Email</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="text-end mb-3"><a href="forgot_password.php">Forgot Password?</a></div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>