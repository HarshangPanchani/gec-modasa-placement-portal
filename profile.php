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
// $stmt->close();
// $conn->close();
$action = $_GET['action'] ?? 'display';

// $stmt_student = $conn->prepare("SELECT id, branch, status FROM students WHERE enrollment_no = ?");
// $stmt_student->bind_param("s", $enrollment_no);
// $stmt_student->execute();
// $student_result = $stmt_student->get_result();
// if ($student_result->num_rows === 0) {
//     die("Error: Student record not found.");
// }
// $student = $student_result->fetch_assoc();
$student_id = $student['id'];
$student_branch = $student['branch'];
$student_status = $student['status'];
// $stmt_student->close();
$stmt->close();
// $conn->close();
// --- Step 2: Fetch Student's Personal Placement Analytics ---
$sql_analytics = "
    SELECT
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ?) as total_applied,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND was_present = 'Yes') as total_present,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND was_present = 'No') as total_absent,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND is_selected = 'Yes') as offers_received,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND is_selected = 'No') as not_selected
";
$stmt_analytics = $conn->prepare($sql_analytics);
$stmt_analytics->bind_param("iiiii", $student_id, $student_id, $student_id, $student_id, $student_id);
$stmt_analytics->execute();
$analytics = $stmt_analytics->get_result()->fetch_assoc();
$stmt_analytics->close();


// --- Step 3: Fetch Jobs with the New Smart Logic ---
$jobs = [];
// Query 1: Get ALL jobs the student has ALREADY APPLIED FOR. This is always shown.
$sql_applied = "
    SELECT j.*, ja.id as application_id
    FROM jobs j
    JOIN job_applications ja ON j.id = ja.job_id
    WHERE ja.student_id = ?";
$stmt_applied = $conn->prepare($sql_applied);
$stmt_applied->bind_param("i", $student_id);
$stmt_applied->execute();
$applied_jobs_result = $stmt_applied->get_result();
while ($job = $applied_jobs_result->fetch_assoc()) {
    $jobs[$job['id']] = $job; // Use job ID as key to prevent duplicates
}
$stmt_applied->close();

// Query 2: Get NEW, available jobs ONLY IF the student is 'Active'.
$available_job_count = 0;
if ($student_status === 'Active') {
    // Logic for Branch Mapping
    $search_term = '';
    switch ($student_branch) {
        case 'Computer Engineering': case 'Information Technology': $search_term = 'CE/IT'; break;
        case 'Mechanical Engineering': case 'Automobile Engineering': $search_term = 'Auto/Mech'; break;
        case 'Electronics & Communication': $search_term = 'EC'; break;
        case 'Electrical Engineering': $search_term = 'Elec'; break;
        case 'Civil Engineering': $search_term = 'Civil'; break;
        default: $search_term = 'NO_JOBS_FOUND';
    }
    
    $today = date('Y-m-d');
    // Select all columns for display purposes (e.g., logo, package)
    $sql_available = "
        SELECT j.*, NULL as application_id FROM jobs j 
        WHERE FIND_IN_SET(?, j.departments) > 0 
        AND j.registration_last_date >= ?
    ";
    $stmt_available = $conn->prepare($sql_available);
    $stmt_available->bind_param("ss", $search_term, $today);
    $stmt_available->execute();
    $available_jobs_result = $stmt_available->get_result();
    
    // We get the count BEFORE combining the arrays
    $available_job_count = $available_jobs_result->num_rows;

    while ($job = $available_jobs_result->fetch_assoc()) {
        if (!isset($jobs[$job['id']])) { // Add only if not already in the "applied" list
            $jobs[$job['id']] = $job;
        }
    }
    $stmt_available->close();
}
$conn->close();

// Update the analytics with the correct count of currently available jobs
$analytics['available_jobs'] = $available_job_count;

// Sort the final combined job list by deadline. Active jobs first, then expired ones.
usort($jobs, function($a, $b) {
    $today = date('Y-m-d');
    $a_is_active = $a['registration_last_date'] >= $today;
    $b_is_active = $b['registration_last_date'] >= $today;
    if ($a_is_active != $b_is_active) {
        return $a_is_active ? -1 : 1; // Active jobs come before inactive ones
    }
    // If both are active or both are inactive, sort by registration date (latest first)
    return strtotime($b['registration_last_date']) <=> strtotime($a['registration_last_date']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .profile-pic { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid #dee2e6; }
        .data-label { font-weight: bold; color: #6c757d; }
        .data-value { color: #212529; }
        .analytics-card { border-left: 5px solid; }
        .company-logo-sm { max-height: 30px; max-width: 80px; object-fit: contain; }
    </style>
</head>
<body class="bg-light">

<div class="container py-3 px-2" style="max-width: 1500px; margin: auto;">
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
                                         <option value="" disabled <?= empty(trim($student['branch'])) ? 'selected' : '' ?>>-- Select Branch --</option>
                                        <?php  $branches = ['Computer Engineering', 'Information Technology', 'Mechanical Engineering', 'Civil Engineering', 'Electrical Engineering', 'Electronics & Communication']; ?>
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
                            <!-- <a href="jobs.php" class="btn btn-success">Active Jobs</a> -->
                            <a href="profile.php?action=edit" class="btn btn-primary">Edit Profile</a>
                        </div>
                        <div class="row">
                            <div class="col-md-4 text-center border-end">
                                <?php $photo_path = !empty($student['photo_path']) && file_exists($student['photo_path']) ? $student['photo_path'] : 'https://via.placeholder.com/150'; ?>
                              <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile Picture" class="img-fluid mb-3" style="height: 150px; width: 150px; object-fit: cover; object-position: center;">

                                <h4 class="data-value"><?php echo htmlspecialchars($student['name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($student['branch']); ?></p>
                                <hr>
                                
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
                                <hr class="my-2">
                                <h5 class="mb-2">Academic Details</h5>
                                <div class="row">
                                    <div class="col-sm-6 mb-2"><span class="data-label">SSC %:</span> <span class="data-value"><?php echo htmlspecialchars($student['ssc_percentage'] ?? 'N/A'); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">HSC/Diploma %:</span> <span class="data-value"><?php echo htmlspecialchars($student['hsc_diploma_percentage'] ?? 'N/A'); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Current CGPA:</span> <span class="data-value"><?php echo htmlspecialchars($student['current_cgpa'] ?? 'N/A'); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Backlogs:</span> <span class="data-value"><?php echo htmlspecialchars($student['total_backlog']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Admission:</span> <span class="data-value"><?php echo htmlspecialchars($student['admission_year']); ?></span></div>
                                    <div class="col-sm-6 mb-2"><span class="data-label">Passout:</span> <span class="data-value"><?php echo htmlspecialchars($student['passout_year']); ?></span></div>
                                </div>
                                <!-- <hr class="my-3">
                                <h5 class="mb-3">Professional Links & Documents</h5> -->
                                <hr>
                                <h5 class="mb-1"></h5>
                                <div class="row">
                                <?php if (!empty($student['resume_path']) && file_exists($student['resume_path'])): ?>
                                   <div class="col mb-2">
                                    <span class="data-label">Resume:</span>
                                      <a href="<?php echo htmlspecialchars($student['resume_path']); ?>" target="_blank">
                                       <i class="fas fa-file-pdf" style="color:#D44638; font-size: 1.5rem;"></i> 
                                       </a>
                                      </div>
                                <?php endif; ?>
                               <!-- Font Awesome CDN -->
                               <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
                                    <?php if (!empty($student['linkedin_url'])): ?>
                                          <div class="col mb-2">
                                            <span class="data-label">LinkedIn:</span>
                                                        <a href="<?= htmlspecialchars($student['linkedin_url']) ?>" target="_blank">
                                                          <i class="fab fa-linkedin" style="font-size: 1.5rem; color:#0A66C2;"></i>
                                                        </a>
                                             </div>
                                       <?php endif; ?>
                                 <?php if (!empty($student['github_url'])): ?>
                                  <div class="col mb-2">
                                    <span class="data-label">GitHub:</span>
                                      <a href="<?php echo htmlspecialchars($student['github_url']); ?>" target="_blank">
                                           <i class="fab fa-github" style="font-size: 1.5rem;"></i>
                                        </a>
                                      </div>
                                  <?php endif; ?>
                                    <?php if (!empty($student['portfolio_url'])): ?>
                                        <div class="col mb-2">
                                       <span class="data-label">Portfolio:</span>
                                            <a href="<?php echo htmlspecialchars($student['portfolio_url']); ?>" target="_blank">
                                              <i class="fas fa-globe" style="font-size: 1.5rem; color: #007bff;"></i>
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
<hr>
<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Student Dashboard</h2>
        
    </div>

    <!-- Status Alert Block -->
    <?php if ($student_status !== 'Active'):
        $alert_type = 'warning';
        $alert_heading = '';
        $alert_message = '';
        switch ($student_status) {
            case 'Placed':
                $alert_type = 'success';
                $alert_heading = 'Congratulations on Your Placement!';
                $alert_message = 'As you have been successfully placed, access to new job opportunities has been concluded. You can still view the status of your past applications below.';
                break;
            case 'Debarred (Absence)':
                $alert_type = 'danger';
                $alert_heading = 'Placement Activity Suspended (Attendance)';
                $alert_message = 'Your access to new job opportunities has been suspended due to accumulating 3 or more recorded absences from placement drives.';
                break;
            case 'Debarred (Misconduct)':
                $alert_type = 'danger';
                $alert_heading = 'Placement Activity Suspended (Disciplinary Action)';
                $alert_message = 'Your access to new job opportunities has been suspended due to disciplinary action. Please contact the placement cell for more information.';
                break;
        }
    ?>
        <div class="alert alert-<?php echo $alert_type; ?> text-center mt-4">
            <h4 class="alert-heading"><?php echo $alert_heading; ?></h4>
            <p class="mb-0"><?php echo $alert_message; ?></p>
        </div>
    <?php endif; ?>

    <!-- Placement Analytics Section -->
    <div class="card shadow-sm my-4">
        <div class="card-header"><h5 class="mb-0">Your Placement Record</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col"><div class="card analytics-card border-primary"><div class="card-body text-center"><h5><?php echo $analytics['available_jobs']; ?></h5><small class="text-muted">Available</small></div></div></div>
                <div class="col"><div class="card analytics-card border-info"><div class="card-body text-center"><h5><?php echo $analytics['total_applied']; ?></h5><small class="text-muted">Applied</small></div></div></div>
                <div class="col"><div class="card analytics-card border-secondary"><div class="card-body text-center"><h5><?php echo $analytics['total_present']; ?></h5><small class="text-muted">Present</small></div></div></div>
                <div class="col"><div class="card analytics-card border-warning"><div class="card-body text-center"><h5><?php echo $analytics['total_absent']; ?></h5><small class="text-muted">Absent</small></div></div></div>
                <div class="col"><div class="card analytics-card border-success"><div class="card-body text-center"><h5><?php echo $analytics['offers_received']; ?></h5><small class="text-muted">Offers</small></div></div></div>
                <div class="col"><div class="card analytics-card border-danger"><div class="card-body text-center"><h5><?php echo $analytics['not_selected']; ?></h5><small class="text-muted">Not Selected</small></div></div></div>
            </div>
        </div>
    </div>

    <!-- Job Opportunities Table -->
    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Job Opportunities</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="jobs-table" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Package (LPA)</th>
                            <th>Location</th>
                            <th>Apply Before</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): 
                            $is_expired = $job['registration_last_date'] < date('Y-m-d');
                        ?>
                            <tr class="<?php echo $is_expired ? 'table-secondary opacity-75' : ''; ?>">
                                <td>
                                    <?php 
                                        $logo_path = !empty($job['company_logo_path']) && file_exists($job['company_logo_path']) ? $job['company_logo_path'] : '';
                                    ?>
                                    <?php if($logo_path): ?><img src="<?php echo htmlspecialchars($logo_path); ?>" class="company-logo-sm me-2"><?php endif; ?>
                                    <strong><?php echo htmlspecialchars($job['company_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($job['min_package'] ?? 'N/A') . ' - ' . htmlspecialchars($job['max_package'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td class="<?php echo !$is_expired ? 'text-danger fw-bold' : ''; ?>"><?php echo date('d-M-Y', strtotime($job['registration_last_date'])); ?></td>
                                <td>
                                    <?php if ($job['application_id']): ?>
                                        <span class="badge bg-success p-2"><i class="bi bi-check-circle"></i> Applied</span>
                                    <?php else: // This will only be reached if student is 'Active' and job is available
                                        ?>
                                        <span class="badge bg-primary p-2">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="bi <?php echo $is_expired ? 'bi-clock-history' : 'bi-box-arrow-in-right'; ?>"></i>
                                        <?php echo $is_expired ? ' View History' : ' View & Apply'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($jobs)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No job opportunities found at this time.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // The PHP code already sorts the jobs logically (active first),
    // so we don't need to set a default order here.
    // The user can still click headers to sort differently.
    $('#jobs-table').DataTable();
});
</script>
</body>
</html>