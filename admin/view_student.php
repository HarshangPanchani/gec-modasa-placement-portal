<?php
// gecm/admin/view_student.php
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

// Fetch the student data
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
    <title>View Student Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-pic { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 3px solid #dee2e6; }
        .data-label { font-weight: bold; color: #6c757d; }
        .data-value { color: #212529; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Student Full Profile</h3>
        <a href="manage_students.php" class="btn btn-secondary">← Back to Student List</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-3 text-center border-end">
                    <?php 
                        $photo_path = !empty($student['photo_path']) && file_exists('../' . $student['photo_path']) 
                                      ? '../' . $student['photo_path'] 
                                      : 'https://via.placeholder.com/150';
                    ?>
                    <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Profile Picture" class="profile-pic img-fluid mb-3">
                    <h4 class="data-value"><?php echo htmlspecialchars($student['name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($student['branch']); ?></p>
                </div>

                <div class="col-md-9 ps-4">
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
                         <div class="col-12 mb-2">
                            <span class="data-label">Resume:</span> 
                            <?php if (!empty($student['resume_path']) && file_exists('../' . $student['resume_path'])): ?>
                                <a href="<?php echo '../' . htmlspecialchars($student['resume_path']); ?>" target="_blank">View/Download Resume</a>
                            <?php else: echo '<span class="data-value">Not Uploaded</span>'; endif; ?>
                        </div>
                        <div class="col-12 mb-2">
                            <span class="data-label">LinkedIn:</span> 
                            <?php if (!empty($student['linkedin_url'])): ?>
                                <a href="<?php echo htmlspecialchars($student['linkedin_url']); ?>" target="_blank"><?php echo htmlspecialchars($student['linkedin_url']); ?></a>
                            <?php else: echo '<span class="data-value">N/A</span>'; endif; ?>
                        </div>
                        <div class="col-12 mb-2">
                            <span class="data-label">GitHub:</span> 
                            <?php if (!empty($student['github_url'])): ?>
                                <a href="<?php echo htmlspecialchars($student['github_url']); ?>" target="_blank"><?php echo htmlspecialchars($student['github_url']); ?></a>
                            <?php else: echo '<span class="data-value">N/A</span>'; endif; ?>
                        </div>
                        <div class="col-12 mb-2">
                            <span class="data-label">Portfolio:</span> 
                            <?php if (!empty($student['portfolio_url'])): ?>
                                <a href="<?php echo htmlspecialchars($student['portfolio_url']); ?>" target="_blank"><?php echo htmlspecialchars($student['portfolio_url']); ?></a>
                            <?php else: echo '<span class="data-value">N/A</span>'; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>