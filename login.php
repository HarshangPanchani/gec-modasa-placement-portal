<?php
session_start();
require_once 'db_connect.php';

$errorMessage = "";

// If user is already logged in, redirect them to the profile page
if (isset($_SESSION['enrollment_no'])) {
    header("Location: profile.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $enrollment_no = $_POST['enrollment_no'];
    $password = $_POST['password'];

    if (empty($enrollment_no) || empty($password)) {
        $errorMessage = "Please enter both enrollment number and password.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM students WHERE enrollment_no = ?");
        $stmt->bind_param("s", $enrollment_no);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify the password against the stored hash
            if (password_verify($password, $user['password'])) {
                // Password is correct, start the session
                $_SESSION['enrollment_no'] = $enrollment_no;
                header("Location: profile.php");
                exit();
            } else {
                $errorMessage = "Invalid enrollment number or password.";
            }
        } else {
            $errorMessage = "Invalid enrollment number or password.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Student Portal Login</h2>

                    <?php if(!empty($errorMessage)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo $errorMessage; ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="post">
                        <div class="mb-3">
                            <label for="enrollment_no" class="form-label">Enrollment Number</label>
                            <input type="text" class="form-control" id="enrollment_no" name="enrollment_no" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg">Login</button>
                    </form>
                    
                   
                    <div class="mb-3 text-end">
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                    <hr>
                    <p class="text-center">Don't have an account? <a href="register.php">Register Here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>