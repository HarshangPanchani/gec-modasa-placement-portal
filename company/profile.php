<?php
// gecm/company/profile.php
session_start();
require_once '../db_connect.php'; // Note the '..' to go up one level

if (!isset($_SESSION['company_email'])) {
    header("Location: login.php");
    exit();
}

$current_company_email = $_SESSION['company_email'];
$action = $_GET['action'] ?? 'display';

// --- HANDLE PROFILE UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Validation for unique HR Email ---
    $new_hr_email = $_POST['hr_email'];
    $validation_error = false;
    if ($new_hr_email !== $current_company_email) {
        $stmt_check = $conn->prepare("SELECT id FROM companies WHERE hr_email = ?");
        $stmt_check->bind_param("s", $new_hr_email);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $_SESSION['message'] = ['text' => 'Error: This HR Email address is already in use by another company.', 'type' => 'danger'];
            $validation_error = true;
        }
        $stmt_check->close();
    }
    
    if (!$validation_error) {
        // --- File Replacement Function ---
        function handleCompanyFileReplacement($file_key, $upload_subdir, $company_name, $current_path) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                if (!empty($current_path) && file_exists($current_path)) {
                    unlink($current_path); // Delete the old file
                }
                
                $uploadDir = '../uploads/';
                $targetDir = $uploadDir . $upload_subdir . '/';
                if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
                
                $file = $_FILES[$file_key];
                $sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name);
                $fileName = $sanitized_name . '_' . time() . '_' . basename($file['name']);
                $targetPath = $targetDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    return $targetPath; // Return NEW path
                }
            }
            return $current_path; // Return OLD path if no upload
        }

        // --- Fetch current paths for deletion logic ---
        $stmt_paths = $conn->prepare("SELECT logo_path, job_description_image_path, job_description_pdf_path FROM companies WHERE hr_email = ?");
        $stmt_paths->bind_param("s", $current_company_email);
        $stmt_paths->execute();
        $current_paths = $stmt_paths->get_result()->fetch_assoc();
        $stmt_paths->close();
        
        // --- Handle File Replacements ---
        $logo_path = handleCompanyFileReplacement('logo', 'company_logos', $_POST['company_name'], $current_paths['logo_path']);
        $jd_image_path = handleCompanyFileReplacement('jd_image', 'job_files', $_POST['company_name'], $current_paths['job_description_image_path']);
        $jd_pdf_path = handleCompanyFileReplacement('jd_pdf', 'job_files', $_POST['company_name'], $current_paths['job_description_pdf_path']);

        // --- Prepare data for update ---
        $departments = isset($_POST['departments']) ? implode(',', $_POST['departments']) : null;

        $sql_parts = [
            "company_name = ?", "hr_name = ?", "hr_contact = ?", "hr_email = ?", "website_url = ?",
            "logo_path = ?", "min_package = ?", "max_package = ?", "location = ?", "departments = ?",
            "registration_last_date = ?", "job_description_text = ?", "job_description_image_path = ?",
            "job_description_pdf_path = ?", "interview_mode = ?", "interview_place = ?"
        ];

        $params = [
            $_POST['company_name'], $_POST['hr_name'], $_POST['hr_contact'], $new_hr_email, $_POST['website_url'],
            $logo_path, $_POST['min_package'], $_POST['max_package'], $_POST['location'], $departments,
            $_POST['registration_last_date'], $_POST['job_description_text'], $jd_image_path,
            $jd_pdf_path, $_POST['interview_mode'], $_POST['interview_place']
        ];
        $types = "ssssssddssssssss";

        if (!empty($_POST['password'])) {
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?";
            $params[] = $hashedPassword;
            $types .= "s";
        }
        
        $sql = "UPDATE companies SET " . implode(", ", $sql_parts) . " WHERE hr_email = ?";
        $params[] = $current_company_email;
        $types .= "s";
        
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) {
            $_SESSION['company_email'] = $new_hr_email; // Update session if email changed
            $_SESSION['message'] = ['text' => 'Profile updated successfully!', 'type' => 'success'];
        } else {
            $_SESSION['message'] = ['text' => 'Error updating profile: ' . $stmt_update->error, 'type' => 'danger'];
        }
        $stmt_update->close();
    }

    header("Location: profile.php");
    exit();
}

// --- FETCH COMPANY DATA ---
$stmt = $conn->prepare("SELECT * FROM companies WHERE hr_email = ?");
$stmt->bind_param("s", $current_company_email);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    session_destroy();
    die("Company not found. Please <a href='login.php'>login</a> again.");
}
$company = $result->fetch_assoc();
$stmt->close();
$conn->close();

$departments_array = !empty($company['departments']) ? explode(',', $company['departments']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Company Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-logo { max-height: 120px; max-width: 200px; object-fit: contain; }
        .data-label { font-weight: bold; color: #6c757d; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Company Profile</h4>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        </div>
        <div class="card-body p-4">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?>"><?php echo $_SESSION['message']['text']; ?></div>
            <?php unset($_SESSION['message']); endif; ?>

            <?php if ($action === 'edit'): ?>
                <!-- ================== EDIT MODE ================== -->
                <h3 class="mb-4">Edit Company & Job Details</h3>
                <form action="profile.php" method="POST" enctype="multipart/form-data">
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
                    <a href="profile.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                </form>

            <?php else: ?>
                <!-- ================== DISPLAY MODE ================== -->
                <div class="text-end mb-3"><a href="profile.php?action=edit" class="btn btn-primary">Edit Profile</a></div>
                <div class="row">
                    <div class="col-md-4 text-center border-end">
                        <img src="<?php echo !empty($company['logo_path']) ? htmlspecialchars($company['logo_path']) : '../uploads/placeholder.png'; ?>" class="img-fluid rounded mb-3 profile-logo">
                        <h5><?php echo htmlspecialchars($company['company_name']); ?></h5>
                        <?php if(!empty($company['website_url'])): ?><p><a href="<?php echo htmlspecialchars($company['website_url']); ?>" target="_blank">Visit Website</a></p><?php endif; ?>
                    </div>
                    <div class="col-md-8 ps-4">
                        <h5>HR Contact</h5>
                        <p><span class="data-label">Name:</span> <?php echo htmlspecialchars($company['hr_name']); ?></p>
                        <p><span class="data-label">Email:</span> <?php echo htmlspecialchars($company['hr_email']); ?></p>
                        <p><span class="data-label">Contact:</span> <?php echo htmlspecialchars($company['hr_contact']); ?></p>
                        <hr>
                        <h5>Job Opportunity</h5>
                        <p><span class="data-label">Package Range (LPA):</span> <?php echo htmlspecialchars($company['min_package'] ?? 'N/A') . ' - ' . htmlspecialchars($company['max_package'] ?? 'N/A'); ?></p>
                        <p><span class="data-label">Location:</span> <?php echo htmlspecialchars($company['location'] ?? 'N/A'); ?></p>
                        <p><span class="data-label">Last Date to Apply:</span> <?php echo htmlspecialchars($company['registration_last_date'] ?? 'N/A'); ?></p>
                        <p><span class="data-label">Departments:</span> <?php echo !empty($departments_array) ? implode(', ', $departments_array) : 'N/A'; ?></p>
                        <hr>
                        <h5>Job Description</h5>
                        <p><?php echo !empty($company['job_description_text']) ? nl2br(htmlspecialchars($company['job_description_text'])) : 'N/A'; ?></p>
                        <?php if(!empty($company['job_description_image_path'])): ?><p><a href="<?php echo htmlspecialchars($company['job_description_image_path']); ?>" target="_blank">View JD Image</a></p><?php endif; ?>
                        <?php if(!empty($company['job_description_pdf_path'])): ?><p><a href="<?php echo htmlspecialchars($company['job_description_pdf_path']); ?>" target="_blank">View JD PDF</a></p><?php endif; ?>
                         <hr>
                        <h5>Interview Process</h5>
                        <p><span class="data-label">Mode:</span> <?php echo htmlspecialchars($company['interview_mode'] ?? 'N/A'); ?></p>
                        <?php if($company['interview_mode'] == 'Offline'): ?><p><span class="data-label">Place:</span> <?php echo htmlspecialchars($company['interview_place'] ?? 'N/A'); ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>