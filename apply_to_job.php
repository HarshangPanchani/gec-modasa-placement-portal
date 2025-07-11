<?php
// gecm/apply_to_job.php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['enrollment_no'])) { header("Location: login.php"); exit(); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['job_id']) && isset($_POST['otp'])) {
    $job_id = (int)$_POST['job_id'];
    $submitted_otp = $_POST['otp'];
    $enrollment_no = $_SESSION['enrollment_no'];

    // --- 1. Get Job HR Contact to verify OTP ---
    $stmt_job = $conn->prepare("SELECT hr_contact FROM jobs WHERE id = ?");
    $stmt_job->bind_param("i", $job_id);
    $stmt_job->execute();
    $job_result = $stmt_job->get_result();
    if ($job_result->num_rows === 0) {
        $_SESSION['message'] = ['text' => 'Invalid job posting.', 'type' => 'danger'];
        header("Location: jobs.php"); exit();
    }
    $hr_contact = $job_result->fetch_assoc()['hr_contact'];
    $correct_otp = substr($hr_contact, -5);
    $stmt_job->close();

    // --- 2. Verify OTP ---
    if ($submitted_otp !== $correct_otp) {
        $_SESSION['message'] = ['text' => 'Invalid OTP. Please check your email and try again.', 'type' => 'danger'];
        header("Location: view_job_details.php?id=" . $job_id);
        exit();
    }
    
    // --- 3. If OTP is correct, proceed with application ---
    $stmt_student = $conn->prepare("SELECT id FROM students WHERE enrollment_no = ?");
    $stmt_student->bind_param("s", $enrollment_no);
    $stmt_student->execute();
    $student_result = $stmt_student->get_result();
    
    if ($student_result->num_rows > 0) {
        $student_id = $student_result->fetch_assoc()['id'];
        
        $stmt_apply = $conn->prepare("INSERT INTO job_applications (student_id, job_id) VALUES (?, ?)");
        $stmt_apply->bind_param("ii", $student_id, $job_id);
        
        if ($stmt_apply->execute()) {
            $_SESSION['message'] = ['text' => 'Correct OTP! Your application has been submitted successfully!', 'type' => 'success'];
        } else {
            if ($conn->errno == 1062) {
                $_SESSION['message'] = ['text' => 'You have already applied for this job.', 'type' => 'warning'];
            } else {
                $_SESSION['message'] = ['text' => 'An error occurred: ' . $stmt_apply->error, 'type' => 'danger'];
            }
        }
        $stmt_apply->close();
    } else {
        $_SESSION['message'] = ['text' => 'Could not find your student record.', 'type' => 'danger'];
    }
    $stmt_student->close();
    
    header("Location: view_job_details.php?id=" . $job_id);
    exit();

} else {
    header("Location: jobs.php");
    exit();
}
?>