<?php
// gecm/admin/add_job.php
session_start();
require_once '../db_connect.php';

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- Begin Database Transaction ---
    $conn->begin_transaction();
    try {
        // --- Prepare Job Data ---
        $posting_year = date('Y');
        $company_name = $_POST['company_name'];
        $sanitized_company_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name);
        $departments = isset($_POST['departments']) ? implode(',', $_POST['departments']) : null;
        
        // --- Handle Logo Upload ---
        $logo_path = null;
        if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
            $logo_dir = "../uploads/$posting_year/$sanitized_company_name/logo/";
            if (!is_dir($logo_dir)) mkdir($logo_dir, 0755, true);
            $logo_filename = "logo_" . basename($_FILES['company_logo']['name']);
            $logo_path = $logo_dir . $logo_filename;
            move_uploaded_file($_FILES['company_logo']['tmp_name'], $logo_path);
             // Make path relative to root for db
            $logo_path = str_replace('../', '', $logo_path); 
        }

        // --- Insert Main Job Data ---
        $sql_job = "INSERT INTO jobs (company_name, hr_name, hr_contact, hr_email, website_url, company_logo_path, min_package, max_package, location, departments, registration_last_date, job_description_text, interview_mode, interview_place, posting_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_job = $conn->prepare($sql_job);
        $stmt_job->bind_param("ssssssddssssssi",
            $company_name, $_POST['hr_name'], $_POST['hr_contact'], $_POST['hr_email'],
            $_POST['website_url'], $logo_path, $_POST['min_package'],
            $_POST['max_package'], $_POST['location'], $departments, 
            $_POST['registration_last_date'], $_POST['job_description_text'],
            $_POST['interview_mode'], $_POST['interview_place'], $posting_year
        );
        $stmt_job->execute();
        $job_id = $conn->insert_id; // Get the ID of the job we just created
        $stmt_job->close();

        // --- Handle Multiple Image and PDF Uploads ---
        $file_dir_base = "../uploads/$posting_year/$sanitized_company_name/";
        
        // Handle Images
        if (isset($_FILES['jd_images'])) {
            $images_dir = $file_dir_base . "images/";
            if (!is_dir($images_dir)) mkdir($images_dir, 0755, true);
            $img_count = 1;
            foreach ($_FILES['jd_images']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['jd_images']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_ext = pathinfo($_FILES['jd_images']['name'][$key], PATHINFO_EXTENSION);
                    $file_path = $images_dir . "image_" . $img_count++ . "." . $file_ext;
                    move_uploaded_file($tmp_name, $file_path);
                    $db_path = str_replace('../', '', $file_path);
                    $stmt_file = $conn->prepare("INSERT INTO job_files (job_id, file_type, file_path) VALUES (?, 'image', ?)");
                    $stmt_file->bind_param("is", $job_id, $db_path);
                    $stmt_file->execute();
                    $stmt_file->close();
                }
            }
        }
        
        // Handle PDFs
        if (isset($_FILES['jd_pdfs'])) {
            $pdfs_dir = $file_dir_base . "pdfs/";
            if (!is_dir($pdfs_dir)) mkdir($pdfs_dir, 0755, true);
            $pdf_count = 1;
            foreach ($_FILES['jd_pdfs']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['jd_pdfs']['error'][$key] === UPLOAD_ERR_OK) {
                     $file_ext = pathinfo($_FILES['jd_pdfs']['name'][$key], PATHINFO_EXTENSION);
                    $file_path = $pdfs_dir . "pdf_" . $pdf_count++ . "." . $file_ext;
                    move_uploaded_file($tmp_name, $file_path);
                    $db_path = str_replace('../', '', $file_path);
                    $stmt_file = $conn->prepare("INSERT INTO job_files (job_id, file_type, file_path) VALUES (?, 'pdf', ?)");
                    $stmt_file->bind_param("is", $job_id, $db_path);
                    $stmt_file->execute();
                    $stmt_file->close();
                }
            }
        }
        
        // If everything was successful, commit the transaction
        $conn->commit();
        $_SESSION['message'] = ['text' => 'New job posting created successfully!', 'type' => 'success'];

    } catch (mysqli_sql_exception $exception) {
        // If anything failed, roll back the transaction
        $conn->rollback();
        $_SESSION['message'] = ['text' => 'Failed to create job posting: ' . $exception->getMessage(), 'type' => 'danger'];
    }
    
    header("Location: manage_jobs.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Add New Job</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="card shadow-sm">
        <div class="card-header">
            <h3 class="mb-0">Add New Job Posting</h3>
        </div>
        <div class="card-body p-4">
            <form action="add_job.php" method="post" enctype="multipart/form-data">
                <!-- Company Details -->
                <h5>Company Details</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Company Name *</label><input type="text" name="company_name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Company Logo</label><input type="file" name="company_logo" class="form-control" accept="image/*"></div>
                </div>
                 <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">HR Name *</label><input type="text" name="hr_name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">HR Contact No *</label><input type="text" name="hr_contact" class="form-control" required></div>
                </div>
                <div class="row">
                     <div class="col-md-6 mb-3"><label class="form-label">HR Email Address *</label><input type="email" name="hr_email" class="form-control" required></div>
                     <div class="col-md-6 mb-3"><label class="form-label">Company Website URL</label><input type="url" name="website_url" class="form-control" placeholder="https://example.com"></div>
                </div>
                <hr>
                
                <!-- Job Details -->
                <h5>Job Details</h5>
                <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">Min. Package (LPA)</label><input type="number" name="min_package" step="0.01" class="form-control"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Max. Package (LPA)</label><input type="number" name="max_package" step="0.01" class="form-control"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Job Location</label><input type="text" name="location" class="form-control"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Application Deadline</label><input type="date" name="registration_last_date" class="form-control"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Eligible Departments</label>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="CE/IT"><label class="form-check-label">CE/IT</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="Auto/Mech"><label class="form-check-label">Auto/Mech</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="EC"><label class="form-check-label">EC</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="Elec"><label class="form-check-label">Electrical</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="Civil"><label class="form-check-label">Civil</label></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Job Description Text</label>
                    <textarea name="job_description_text" class="form-control" rows="5"></textarea>
                </div>
                 <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Job Description Images (Multiple)</label><input type="file" name="jd_images[]" class="form-control" accept="image/*" multiple></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Job Description PDFs (Multiple)</label><input type="file" name="jd_pdfs[]" class="form-control" accept=".pdf" multiple></div>
                </div>
                <hr>

                <!-- Interview Details -->
                <h5>Interview Details</h5>
                 <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mode of Interview</label>
                        <select name="interview_mode" class="form-select"><option value="">Choose...</option><option>Online</option><option>Offline</option></select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">If Offline, Place of Interview</label>
                         <input type="text" name="interview_place" class="form-control" placeholder="e.g., At College / At Company Office">
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Create Job Posting</button>
                    <a href="manage_jobs.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>