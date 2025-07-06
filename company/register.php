<?php
// gecm/company/register.php
session_start();
require_once '../db_connect.php'; // Note the '..' to go up one level

$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    function handleCompanyUpload($file_key, $upload_subdir, $company_id_placeholder) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
            $uploadDir = '../uploads/'; // Note the '..'
            $targetDir = $uploadDir . $upload_subdir . '/';
            if (!is_dir($targetDir)) { mkdir($targetDir, 0755, true); }
            
            $file = $_FILES[$file_key];
            $fileName = $company_id_placeholder . '_' . basename($file['name']);
            $targetPath = $targetDir . $fileName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                return $targetPath;
            }
        }
        return null;
    }

    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $departments = isset($_POST['departments']) ? implode(',', $_POST['departments']) : null;
    
    // For filenames, use a sanitized company name as a placeholder before we get the real ID
    $company_id_placeholder = preg_replace('/[^a-zA-Z0-9_-]/', '_', $_POST['company_name']);

    $logo_path = handleCompanyUpload('logo', 'company_logos', $company_id_placeholder);
    $jd_image_path = handleCompanyUpload('jd_image', 'job_files', $company_id_placeholder);
    $jd_pdf_path = handleCompanyUpload('jd_pdf', 'job_files', $company_id_placeholder);

    $sql = "INSERT INTO companies (company_name, hr_name, hr_contact, hr_email, password, website_url, logo_path, min_package, max_package, location, departments, registration_last_date, job_description_text, job_description_image_path, job_description_pdf_path, interview_mode, interview_place) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);

    $stmt->bind_param("sssssssdsssssssss",
        $_POST['company_name'], $_POST['hr_name'], $_POST['hr_contact'], $_POST['hr_email'],
        $hashedPassword, $_POST['website_url'], $logo_path,
        $_POST['min_package'], $_POST['max_package'], $_POST['location'],
        $departments, $_POST['registration_last_date'], $_POST['job_description_text'],
        $jd_image_path, $jd_pdf_path, $_POST['interview_mode'], $_POST['interview_place']
    );

    if ($stmt->execute()) {
        $_SESSION['company_email'] = $_POST['hr_email'];
        header("Location: profile.php");
        exit();
    } else {
        if ($conn->errno == 1062) {
            $errorMessage = "Error: A company with this HR Email Address already exists.";
        } else {
            $errorMessage = "Error: " . $stmt->error;
        }
    }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Company Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="card shadow-sm">
        <div class="card-body p-5">
            <h2 class="text-center mb-4">Company Registration</h2>
            <?php if(!empty($errorMessage)): ?>
                <div class="alert alert-danger"><?php echo $errorMessage; ?></div>
            <?php endif; ?>

            <form action="register.php" method="post" enctype="multipart/form-data">
                <!-- Company and HR Details -->
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Company Name *</label><input type="text" name="company_name" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Company Website URL</label><input type="url" name="website_url" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">HR Name *</label><input type="text" name="hr_name" class="form-control" required></div>
                    <div class="col-md-4 mb-3"><label class="form-label">HR Email (for login) *</label><input type="email" name="hr_email" class="form-control" required></div>
                    <div class="col-md-4 mb-3"><label class="form-label">HR Contact No *</label><input type="text" name="hr_contact" class="form-control" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Company Logo</label><input type="file" name="logo" class="form-control" accept="image/*"></div>
                </div>
                <hr>
                <!-- Job Details -->
                <h4 class="mb-3">Job & Placement Details</h4>
                <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">Min. Package (LPA)</label><input type="number" name="min_package" step="0.01" class="form-control"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Max. Package (LPA)</label><input type="number" name="max_package" step="0.01" class="form-control"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Job Location</label><input type="text" name="location" class="form-control"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Last Date for Registration</label><input type="date" name="registration_last_date" class="form-control"></div>
                </div>
                 <div class="mb-3">
                    <label class="form-label d-block">Required Departments</label>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="CE/IT"><label class="form-check-label">CE/IT</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="Auto/Mech"><label class="form-check-label">Auto/Mech</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="EC"><label class="form-check-label">EC</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="Elec"><label class="form-check-label">Electrical</label></div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="departments[]" value="Civil"><label class="form-check-label">Civil</label></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Job Description</label>
                    <textarea name="job_description_text" class="form-control" rows="4"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Upload Image for JD (Optional)</label><input type="file" name="jd_image" class="form-control" accept="image/*"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Upload PDF for JD (Optional)</label><input type="file" name="jd_pdf" class="form-control" accept=".pdf"></div>
                </div>
                <hr>
                <!-- Interview Details -->
                <h4 class="mb-3">Interview Details</h4>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Mode of Interview</label>
                        <select name="interview_mode" class="form-select">
                            <option selected value="">Choose...</option><option>Online</option><option>Offline</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">If Offline, Place of Interview</label>
                         <input type="text" name="interview_place" class="form-control" placeholder="e.g., At College / At Company Office">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg mt-4">Register Company</button>
                <p class="text-center mt-3">Already registered? <a href="login.php">Login Here</a></p>
            </form>
        </div>
    </div>
</div>
</body>
</html>