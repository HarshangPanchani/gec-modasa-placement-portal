<?php
// gecm/admin/edit_student.php
session_start();
require_once '../db_connect.php';

// --- SECURITY AND VALIDATION ---
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) {
    $_SESSION['message'] = ['text' => 'No student ID provided.', 'type' => 'danger'];
    header("Location: manage_students.php");
    exit();
}
$student_id = (int)$_GET['id'];

// --- HANDLE THE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Unique field validation...
    $new_email = $_POST['email'];
    $new_enrollment_no = $_POST['enrollment_no'];
    $validation_error = false;
    
    $stmt_check_email = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
    $stmt_check_email->bind_param("si", $new_email, $student_id);
    $stmt_check_email->execute();
    if ($stmt_check_email->get_result()->num_rows > 0) {
        $_SESSION['message'] = ['text' => 'Error: Email already in use by another student.', 'type' => 'danger'];
        $validation_error = true;
    }
    $stmt_check_email->close();

    if (!$validation_error) {
        $stmt_check_enr = $conn->prepare("SELECT id FROM students WHERE enrollment_no = ? AND id != ?");
        $stmt_check_enr->bind_param("si", $new_enrollment_no, $student_id);
        $stmt_check_enr->execute();
        if ($stmt_check_enr->get_result()->num_rows > 0) {
            $_SESSION['message'] = ['text' => 'Error: Enrollment number already in use by another student.', 'type' => 'danger'];
            $validation_error = true;
        }
        $stmt_check_enr->close();
    }
    
    if (!$validation_error) {
        // File replacement function...
        function handle_file_replacement($file_key, $enrollment_no, $upload_subdir, $purpose, $current_path) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                if (!empty($current_path) && file_exists('../' . $current_path)) { unlink('../' . $current_path); }
                $file_tmp_path = $_FILES[$file_key]['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
                $new_file_name = $enrollment_no . '_' . $purpose . '.' . $file_extension;
                $dest_path = 'uploads/' . $upload_subdir . '/' . $new_file_name;
                if (move_uploaded_file($file_tmp_path, '../' . $dest_path)) { return $dest_path; } 
                else { return $current_path; }
            }
            return $current_path;
        }

        $stmt_paths = $conn->prepare("SELECT photo_path, resume_path FROM students WHERE id = ?");
        $stmt_paths->bind_param("i", $student_id);
        $stmt_paths->execute();
        $current_paths = $stmt_paths->get_result()->fetch_assoc();
        $stmt_paths->close();

        $new_photo_path = handle_file_replacement('photo', $new_enrollment_no, 'photos', 'photo', $current_paths['photo_path']);
        $new_resume_path = handle_file_replacement('resume', $new_enrollment_no, 'resumes', 'resume', $current_paths['resume_path']);
        
        // --- COMPLETE UPDATE LOGIC ---
        $sql_parts = ["email = ?", "enrollment_no = ?", "name = ?", "gender = ?", "category = ?", "city = ?", "whatsapp_no = ?", "secondary_no = ?", "photo_path = ?", "resume_path = ?", "ssc_percentage = ?", "hsc_diploma_percentage = ?", "current_cgpa = ?", "total_backlog = ?", "linkedin_url = ?", "github_url = ?", "portfolio_url = ?", "branch = ?", "admission_year = ?", "passout_year = ?", "status = ?"];
        $params = [
            $new_email, $new_enrollment_no, $_POST['name'], $_POST['gender'], $_POST['category'], $_POST['city'], $_POST['whatsapp_no'], 
            !empty($_POST['secondary_no']) ? $_POST['secondary_no'] : null, $new_photo_path, $new_resume_path,
            !empty($_POST['ssc_percentage']) ? $_POST['ssc_percentage'] : null,
            !empty($_POST['hsc_diploma_percentage']) ? $_POST['hsc_diploma_percentage'] : null,
            !empty($_POST['current_cgpa']) ? $_POST['current_cgpa'] : null,
            $_POST['total_backlog'],
            !empty($_POST['linkedin_url']) ? $_POST['linkedin_url'] : null,
            !empty($_POST['github_url']) ? $_POST['github_url'] : null,
            !empty($_POST['portfolio_url']) ? $_POST['portfolio_url'] : null,
            $_POST['branch'], $_POST['admission_year'], $_POST['passout_year'],
            $_POST['status'] // The new status field
        ];
        $types = "ssssssssssdddissssiss"; // Correct types string for all fields

        if (!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?"; $params[] = $hashed_password; $types .= "s";
        }

        $sql = "UPDATE students SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $params[] = $student_id; $types .= "i";
        
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) { $_SESSION['message'] = ['text' => 'Student profile updated successfully!', 'type' => 'success']; } 
        else { $_SESSION['message'] = ['text' => 'Error updating student profile: ' . $stmt_update->error, 'type' => 'danger']; }
        $stmt_update->close();
    }
    header("Location: manage_students.php");
    exit();
}

// --- Fetch student data to pre-fill the complete form ---
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
if (!$student) {
    $_SESSION['message'] = ['text' => 'Student not found.', 'type' => 'danger'];
    header("Location: manage_students.php"); exit();
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Edit Student Profile (Admin)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Editing Profile: <?php echo htmlspecialchars($student['name']); ?></h3>
        <a href="manage_students.php" class="btn btn-secondary">← Back to Student List</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <!-- JUSTIFICATION: This is the COMPLETE form, ensuring no data is lost on submit -->
            <form action="edit_student.php?id=<?php echo $student_id; ?>" method="POST" enctype="multipart/form-data">
                <!-- Personal & Login -->
                <h5>Personal & Login Details</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Enrollment No</label><input type="text" name="enrollment_no" class="form-control" pattern="\d{12}" value="<?php echo htmlspecialchars($student['enrollment_no']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">New Password (Leave blank to keep)</label><input type="password" name="password" class="form-control"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label d-block">Gender</label>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="gender" value="Male" <?php if($student['gender'] == 'Male') echo 'checked'; ?>><label>Male</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="gender" value="Female" <?php if($student['gender'] == 'Female') echo 'checked'; ?>><label>Female</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="gender" value="Other" <?php if($student['gender'] == 'Other') echo 'checked'; ?>><label>Other</label></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select" required>
                            <?php $categories = ['OPEN', 'EWS', 'OBC', 'SC', 'ST', 'SEBC', 'OTHER']; foreach($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php if($student['category'] == $cat) echo 'selected'; ?>><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                     <div class="col-md-4 mb-3"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($student['city']); ?>" required></div>
                     <div class="col-md-4 mb-3"><label class="form-label">WhatsApp No</label><input type="text" name="whatsapp_no" class="form-control" value="<?php echo htmlspecialchars($student['whatsapp_no']); ?>" required></div>
                     <div class="col-md-4 mb-3"><label class="form-label">Secondary No</label><input type="text" name="secondary_no" class="form-control" value="<?php echo htmlspecialchars($student['secondary_no'] ?? ''); ?>"></div>
                </div>
                <hr>
                
                <!-- Academic Details -->
                <h5>Academic Details</h5>
                 <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">Branch</label>
                        <select name="branch" class="form-select" required>
                             <?php $branches = ['Computer Engineering', 'Information Technology', 'Mechanical Engineering', 'Automobile Engineering', 'Civil Engineering', 'Electrical Engineering', 'Electronics & Communication']; foreach($branches as $b): ?>
                                <option value="<?php echo $b; ?>" <?php if($student['branch'] == $b) echo 'selected'; ?>><?php echo $b; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3"><label class="form-label">Admission Year</label><input type="number" name="admission_year" class="form-control" value="<?php echo htmlspecialchars($student['admission_year']); ?>" required></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Passout Year</label><input type="number" name="passout_year" class="form-control" value="<?php echo htmlspecialchars($student['passout_year']); ?>" required></div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3"><label class="form-label">SSC %</label><input type="number" step="0.01" name="ssc_percentage" class="form-control" value="<?php echo htmlspecialchars($student['ssc_percentage']); ?>"></div>
                    <div class="col-md-4 mb-3"><label class="form-label">HSC/Diploma %</label><input type="number" step="0.01" name="hsc_diploma_percentage" class="form-control" value="<?php echo htmlspecialchars($student['hsc_diploma_percentage']); ?>"></div>
                    <div class="col-md-4 mb-3"><label class="form-label">Current CGPA</label><input type="number" step="0.01" name="current_cgpa" class="form-control" value="<?php echo htmlspecialchars($student['current_cgpa']); ?>"></div>
                </div>
                 <div class="mb-3"><label class="form-label">Total Backlogs</label><input type="number" name="total_backlog" class="form-control" value="<?php echo htmlspecialchars($student['total_backlog']); ?>" required></div>
                <hr>

                <!-- Files & Links -->
                <h5>Files & Online Profiles</h5>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Update Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Update Resume</label><input type="file" name="resume" class="form-control" accept=".pdf"></div>
                </div>
                <div class="mb-3"><label class="form-label">LinkedIn URL</label><input type="url" name="linkedin_url" class="form-control" value="<?php echo htmlspecialchars($student['linkedin_url'] ?? ''); ?>"></div>
                <div class="mb-3"><label class="form-label">GitHub URL</label><input type="url" name="github_url" class="form-control" value="<?php echo htmlspecialchars($student['github_url'] ?? ''); ?>"></div>
                <div class="mb-3"><label class="form-label">Portfolio URL</label><input type="url" name="portfolio_url" class="form-control" value="<?php echo htmlspecialchars($student['portfolio_url'] ?? ''); ?>"></div>
                <hr>

                <!-- Account Status -->
                <div class="mb-3">
                    <label class="form-label fw-bold text-danger">Student Account Status</label>
                    <select name="status" class="form-select form-select-lg">
                        <option value="Active" <?php if($student['status'] == 'Active') echo 'selected'; ?>>Active (Eligible for Placements)</option>
                        <option value="Placed" <?php if($student['status'] == 'Placed') echo 'selected'; ?>>Placed</option>
                        <option value="Debarred (Absence)" <?php if($student['status'] == 'Debarred (Absence)') echo 'selected'; ?>>Debarred (Due to Absence)</option>
                        <option value="Debarred (Misconduct)" <?php if($student['status'] == 'Debarred (Misconduct)') echo 'selected'; ?>>Debarred (Due to Misconduct)</option>
                    </select>
                    <div class="form-text text-danger">Warning: Changing this status directly affects the student's access to new jobs. Use with caution.</div>
                </div>
                <hr>

                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>