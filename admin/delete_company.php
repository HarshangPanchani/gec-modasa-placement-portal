<?php
// gecm/admin/delete_company.php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { header("Location: manage_companies.php"); exit(); }
$company_id = (int)$_GET['id'];

// Fetch file paths before deleting the DB record
$stmt_files = $conn->prepare("SELECT logo_path, job_description_image_path, job_description_pdf_path FROM companies WHERE id = ?");
$stmt_files->bind_param("i", $company_id);
$stmt_files->execute();
$files = $stmt_files->get_result()->fetch_assoc();
$stmt_files->close();

// Delete the actual files from the server
if ($files) {
    if (!empty($files['logo_path']) && file_exists('../' . $files['logo_path'])) { unlink('../' . $files['logo_path']); }
    if (!empty($files['job_description_image_path']) && file_exists('../' . $files['job_description_image_path'])) { unlink('../' . $files['job_description_image_path']); }
    if (!empty($files['job_description_pdf_path']) && file_exists('../' . $files['job_description_pdf_path'])) { unlink('../' . $files['job_description_pdf_path']); }
}

// Delete the company record
$stmt_delete = $conn->prepare("DELETE FROM companies WHERE id = ?");
$stmt_delete->bind_param("i", $company_id);
if ($stmt_delete->execute()) {
    $_SESSION['message'] = ['text' => 'Company has been successfully deleted.', 'type' => 'success'];
} else {
    $_SESSION['message'] = ['text' => 'Error deleting company: ' . $stmt_delete->error, 'type' => 'danger'];
}
$stmt_delete->close();
$conn->close();

header("Location: manage_companies.php");
exit();