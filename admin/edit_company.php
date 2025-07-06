<?php
// gecm/admin/edit_company.php
session_start();
require_once '../db_connect.php';

// Auth and ID checks
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { header("Location: manage_companies.php"); exit(); }
$company_id = (int)$_GET['id'];

// --- HANDLE FULL PROFILE UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Fetch original email to compare
    $stmt_old_email = $conn->prepare("SELECT hr_email FROM companies WHERE id = ?");
    $stmt_old_email->bind_param("i", $company_id);
    $stmt_old_email->execute();
    $old_company_data = $stmt_old_email->get_result()->fetch_assoc();
    $current_company_email = $old_company_data['hr_email'];
    $stmt_old_email->close();

    $new_hr_email = $_POST['hr_email'];
    $validation_error = false;

    // Validate if new email is taken by ANOTHER company
    if ($new_hr_email !== $current_company_email) {
        $stmt_check = $conn->prepare("SELECT id FROM companies WHERE hr_email = ?");
        $stmt_check->bind_param("s", $new_hr_email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $_SESSION['message'] = ['text' => 'Error: This HR Email is already used by another company.', 'type' => 'danger'];
            $validation_error = true;
        }
        $stmt_check->close();
    }
    
    if (!$validation_error) {
        function handleCompanyFileReplacement($file_key, $upload_subdir, $company_name, $current_path) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                if (!empty($current_path) && file_exists('../' . $current_path)) { unlink('../' . $current_path); }
                $uploadDir = 'uploads/';
                $targetDir = $uploadDir . $upload_subdir . '/';
                if (!is_dir('../' . $targetDir)) { mkdir('../' . $targetDir, 0755, true); }
                $file = $_FILES[$file_key];
                $sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name);
                $fileName = $sanitized_name . '_' . time() . '_' . basename($file['name']);
                $targetPath = $targetDir . $fileName;
                if (move_uploaded_file($file['tmp_name'], '../' . $targetPath)) { return $targetPath; }
            }
            return $current_path;
        }

        $stmt_paths = $conn->prepare("SELECT logo_path, job_description_image_path, job_description_pdf_path FROM companies WHERE id = ?");
        $stmt_paths->bind_param("i", $company_id);
        $stmt_paths->execute();
        $current_paths = $stmt_paths->get_result()->fetch_assoc();
        $stmt_paths->close();
        
        $logo_path = handleCompanyFileReplacement('logo', 'company_logos', $_POST['company_name'], $current_paths['logo_path']);
        $jd_image_path = handleCompanyFileReplacement('jd_image', 'job_files', $_POST['company_name'], $current_paths['job_description_image_path']);
        $jd_pdf_path = handleCompanyFileReplacement('jd_pdf', 'job_files', $_POST['company_name'], $current_paths['job_description_pdf_path']);

        $departments = isset($_POST['departments']) ? implode(',', $_POST['departments']) : null;

        $sql_parts = ["company_name = ?", "hr_name = ?", "hr_contact = ?", "hr_email = ?", "website_url = ?", "logo_path = ?", "min_package = ?", "max_package = ?", "location = ?", "departments = ?", "registration_last_date = ?", "job_description_text = ?", "job_description_image_path = ?", "job_description_pdf_path = ?", "interview_mode = ?", "interview_place = ?"];
        $params = [$_POST['company_name'], $_POST['hr_name'], $_POST['hr_contact'], $new_hr_email, $_POST['website_url'], $logo_path, $_POST['min_package'], $_POST['max_package'], $_POST['location'], $departments, $_POST['registration_last_date'], $_POST['job_description_text'], $jd_image_path, $jd_pdf_path, $_POST['interview_mode'], $_POST['interview_place']];
        $types = "ssssssddssssssss";

        if (!empty($_POST['password'])) {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?";
            $params[] = $hashedPassword;
            $types .= "s";
        }
        
        $sql = "UPDATE companies SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $params[] = $company_id;
        $types .= "i";
        
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['text' => 'Company profile updated successfully!', 'type' => 'success'];
        } else {
            $_SESSION['message'] = ['text' => 'Error updating profile: ' . $stmt_update->error, 'type' => 'danger'];
        }
        $stmt_update->close();
    }
    
    header("Location: manage_companies.php");
    exit();
}

// --- Fetch data to pre-fill form ---
$stmt = $conn->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['message'] = ['text' => 'Company not found.', 'type' => 'danger'];
    header("Location: manage_companies.php");
    exit();
}
$company = $result->fetch_assoc();
$departments_array = !empty($company['departments']) ? explode(',', $company['departments']) : [];
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Edit Company</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Editing Company: <?php echo htmlspecialchars($company['company_name']); ?></h3>
        <a href="manage_companies.php" class="btn btn-secondary">← Back to Company List</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <form action="edit_company.php?id=<?php echo $company_id; ?>" method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Company Name *</label><input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($company['company_name']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Company Website URL</label><input type="url" name="website_url" class="form-control" value="<?php echo htmlspecialchars($company['website_url']); ?>"></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">HR Name *</label><input type="text" name="hr_name" class="form-control" value="<?php echo htmlspecialchars($company['hr_name']); ?>" required></div>
                    <div class="col-md-4 mb-3"><label class="form-label">HR Email (for login) *</label><input type="email" name="hr_email" class="form-control" value="<?php echo htmlspecialchars($company['hr_email']); ?>" required></div>
                    <div class="col-md-4 mb-3"><label class="form-label">HR Contact No *</label><input type="text" name="hr_contact" class="form-control" value="<?php echo htmlspecialchars($company['hr_contact']); ?>" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">New Password (Leave blank to keep current)</label><input type="password" name="password" class="form-control"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Update Company Logo</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
                </div>
                <hr>
                <h4 class="mb-3">Job & Placement Details</h4>
                <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">Min. Package (LPA)</label><input type="number" name="min_package" step="0.01" class="form-control" value="<?php echo htmlspecialchars($company['min_package']); ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Max. Package (LPA)</label><input type="number" name="max_package" step="0.01" class="form-control" value="<?php echo htmlspecialchars($company['max_package']); ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Job Location</label><input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($company['location']); ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Last Date for Registration</label><input type="date" name="registration_last_date" class="form-control" value="<?php echo htmlspecialchars($company['registration_last_date']); ?>"></div>
                </div>
                 <div class="mb-3">
                    <label class="form-label d-block">Required Departments</label>
                    <?php $all_depts = ['CE/IT', 'Auto/Mech', 'EC', 'Elec', 'Civil']; ?>
                    <?php foreach($all_depts as $dept): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" name="departments[]" value="<?php echo $dept; ?>" <?php if(in_array($dept, $departments_array)) echo 'checked'; ?>>
                            <label class="form-check-label"><?php echo $dept; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Job Description</label>
                    <textarea name="job_description_text" class="form-control" rows="4"><?php echo htmlspecialchars($company['job_description_text']); ?></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Update JD Image</label><input type="file" name="jd_image" class="form-control" accept="image/*"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Update JD PDF</label><input type="file" name="jd_pdf" class="form-control" accept=".pdf"></div>
                </div>
                <hr>
                <h4 class="mb-3">Interview Details</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mode of Interview</label>
                        <select name="interview_mode" class="form-select">
                            <option value="">Choose...</option>
                            <option value="Online" <?php if($company['interview_mode'] == 'Online') echo 'selected'; ?>>Online</option>
                            <option value="Offline" <?php if($company['interview_mode'] == 'Offline') echo 'selected'; ?>>Offline</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">If Offline, Place of Interview</label>
                         <input type="text" name="interview_place" class="form-control" placeholder="e.g., At College / At Company Office" value="<?php echo htmlspecialchars($company['interview_place']); ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                <a href="manage_companies.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>