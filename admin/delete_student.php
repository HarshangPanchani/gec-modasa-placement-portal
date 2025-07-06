<?php
// gecm/admin/delete_student.php
session_start();
require_once '../db_connect.php';

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['message'] = ['text' => 'No student ID provided for deletion.', 'type' => 'danger'];
    header("Location: manage_students.php");
    exit();
}

$student_id = (int)$_GET['id'];

// --- Fetch file paths before deleting the record ---
$stmt_files = $conn->prepare("SELECT photo_path, resume_path FROM students WHERE id = ?");
$stmt_files->bind_param("i", $student_id);
$stmt_files->execute();
$result_files = $stmt_files->get_result();
$files = $result_files->fetch_assoc();
$stmt_files->close();

// --- Delete the actual files from the server ---
if ($files) {
    if (!empty($files['photo_path']) && file_exists('../' . $files['photo_path'])) {
        unlink('../' . $files['photo_path']);
    }
    if (!empty($files['resume_path']) && file_exists('../' . $files['resume_path'])) {
        unlink('../' . $files['resume_path']);
    }
}

// --- Delete the student record from the database ---
$stmt_delete = $conn->prepare("DELETE FROM students WHERE id = ?");
$stmt_delete->bind_param("i", $student_id);

if ($stmt_delete->execute()) {
    $_SESSION['message'] = ['text' => 'Student record has been successfully deleted.', 'type' => 'success'];
} else {
    $_SESSION['message'] = ['text' => 'Error deleting student record: ' . $stmt_delete->error, 'type' => 'danger'];
}

$stmt_delete->close();
$conn->close();

header("Location: manage_students.php");
exit();