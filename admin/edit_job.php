<?php
// gecm/admin/edit_job.php
session_start();
require_once '../db_connect.php';

// Auth and ID checks
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { header("Location: manage_jobs.php"); exit(); }
$job_id = (int)$_GET['id'];

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- Begin Database Transaction for Safety ---
    $conn->begin_transaction();
    try {
        // --- Step 1: Handle Deletion of Existing Files ---
        if (!empty($_POST['delete_files'])) {
            $files_to_delete_ids = $_POST['delete_files'];
            $placeholders = implode(',', array_fill(0, count($files_to_delete_ids), '?'));
            $types = str_repeat('i', count($files_to_delete_ids));

            // Get file paths from DB to delete from server
            $stmt_get_paths = $conn->prepare("SELECT file_path FROM job_files WHERE id IN ($placeholders)");
            $stmt_get_paths->bind_param($types, ...$files_to_delete_ids);
            $stmt_get_paths->execute();
            $paths_result = $stmt_get_paths->get_result();
            while($row = $paths_result->fetch_assoc()) {
                if (file_exists('../' . $row['file_path'])) {
                    unlink('../' . $row['file_path']);
                }
            }
            $stmt_get_paths->close();

            // Now delete file records from the database
            $stmt_delete = $conn->prepare("DELETE FROM job_files WHERE id IN ($placeholders)");
            $stmt_delete->bind_param($types, ...$files_to_delete_ids);
            $stmt_delete->execute();
            $stmt_delete->close();
        }

        // --- Step 2: Handle Updates to Main Job and Logo ---
        $company_name = $_POST['company_name'];
        $posting_year = $_POST['posting_year'];
        $sanitized_company_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name);
        $departments = isset($_POST['departments']) ? implode(',', $_POST['departments']) : null;
        
        $current_logo_path = $_POST['current_logo_path'];
        $new_logo_path = $current_logo_path;
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            // Delete old logo if it exists
            if (!empty($current_logo_path) && file_exists('../'.$current_logo_path)) {
                unlink('../'.$current_logo_path);
            }
            $logo_dir = "uploads/$posting_year/$sanitized_company_name/logo/";
            if (!is_dir('../'.$logo_dir)) mkdir('../'.$logo_dir, 0755, true);
            $logo_filename = "logo_" . time() . "_" . basename($_FILES['company_logo']['name']);
            $target_path = '../' . $logo_dir . $logo_filename;
            move_uploaded_file($_FILES['company_logo']['tmp_name'], $target_path);
            $new_logo_path = $logo_dir . $logo_filename;
        }

        $sql_update_job = "UPDATE jobs SET company_name=?, hr_name=?, hr_contact=?, hr_email=?, website_url=?, company_logo_path=?, min_package=?, max_package=?, location=?, departments=?, registration_last_date=?, job_description_text=?, interview_mode=?, interview_place=? WHERE id=?";
        $stmt_update_job = $conn->prepare($sql_update_job);
        $stmt_update_job->bind_param("ssssssddssssssi",
            $company_name, $_POST['hr_name'], $_POST['hr_contact'], $_POST['hr_email'],
            $_POST['website_url'], $new_logo_path, $_POST['min_package'],
            $_POST['max_package'], $_POST['location'], $departments,
            $_POST['registration_last_date'], $_POST['job_description_text'],
            $_POST['interview_mode'], $_POST['interview_place'], $job_id
        );
        $stmt_update_job->execute();
        $stmt_update_job->close();


        // --- Step 3: Handle Addition of NEW Files ---
        $file_dir_base = "../uploads/$posting_year/$sanitized_company_name/";

        // Handle new images
        if (isset($_FILES['new_jd_images'])) {
            $images_dir = $file_dir_base . "images/";
            if (!is_dir($images_dir)) mkdir($images_dir, 0755, true);
            foreach ($_FILES['new_jd_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['new_jd_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_ext = pathinfo($_FILES['new_jd_images']['name'][$key], PATHINFO_EXTENSION);
                    $file_path = $images_dir . "image_" . time() . "_" . $key . "." . $file_ext;
                    move_uploaded_file($tmp_name, $file_path);
                    $db_path = str_replace('../', '', $file_path);
                    $stmt_file = $conn->prepare("INSERT INTO job_files (job_id, file_type, file_path) VALUES (?, 'image', ?)");
                    $stmt_file->bind_param("is", $job_id, $db_path);
                    $stmt_file->execute();
                    $stmt_file->close();
                }
            }
        }
        
        // Handle new PDFs
        if (isset($_FILES['new_jd_pdfs'])) {
            $pdfs_dir = $file_dir_base . "pdfs/";
            if (!is_dir($pdfs_dir)) mkdir($pdfs_dir, 0755, true);
            foreach ($_FILES['new_jd_pdfs']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['new_jd_pdfs']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_ext = pathinfo($_FILES['new_jd_pdfs']['name'][$key], PATHINFO_EXTENSION);
                    $file_path = $pdfs_dir . "pdf_" . time() . "_" . $key . "." . $file_ext;
                    move_uploaded_file($tmp_name, $file_path);
                    $db_path = str_replace('../', '', $file_path);
                    $stmt_file = $conn->prepare("INSERT INTO job_files (job_id, file_type, file_path) VALUES (?, 'pdf', ?)");
                    $stmt_file->bind_param("is", $job_id, $db_path);
                    $stmt_file->execute();
                    $stmt_file->close();
                }
            }
        }

        // If we reach here without errors, commit the transaction
        $conn->commit();
        $_SESSION['message'] = ['text' => 'Job posting updated successfully!', 'type' => 'success'];
    
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback(); // Something went wrong, revert all changes
        $_SESSION['message'] = ['text' => 'Error updating job posting: ' . $exception->getMessage(), 'type' => 'danger'];
    }

    header("Location: manage_jobs.php");
    exit();
}


// --- Fetch existing job data to pre-fill form ---
$stmt_job = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt_job->bind_param("i", $job_id);
$stmt_job->execute();
$result_job = $stmt_job->get_result();
if ($result_job->num_rows === 0) {
    $_SESSION['message'] = ['text' => 'Job posting not found.', 'type' => 'danger'];
    header("Location: manage_jobs.php");
    exit();
}
$job = $result_job->fetch_assoc();
$stmt_job->close();

$stmt_files = $conn->prepare("SELECT id, file_path FROM job_files WHERE job_id = ?");
$stmt_files->bind_param("i", $job_id);
$stmt_files->execute();
$existing_files = $stmt_files->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_files->close();
$conn->close();
$departments_array = !empty($job['departments']) ? explode(',', $job['departments']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Edit Job Posting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
     <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Editing Job: <?php echo htmlspecialchars($job['company_name']); ?></h3>
        <a href="manage_jobs.php" class="btn btn-secondary">← Back to Job List</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="edit_job.php?id=<?php echo $job_id; ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="posting_year" value="<?php echo $job['posting_year']; ?>">
                <input type="hidden" name="current_logo_path" value="<?php echo htmlspecialchars($job['company_logo_path']); ?>">
                
                <h5>Company Details</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Company Name *</label><input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($job['company_name']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Update Company Logo</label><input type="file" name="company_logo" class="form-control" accept="image/*"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">HR Name *</label><input type="text" name="hr_name" class="form-control" value="<?php echo htmlspecialchars($job['hr_name']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">HR Contact No *</label><input type="text" name="hr_contact" class="form-control" value="<?php echo htmlspecialchars($job['hr_contact']); ?>" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">HR Email Address *</label><input type="email" name="hr_email" class="form-control" value="<?php echo htmlspecialchars($job['hr_email']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Company Website URL</label><input type="url" name="website_url" class="form-control" value="<?php echo htmlspecialchars($job['website_url']); ?>"></div>
                </div>
                <hr>

                <h5>Job Details</h5>
                 <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">Min. Package (LPA)</label><input type="number" name="min_package" step="0.01" class="form-control" value="<?php echo htmlspecialchars($job['min_package']); ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Max. Package (LPA)</label><input type="number" name="max_package" step="0.01" class="form-control" value="<?php echo htmlspecialchars($job['max_package']); ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Job Location</label><input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($job['location']); ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Application Deadline</label><input type="date" name="registration_last_date" class="form-control" value="<?php echo htmlspecialchars($job['registration_last_date']); ?>"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Eligible Departments</label>
                     <?php $all_depts = ['CE/IT', 'Auto/Mech', 'EC', 'Elec', 'Civil']; ?>
                    <?php foreach($all_depts as $dept): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="departments[]" value="<?php echo $dept; ?>" <?php if(in_array($dept, $departments_array)) echo 'checked'; ?>>
                            <label class="form-check-label"><?php echo $dept; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Job Description Text</label>
                    <textarea name="job_description_text" class="form-control" rows="5"><?php echo htmlspecialchars($job['job_description_text']); ?></textarea>
                </div>
                <hr>
                
                <h5>Manage & Add Job Files</h5>
                 <?php if(!empty($existing_files)): ?>
                    <div class="mb-3 p-3 border rounded">
                        <label class="form-label fw-bold">Delete Existing Files:</label>
                        <?php foreach($existing_files as $file): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="delete_files[]" value="<?php echo $file['id']; ?>" id="del_<?php echo $file['id']; ?>">
                            <label class="form-check-label" for="del_<?php echo $file['id']; ?>">
                                Delete - <a href="../<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank"><?php echo basename($file['file_path']); ?></a>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                 <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Add New Images</label><input type="file" name="new_jd_images[]" class="form-control" accept="image/*" multiple></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Add New PDFs</label><input type="file" name="new_jd_pdfs[]" class="form-control" accept=".pdf" multiple></div>
                </div>
                <hr>

                <h5>Interview Details</h5>
                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mode of Interview</label>
                        <select name="interview_mode" class="form-select">
                            <option value="Online" <?php if($job['interview_mode'] == 'Online') echo 'selected'; ?>>Online</option>
                            <option value="Offline" <?php if($job['interview_mode'] == 'Offline') echo 'selected'; ?>>Offline</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">If Offline, Place</label>
                         <input type="text" name="interview_place" class="form-control" value="<?php echo htmlspecialchars($job['interview_place']); ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Save Changes to Job Posting</button>
                    <a href="manage_jobs.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>