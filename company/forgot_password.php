<?php
// gecm/company/forgot_password.php
session_start();

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require the autoloader from the root directory
require '../vendor/autoload.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Connect to the database from the root directory
    require_once '../db_connect.php'; 

    $hr_email = $_POST['hr_email'];

    // 1. Check if the HR email exists in the companies table
    $stmt = $conn->prepare("SELECT company_name, hr_name FROM companies WHERE hr_email = ?");
    $stmt->bind_param("s", $hr_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $company = $result->fetch_assoc();
        $company_name = $company['company_name'];
        $hr_name = $company['hr_name'];

        // 2. Generate a secure random password
        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
        
        // 3. Hash the new password before storing it
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 4. Update the company's record with the new hashed password
        $update_stmt = $conn->prepare("UPDATE companies SET password = ? WHERE hr_email = ?");
        $update_stmt->bind_param("ss", $hashed_password, $hr_email);
        
        if ($update_stmt->execute()) {
            // 5. If update is successful, send the email with the NEW plain-text password
            $mail = new PHPMailer(true);
            try {
                // Server settings (use the same credentials as before)
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'panchani.harshang.ce20@gmail.com'; // <<< YOUR GMAIL ADDRESS
                $mail->Password   = 'tjjg nnfl eabq rymi';  // <<< YOUR 16-DIGIT APP PASSWORD
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom('panchani.harshang.ce20@gmail.com', 'Placement Cell');
                $mail->addAddress($hr_email, $hr_name); // Send to the company HR's email

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Company Portal Password Reset';
                $mail->Body    = "Hi " . htmlspecialchars($hr_name) . ",<br><br>" .
                                 "Your password for the " . htmlspecialchars($company_name) . " account on our Company Portal has been reset.<br><br>" .
                                 "Your new temporary password is: <b>" . $new_password . "</b><br><br>" .
                                 "Please log in using this new password and change it immediately from your company profile page for security reasons.<br><br>" .
                                 "Thanks,<br>The Placement Team";
                $mail->AltBody = "Hi " . htmlspecialchars($hr_name) . ",\n\nYour new temporary password for the " . htmlspecialchars($company_name) . " account is: " . $new_password . "\nPlease log in and change it immediately.";

                $mail->send();
                $message = ['text' => 'A new temporary password has been sent to the registered email address.', 'type' => 'success'];

            } catch (Exception $e) {
                $message = ['text' => "Your password was reset, but we failed to send the email. Please contact support. Mailer Error: {$mail->ErrorInfo}", 'type' => 'danger'];
            }
        } else {
            $message = ['text' => 'There was an error resetting the password. Please try again.', 'type' => 'danger'];
        }
        $update_stmt->close();
    } else {
        // To prevent attackers from checking for valid emails, show a generic message.
        $message = ['text' => 'If that email address exists in our system, a new password has been sent.', 'type' => 'success'];
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Company Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Reset Company Password</h2>
                    <p class="text-center text-muted">Enter your company's registered HR email. We will generate a new password and send it to that address.</p>

                    <?php if(!empty($message)): ?>
                        <div class="alert alert-<?php echo $message['type']; ?>" role="alert"><?php echo $message['text']; ?></div>
                    <?php endif; ?>

                    <form action="forgot_password.php" method="post">
                        <div class="mb-3">
                            <label for="hr_email" class="form-label">HR Email Address</label>
                            <input type="email" class="form-control" id="hr_email" name="hr_email" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg">Reset Password</button>
                    </form>
                    <p class="text-center mt-3"><a href="login.php">Back to Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>