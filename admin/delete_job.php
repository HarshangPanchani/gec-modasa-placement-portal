<?php
// gecm/admin/delete_job.php
session_start();
require_once '../db_connect.php';

// Security and validation checks
if (!isset($_SESSION['admin_id'])) { 
    header("Location: login.php"); 
    exit(); 
}
if (!isset($_GET['id'])) { 
    $_SESSION['message'] = ['text' => 'No Job ID provided for deletion.', 'type' => 'danger'];
    header("Location: manage_jobs.php"); 
    exit(); 
}
$job_id = (int)$_GET['id'];

$conn->begin_transaction();
try {
    // --- Step 1: Get All Necessary Information Before Deleting ---
    
    // Get the job's main details to find the folder paths
    $stmt_job = $conn->prepare("SELECT company_name, posting_year, company_logo_path FROM jobs WHERE id = ?");
    $stmt_job->bind_param("i", $job_id);
    $stmt_job->execute();
    $job = $stmt_job->get_result()->fetch_assoc();
    $stmt_job->close();

    if (!$job) {
        throw new Exception("Job posting not found.");
    }
    
    // Get all associated image/pdf paths from the `job_files` table
    $stmt_files = $conn->prepare("SELECT file_path FROM job_files WHERE job_id = ?");
    $stmt_files->bind_param("i", $job_id);
    $stmt_files->execute();
    $files_result = $stmt_files->get_result();
    $all_file_paths = [];
    while ($file = $files_result->fetch_assoc()) {
        $all_file_paths[] = $file['file_path'];
    }
    // Add the logo path to the list of files to be deleted
    if (!empty($job['company_logo_path'])) {
        $all_file_paths[] = $job['company_logo_path'];
    }
    $stmt_files->close();


    // --- Step 2: Delete All Associated Files from the Server ---
    foreach ($all_file_paths as $relative_path) {
        $full_path = '../' . $relative_path;
        if (!empty($relative_path) && file_exists($full_path)) {
            unlink($full_path);
        }
    }

    // --- Step 3: Delete the Job Record from the Database ---
    // The `ON DELETE CASCADE` will handle deleting records from `job_files`
    $stmt_delete = $conn->prepare("DELETE FROM jobs WHERE id = ?");
    $stmt_delete->bind_param("i", $job_id);
    if (!$stmt_delete->execute()) {
        throw new Exception($stmt_delete->error);
    }
    $stmt_delete->close();


    // --- Step 4: Clean Up Empty Directories ---
    $sanitized_company_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $job['company_name']);
    $main_company_dir = '../uploads/' . $job['posting_year'] . '/' . $sanitized_company_name;

    function is_dir_empty($dir) {
        if (!is_readable($dir)) return NULL; 
        return (count(scandir($dir)) == 2);
    }

    // Check and remove subdirectories
    $sub_dirs = ['logo', 'images', 'pdfs'];
    foreach ($sub_dirs as $sub) {
        $dir_path = $main_company_dir . '/' . $sub;
        if (is_dir($dir_path) && is_dir_empty($dir_path)) {
            rmdir($dir_path);
        }
    }

    // Finally, check and remove the main company directory if it's now empty
    if (is_dir($main_company_dir) && is_dir_empty($main_company_dir)) {
        rmdir($main_company_dir);
    }

    // If we reach here, everything was successful
    $conn->commit();
    $_SESSION['message'] = ['text' => 'Job posting and all associated files/folders have been successfully deleted.', 'type' => 'success'];

} catch (Exception $e) {
    // If any step failed, revert all database changes
    $conn->rollback(); 
    $_SESSION['message'] = ['text' => 'Error during deletion: ' . $e->getMessage(), 'type' => 'danger'];
}

$conn->close();
header("Location: manage_jobs.php");
exit();