<?php
// profile.php
session_start();
require_once 'db_connect.php';

// --- 1. AUTHENTICATION CHECK ---
if (!isset($_SESSION['enrollment_no'])) {
    header("Location: login.php");
    exit();
}
$current_enrollment_no = $_SESSION['enrollment_no'];

// --- 2. HANDLE PROFILE UPDATE (FORM SUBMISSION) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_email = $_POST['email'];
    $new_enrollment_no = $_POST['enrollment_no'];
    $validation_error = false;
    $stmt = $conn->prepare("SELECT enrollment_no FROM students WHERE email = ? AND enrollment_no != ?");
    $stmt->bind_param("ss", $new_email, $current_enrollment_no);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['message'] = ['text' => 'Error: This email address is already in use by another student.', 'type' => 'danger'];
        $validation_error = true;
    }
    $stmt->close();
    if (!$validation_error) {
        $stmt = $conn->prepare("SELECT enrollment_no FROM students WHERE enrollment_no = ? AND enrollment_no != ?");
        $stmt->bind_param("ss", $new_enrollment_no, $current_enrollment_no);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['message'] = ['text' => 'Error: This enrollment number is already in use by another student.', 'type' => 'danger'];
            $validation_error = true;
        }
        $stmt->close();
    }
    if (!$validation_error) {
        function handle_file_replacement($file_key, $enrollment_no, $upload_subdir, $purpose, $current_path) {
             if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                if (!empty($current_path) && file_exists($current_path)) { unlink($current_path); }
                $file_tmp_path = $_FILES[$file_key]['tmp_name'];
                $file_extension = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
                $new_file_name = $enrollment_no . '_' . $purpose . '.' . $file_extension;
                $dest_path = 'uploads/' . $upload_subdir . '/' . $new_file_name;
                if (move_uploaded_file($file_tmp_path, $dest_path)) { return $dest_path; } else { return $current_path; }
            }
            return $current_path;
        }
        $stmt_get_paths = $conn->prepare("SELECT photo_path, resume_path FROM students WHERE enrollment_no = ?");
        $stmt_get_paths->bind_param("s", $current_enrollment_no);
        $stmt_get_paths->execute();
        $current_student_data = $stmt_get_paths->get_result()->fetch_assoc();
        $stmt_get_paths->close();
        $new_photo_path = handle_file_replacement('photo', $new_enrollment_no, 'photos', 'photo', $current_student_data['photo_path']);
        $new_resume_path = handle_file_replacement('resume', $new_enrollment_no, 'resumes', 'resume', $current_student_data['resume_path']);
        $sql_parts = ["email = ?", "enrollment_no = ?", "name = ?", "gender = ?", "category = ?", "city = ?", "whatsapp_no = ?", "secondary_no = ?", "photo_path = ?", "resume_path = ?", "ssc_percentage = ?", "hsc_diploma_percentage = ?", "current_cgpa = ?", "total_backlog = ?", "linkedin_url = ?", "github_url = ?", "portfolio_url = ?", "branch = ?", "admission_year = ?", "passout_year = ?"];
        $params = [$new_email, $new_enrollment_no, $_POST['name'] ?? '', $_POST['gender'] ?? '', $_POST['category'] ?? '', $_POST['city'] ?? '', $_POST['whatsapp_no'] ?? '', !empty($_POST['secondary_no']) ? $_POST['secondary_no'] : null, $new_photo_path, $new_resume_path, !empty($_POST['ssc_percentage']) ? $_POST['ssc_percentage'] : null, !empty($_POST['hsc_diploma_percentage']) ? $_POST['hsc_diploma_percentage'] : null, !empty($_POST['current_cgpa']) ? $_POST['current_cgpa'] : null, $_POST['total_backlog'] ?? 0, !empty($_POST['linkedin_url']) ? $_POST['linkedin_url'] : null, !empty($_POST['github_url']) ? $_POST['github_url'] : null, !empty($_POST['portfolio_url']) ? $_POST['portfolio_url'] : null, $_POST['branch'] ?? '', $_POST['admission_year'] ?? '', $_POST['passout_year'] ?? ''];
        $types = "ssssssssssdddissssii";
        if (!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
        $sql = "UPDATE students SET " . implode(", ", $sql_parts) . " WHERE enrollment_no = ?";
        $params[] = $current_enrollment_no;
        $types .= "s";
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param($types, ...$params);
        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['text' => 'Profile updated successfully!', 'type' => 'success'];
            $_SESSION['enrollment_no'] = $new_enrollment_no;
        } else {
            $_SESSION['message'] = ['text' => 'Error updating profile: ' . $stmt_update->error, 'type' => 'danger'];
        }
        $stmt_update->close();
    }
    header("Location: profile.php");
    exit();
}

// --- 3. FETCH CURRENT STUDENT DATA TO DISPLAY ---
$stmt = $conn->prepare("SELECT * FROM students WHERE enrollment_no = ?");
$stmt->bind_param("s", $current_enrollment_no);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    session_destroy();
    die("Student not found. Please <a href='login.php'>login</a> again.");
}
$student = $result->fetch_assoc();
$stmt->close();
$conn->close();
$action = $_GET['action'] ?? 'display';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-pic { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid #dee2e6; }
        .data-label { font-weight: bold; color: #6c757d; }
        .data-value { color: #212529; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Student Profile</h4>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message']['type']; ?>" role="alert">
                            <?php echo $_SESSION['message']['text']; ?>
                        </div>
                    <?php unset($_SESSION['message']); endif; ?>
                    
                    <?php if ($action === 'edit'): ?>
                        <!-- ================== COMPLETE EDIT MODE (RESTORED) ================== -->
                        <h3 class="mb-4">Edit Your Details</h3>
                        <form action="profile.php" method="POST" enctype="multipart/form-data">
                             <!-- Personal & Login Details -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                </div>
                            </div>
                            <div class="row">
                                 <div class="col-md-6 mb-3">
                                    <label class="form-label">Enrollment No (12 digits)</label>
                                    <input type="text" name="enrollment_no" class="form-control" pattern="\d{12}" title="Must be 12 digits" value="<?php echo htmlspecialchars($student['enrollment_no']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password (Leave blank to keep current)</label>
                                    <input type="password" name="password" class="form-control" placeholder="Enter new password only if changing">
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Other Details -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label d-block">Gender</label>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gender" value="Male" <?php if($student['gender'] == 'Male') echo 'checked'; ?>> Male
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gender" value="Female" <?php if($student['gender'] == 'Female') echo 'checked'; ?>> Female
                                    </div>
                                     <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gender" value="Other" <?php if($student['gender'] == 'Other') echo 'checked'; ?>> Other
                                    </div>
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
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($student['city']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">WhatsApp No</label>
                                    <input type="text" name="whatsapp_no" class="form-control" value="<?php echo htmlspecialchars($student['whatsapp_no']); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Secondary No</label>
                                    <input type="text" name="secondary_no" class="form-control" value="<?php echo htmlspecialchars($student['secondary_no'] ?? ''); ?>">
                                </div>
                            </div>

                            <hr>
                            
                            <!-- Academic Details -->
                             <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Branch</label>
                                    <select name="branch" class="form-select" required>
                                        <?php $branches = ['Computer Engineering', 'Information Technology', 'Mechanical Engineering', 'Civil Engineering', 'Electrical Engineering', 'Electronics & Communication']; ?>
                                        <?php foreach($branches as $b): ?>
                                            <option value="<?php echo $b; ?>" <?php if($student['branch'] == $b) echo 'selected'; ?>><?php echo $b; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Total Backlogs</label>
                                    <input type="number" name="total_backlog" class="form-control" value="<?php echo htmlspecialchars($student['total_backlog']); ?>" required min="0">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">SSC %</label>
                                    <input type="number" step="0.01" name="ssc_percentage" class="form-control" value="<?php echo htmlspecialchars($student['ssc_percentage']); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">HSC/Diploma %</label>
                                    <input type="number" step="0.01" name="hsc_diploma_percentage" class="form-control" value="<?php echo htmlspecialchars($student['hsc_diploma_percentage']); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Current CGPA</label>
                                    <input type="number" step="0.01" name="current_cgpa" class="form-control" value="<?php echo htmlspecialchars($student['current_cgpa']); ?>">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Admission Year</label>
                                    <input type="number" name="admission_year" class="form-control" value="<?php echo htmlspecialchars($student['admission_year']); ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Passout Year</label>
                                    <input type="number" name="passout_year" class="form-control" value="<?php echo htmlspecialchars($student['passout_year']); ?>" required>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- Files & Links -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Update Photo</label>
                                    <input type="file" name="photo" class="form-control" accept="image/*">
                                    <?php if (!empty($student['photo_path']) && file_exists($student['photo_path'])): ?><small class="text-muted">Current photo exists. Uploading will replace it.</small><?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Update Resume (PDF)</label>
                                    <input type="file" name="resume" class="form-control" accept=".pdf">
                                    <?php if (!empty($student['resume_path']) && file_exists($student['resume_path'])): ?><small class="text-muted">Current resume exists. Uploading will replace it.</small><?php endif; ?>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">LinkedIn URL</label>
                                <input type="url" name="linkedin_url" class="form-control" value="<?php echo htmlspecialchars($student['linkedin_url'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">GitHub URL</label>
                                <input type="url" name="github_url" class="form-control" value="<?php echo htmlspecialchars($student['github_url'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Portfolio URL</label>
                                <input type="url" name="portfolio_url" class="form-control" value="<?php echo htmlspecialchars($student['portfolio_url'] ?? ''); ?>">
                            </div>
                            <hr>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="profile.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    
                    <?php else: ?>
                        <!-- ================== DETAILED DISPLAY MODE ================== -->
                        <!-- This is the display part you liked, no changes here -->
                        <div class="text-end mb-3">
                            <a href="jobs.php" class="btn btn-success">Active Jobs</a>
                            <a href="profile.php?action=edit" class="btn btn-primary">Edit Profile</a>
                        </div>
                        <div class="row">
                            <div class="col-md-4 text-center border-end">
                                <?php $photo_path = !empty($student['photo_path']) && file_exists($student['photo_path']) ? $student['photo_path'] : 'https://via.placeholder.com/150'; ?>
                                <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile Picture" class="profile-pic img-fluid mb-3">
                                <h4 class="data-value"><?php echo htmlspecialchars($student['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($student['branch']); ?></p>
                            </div>
                            <div class="col-md-8 ps-4">
                                <h5 class="mb-3">Personal & Contact Information</h5>
                                <div class="row">
                                    <div class="col-sm-6 mb-2"><span class="data-label">Enrollment No:</span> <span class="data-value"><?php echo htmlspecialchars($student['enrollment_no']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Email:</span> <span class="data-value"><?php echo htmlspecialchars($student['email']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Gender:</span> <span class="data-value"><?php echo htmlspecialchars($student['gender']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Category:</span> <span class="data-value"><?php echo htmlspecialchars($student['category']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">City:</span> <span class="data-value"><?php echo htmlspecialchars($student['city']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">WhatsApp:</span> <span class="data-value"><?php echo htmlspecialchars($student['whatsapp_no']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Secondary No:</span> <span class="data-value"><?php echo htmlspecialchars($student['secondary_no'] ?? 'N/A'); ?></span></div>
                                </div>
                                <hr class="my-3">
                                <h5 class="mb-3">Academic Details</h5>
                                <div class="row">
                                    <div class="col-sm-4 mb-2"><span class="data-label">SSC %:</span> <span class="data-value"><?php echo htmlspecialchars($student['ssc_percentage'] ?? 'N/A'); ?></span></div>
                                    <div class="col-sm-4 mb-2"><span class="data-label">HSC/Diploma %:</span> <span class="data-value"><?php echo htmlspecialchars($student['hsc_diploma_percentage'] ?? 'N/A'); ?></span></div>
                                    <div class="col-sm-4 mb-2"><span class="data-label">Current CGPA:</span> <span class="data-value"><?php echo htmlspecialchars($student['current_cgpa'] ?? 'N/A'); ?></span></div>
                                    <div class="col-sm-4 mb-2"><span class="data-label">Backlogs:</span> <span class="data-value"><?php echo htmlspecialchars($student['total_backlog']); ?></span></div>
                                    <div class="col-sm-4 mb-2"><span class="data-label">Admission:</span> <span class="data-value"><?php echo htmlspecialchars($student['admission_year']); ?></span></div>
                                    <div class="col-sm-4 mb-2"><span class="data-label">Passout:</span> <span class="data-value"><?php echo htmlspecialchars($student['passout_year']); ?></span></div>
                                </div>
                                <hr class="my-3">
                                <h5 class="mb-3">Professional Links & Documents</h5>
                                <div class="row">
                            

                                     <?php if (!empty($student['resume_path']) && file_exists($student['resume_path'])): ?>
                                         <div class="col-12 mb-2">
                                            <span class="data-label">Resume:</span>
                                                <a href="<?php echo htmlspecialchars($student['resume_path']); ?>" target="_blank">View/Download Resume</a>
                                         </div>
                                     <?php endif; ?>

                                    <?php if (!empty($student['linkedin_url'])): ?>
                                         <div class="col-12 mb-2">
                                              <span class="data-label">LinkedIn:</span>
                                                <a href="<?php echo htmlspecialchars($student['linkedin_url']); ?>" target="_blank">
                                     <?php echo htmlspecialchars($student['linkedin_url']); ?>
                                                </a>
                                          </div>
                                           <?php endif; ?>

                                    <?php if (!empty($student['github_url'])): ?>
                                         <div class="col-12 mb-2">
                                            <span class="data-label">GitHub:</span>
                                               <a href="<?php echo htmlspecialchars($student['github_url']); ?>" target="_blank">
                                     <?php echo htmlspecialchars($student['github_url']); ?>
                                               </a>
                                            </div>
                                     <?php endif; ?>

                                     <?php if (!empty($student['portfolio_url'])): ?>
                                          <div class="col-12 mb-2">
                                            <span class="data-label">Portfolio:</span>
                                                  <a href="<?php echo htmlspecialchars($student['portfolio_url']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($student['portfolio_url']); ?>
                                             </a>
                                              </div>
                                         <?php endif; ?>
                                         </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>