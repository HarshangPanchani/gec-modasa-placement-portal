<?php
// forgot_password.php
session_start();

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require the autoloader created by Composer
require 'vendor/autoload.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'db_connect.php';
    $enrollment_no = $_POST['enrollment_no'];

    // 1. Check if the enrollment number exists and fetch the email
    $stmt = $conn->prepare("SELECT email, name FROM students WHERE enrollment_no = ?");
    $stmt->bind_param("s", $enrollment_no);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        $student_email = $student['email'];
        $student_name = $student['name'];

        // 2. Generate a secure random password
        // A shorter, more memorable password might be better for this use case
        $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 10);
        
        // 3. Hash the new password before storing it
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // 4. Update the student's record with the new hashed password
        $update_stmt = $conn->prepare("UPDATE students SET password = ? WHERE enrollment_no = ?");
        $update_stmt->bind_param("ss", $hashed_password, $enrollment_no);
        
        if ($update_stmt->execute()) {
            // 5. If update is successful, send the email with the NEW plain-text password
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'panchani.harshang.ce20@gmail.com'; // <<< YOUR GMAIL ADDRESS
                $mail->Password   = 'tjjg nnfl eabq rymi';  // <<< YOUR 16-DIGIT APP PASSWORD
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Recipients
                $mail->setFrom('panchani.harshang.ce20@gmail.com', 'Student Portal Admin');
                $mail->addAddress($student_email, $student_name); // Send to the student's email

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your Password Has Been Reset';
                $mail->Body    = "Hi " . htmlspecialchars($student_name) . ",<br><br>" .
                                 "Your password for the Student Portal has been reset.<br><br>" .
                                 "Your new temporary password is: <b>" . $new_password . "</b><br><br>" .
                                 "Please log in using this new password and change it immediately from your profile page for security reasons.<br><br>" .
                                 "Thanks,<br>The Student Portal Team";
                $mail->AltBody = "Hi " . htmlspecialchars($student_name) . ",\n\nYour new temporary password is: " . $new_password . "\nPlease log in and change it immediately.";

                $mail->send();
                $message = ['text' => 'A new temporary password has been sent to the registered email address.', 'type' => 'success'];

            } catch (Exception $e) {
                // This is tricky. The password was reset but email failed.
                // Inform the user to contact support.
                $message = ['text' => "Your password was reset, but we failed to send the email. Please contact support. Mailer Error: {$mail->ErrorInfo}", 'type' => 'danger'];
            }
        } else {
            $message = ['text' => 'There was an error resetting your password. Please try again.', 'type' => 'danger'];
        }
        $update_stmt->close();
    } else {
        // To prevent attackers from checking for valid enrollment numbers, show a generic message.
        $message = ['text' => 'If that enrollment number exists in our system, a new password has been sent to the associated email address.', 'type' => 'success'];
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-md-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h2 class="text-center mb-4">Reset Your Password</h2>
                    <p class="text-center text-muted">Enter your enrollment number. We will generate a new password and send it to the email address on file.</p>

                    <?php if(!empty($message)): ?>
                        <div class="alert alert-<?php echo $message['type']; ?>" role="alert"><?php echo $message['text']; ?></div>
                    <?php endif; ?>

                    <form action="forgot_password.php" method="post">
                        <div class="mb-3">
                            <label for="enrollment_no" class="form-label">Enrollment Number</label>
                            <input type="text" class="form-control" id="enrollment_no" name="enrollment_no" pattern="\d{12}" title="Must be a 12-digit number" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg">Reset My Password</button>
                    </form>
                    <p class="text-center mt-3"><a href="login.php">Back to Login</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>