<?php
// gecm/admin/forgot_password.php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once '../db_connect.php';
    $email = $_POST['email'];

    $stmt = $conn->prepare("SELECT admin_name FROM admins WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $admin_name = $admin['admin_name'];
        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 12);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE admins SET password = ? WHERE email = ?");
        $update_stmt->bind_param("ss", $hashed_password, $email);
        
        if ($update_stmt->execute()) {
            $mail = new PHPMailer(true);
            try {
                // Your PHPMailer config from the other files goes here
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'panchani.harshang.ce20@gmail.com';
                $mail->Password   = 'tjjg nnfl eabq rymi';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->Priority   = 1;

                $mail->setFrom('panchani.harshang.ce20@gmail.com', 'Portal System Admin');
                $mail->addAddress($email, $admin_name);

                $mail->isHTML(true);
                $mail->Subject = 'Admin Portal Password Reset';
                $mail->Body    = "Hi " . htmlspecialchars($admin_name) . ",<br><br>Your password for the Admin Portal has been reset.<br><br>Your new temporary password is: <b>" . $new_password . "</b><br><br>Please log in immediately and change it.";
                $mail->send();
                $message = ['text' => 'A new password has been sent to the registered email address.', 'type' => 'success'];
            } catch (Exception $e) {
                $message = ['text' => "Password reset, but email failed: {$mail->ErrorInfo}", 'type' => 'danger'];
            }
        }
    } else {
        $message = ['text' => 'If this email is registered, a reset link has been sent.', 'type' => 'info'];
    }
}
?>
<!DOCTYPE html>
<!-- The HTML form for forgot password goes here, similar to the student/company version -->
<html lang="en">
    <head>
        <title>Admin Forgot Password</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
<body class="bg-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card shadow-sm" style="width: 25rem;">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Reset Admin Password</h2>
                <?php if(!empty($message)):?>
                    <div class="alert alert-<?php echo $message['type'];?>">
                        <?php echo $message['text'];?></div><?php endif;?>
                        <form action="forgot_password.php" method="post">
                            <div class="mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                            <p class="text-center mt-3"><a href="login.php" >Back to Login</a>
                            </p>
                        </form>
                    </div>
        </div>
    </div>
</body>
</html>