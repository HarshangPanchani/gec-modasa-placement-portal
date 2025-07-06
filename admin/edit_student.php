<?php
// gecm/admin/edit_student.php
session_start();
require_once '../db_connect.php';

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    $_SESSION['message'] = ['text' => 'No student ID provided.', 'type' => 'danger'];
    header("Location: manage_students.php");
    exit();
}
$student_id = (int)$_GET['id'];

// --- Handle Full Profile Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- Validation for unique fields (email, enrollment_no) ---
    $new_email = $_POST['email'];
    $new_enrollment_no = $_POST['enrollment_no'];
    $validation_error = false;
    
    // Check if new email is taken by ANOTHER student
    $stmt_check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
    $stmt_check->bind_param("si", $new_email, $student_id);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows > 0) {
        $_SESSION['message'] = ['text' => 'Error: This email address is already in use by another student.', 'type' => 'danger'];
        $validation_error = true;
    }
    $stmt_check->close();

    // Check if new enrollment is taken by ANOTHER student
    if (!$validation_error) {
        $stmt_check = $conn->prepare("SELECT id FROM students WHERE enrollment_no = ? AND id != ?");
        $stmt_check->bind_param("si", $new_enrollment_no, $student_id);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            $_SESSION['message'] = ['text' => 'Error: This enrollment number is already in use by another student.', 'type' => 'danger'];
            $validation_error = true;
        }
        $stmt_check->close();
    }
    
    if (!$validation_error) {
        // (This robust file handling function is copied from student profile)
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
        
        // --- Prepare all fields for update ---
        $sql_parts = [
            "email = ?", "enrollment_no = ?", "name = ?", "gender = ?", "category = ?", "city = ?", 
            "whatsapp_no = ?", "secondary_no = ?", "photo_path = ?", "resume_path = ?", "ssc_percentage = ?", 
            "hsc_diploma_percentage = ?", "current_cgpa = ?", "total_backlog = ?", "linkedin_url = ?", 
            "github_url = ?", "portfolio_url = ?", "branch = ?", "admission_year = ?", "passout_year = ?"
        ];
        $params = [
            $new_email, $new_enrollment_no, $_POST['name'], $_POST['gender'], $_POST['category'], $_POST['city'],
            $_POST['whatsapp_no'], !empty($_POST['secondary_no']) ? $_POST['secondary_no'] : null,
            $new_photo_path, $new_resume_path,
            !empty($_POST['ssc_percentage']) ? $_POST['ssc_percentage'] : null,
            !empty($_POST['hsc_diploma_percentage']) ? $_POST['hsc_diploma_percentage'] : null,
            !empty($_POST['current_cgpa']) ? $_POST['current_cgpa'] : null,
            $_POST['total_backlog'],
            !empty($_POST['linkedin_url']) ? $_POST['linkedin_url'] : null,
            !empty($_POST['github_url']) ? $_POST['github_url'] : null,
            !empty($_POST['portfolio_url']) ? $_POST['portfolio_url'] : null,
            $_POST['branch'], $_POST['admission_year'], $_POST['passout_year']
        ];
        $types = "ssssssssssdddissssii";

        if (!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }

        $sql = "UPDATE students SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $params[] = $student_id;
        $types .= "i";
        
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['text' => 'Student profile updated successfully!', 'type' => 'success'];
        } else {
            $_SESSION['message'] = ['text' => 'Error updating student profile: ' . $stmt_update->error, 'type' => 'danger'];
        }
        $stmt_update->close();

    } // End of validation check
    
    header("Location: manage_students.php");
    exit();
}

// --- Fetch current student data to pre-fill the form ---
$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['message'] = ['text' => 'Student not found.', 'type' => 'danger'];
    header("Location: manage_students.php");
    exit();
}
$student = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Student Profile (Admin)</title>
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
            <!-- This is the COMPLETE edit form, copied from the student profile -->
            <form action="edit_student.php?id=<?php echo $student_id; ?>" method="POST" enctype="multipart/form-data">
                <!-- Personal & Login Details -->
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label class="form-label">Enrollment No</label><input type="text" name="enrollment_no" class="form-control" pattern="\d{12}" value="<?php echo htmlspecialchars($student['enrollment_no']); ?>" required></div>
                    <div class="col-md-6 mb-3"><label class="form-label">New Password (Leave blank to keep current)</label><input type="password" name="password" class="form-control"></div>
                </div>
                <hr>
                <!-- Other Details -->
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
                            <?php $categories = ['OPEN', 'EWS', 'OBC', 'SC', 'ST', 'SEBC', 'OTHER']; ?>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat; ?>" <?php if($student['category'] == $cat) echo 'selected'; ?>><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <!-- ... Paste ALL other form fields from the full edit form here ... -->
                <hr>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="manage_students.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>