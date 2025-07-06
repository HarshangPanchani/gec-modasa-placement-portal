<?php
// gecm/company/login.php
session_start();
require_once '../db_connect.php'; // Note the '..'

$errorMessage = "";

if (isset($_SESSION['company_email'])) {
    header("Location: profile.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT password FROM companies WHERE hr_email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $company = $result->fetch_assoc();
        if (password_verify($password, $company['password'])) {
            $_SESSION['company_email'] = $email;
            header("Location: profile.php");
            exit();
        }
    }
    $errorMessage = "Invalid email or password.";
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Company Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow" style="width: 25rem;">
        <div class="card-body p-4">
            <h2 class="text-center mb-4">Company Login</h2>
            <?php if(!empty($errorMessage)): ?><div class="alert alert-danger"><?php echo $errorMessage; ?></div><?php endif; ?>
            <form action="login.php" method="post">
                <div class="mb-3"><label class="form-label">HR Email Address</label><input type="email" name="email" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <div class="text-end mb-3">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <p class="text-center mt-3">New here? <a href="register.php">Register your Company</a></p>
        </div>
    </div>
</div>
</body>
</html>