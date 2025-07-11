<?php
// gecm/admin/notify_students.php
session_start();
require_once '../db_connect.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['job_id']) && !empty($_POST['passout_years'])) {
    
    $job_id = (int)$_POST['job_id'];
    $passout_years = $_POST['passout_years']; // This is an array

    // 1. Get Job Details (for departments, HR contact, etc.)
    $stmt_job = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt_job->bind_param("i", $job_id);
    $stmt_job->execute();
    $job = $stmt_job->get_result()->fetch_assoc();
    $stmt_job->close();

    if (!$job) {
        $_SESSION['message'] = ['text' => 'Job posting not found.', 'type' => 'danger'];
        header("Location: manage_jobs.php");
        exit();
    }

    // 2. Generate the OTP from HR Contact Number
    $otp = substr($job['hr_contact'], -5);

    // 3. Build the query to find target students
    $branch_conditions = [];
    $job_departments = explode(',', $job['departments']);

    $branch_map = [
        'CE/IT' => ["'Computer Engineering'", "'Information Technology'"],
        'Auto/Mech' => ["'Automobile Engineering'", "'Mechanical Engineering'"],
        'EC' => ["'Electronics & Communication Engineering'"],
        'Elec' => ["'Electrical Engineering'"],
        'Civil' => ["'Civil Engineering'"]
    ];

    foreach ($job_departments as $dept_short) {
        if (isset($branch_map[$dept_short])) {
            $branch_conditions[] = "branch IN (" . implode(',', $branch_map[$dept_short]) . ")";
        }
    }
    
    if (empty($branch_conditions)) {
        $_SESSION['message'] = ['text' => 'No valid departments specified for this job.', 'type' => 'warning'];
        header("Location: manage_jobs.php");
        exit();
    }

    $passout_year_placeholders = implode(',', array_fill(0, count($passout_years), '?'));
    
    $sql_students = "SELECT email, name FROM students WHERE passout_year IN ($passout_year_placeholders) AND (" . implode(' OR ', $branch_conditions) . ")";
    
    $stmt_students = $conn->prepare($sql_students);
    $stmt_students->bind_param(str_repeat('s', count($passout_years)), ...$passout_years);
    $stmt_students->execute();
    $students_to_notify = $stmt_students->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_students->close();

    // 4. Loop and send emails
    $emails_sent = 0;
    if (!empty($students_to_notify)) {
        $mail = new PHPMailer(true);
        try {
            // Your PHPMailer Server Settings
            $mail->isSMTP(); $mail->Host = 'smtp.gmail.com'; $mail->SMTPAuth = true;
            $mail->Username = 'panchani.harshang.ce20@gmail.com'; $mail->Password = 'tjjg nnfl eabq rymi'; // Use your App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; $mail->Port = 587;
            $mail->Priority = 1;
            $mail->setFrom('panchani.harshang.ce20@gmail.com', 'Placement Cell');
            
            foreach ($students_to_notify as $student) {
                $mail->addBCC($student['email'], $student['name']); // Use BCC to send one email to many recipients privately
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'New Job Opportunity: ' . $job['company_name'];

            $logo_url = 'http://' . $_SERVER['HTTP_HOST'] . '/gecm/' . $job['company_logo_path'];
            
            $mail->Body = "
                <p>Hello Students,</p>
                <p>A new job opportunity is available from <b>" . htmlspecialchars($job['company_name']) . "</b>.</p>
                <p><b>Package:</b> " . htmlspecialchars($job['min_package']) . " - " . htmlspecialchars($job['max_package']) . " LPA</p>
                <p><b>Location:</b> " . htmlspecialchars($job['location']) . "</p>
                <p>To apply for this job, you will need the following verification code (OTP).</p>
                <h3 style='text-align:center; letter-spacing: 5px; background-color: #f0f0f0; padding: 10px;'>$otp</h3>
                <p>Please visit the job details page on the portal and enter this OTP to complete your application.</p>
                <p>Good Luck!</p>
                <p><b>Placement Cell</b></p>";

            $mail->send();
            $emails_sent = count($students_to_notify);

        } catch (Exception $e) {
             $_SESSION['message'] = ['text' => "Failed to send emails. Mailer Error: {$mail->ErrorInfo}", 'type' => 'danger'];
             header("Location: manage_jobs.php");
             exit();
        }
    }
    
    $_SESSION['message'] = ['text' => "Notification sent successfully to $emails_sent eligible students.", 'type' => 'success'];
    header("Location: manage_jobs.php");
    exit();
    
} else {
    $_SESSION['message'] = ['text' => 'Invalid request. Please select at least one passout year.', 'type' => 'danger'];
    header("Location: manage_jobs.php");
    exit();
}