<?php
// gecm/view_job_details.php
session_start();
require_once 'db_connect.php';

// Auth Check & Get Job ID
if (!isset($_SESSION['enrollment_no'])) {
    header("Location: login.php");
    exit();
}
if (!isset($_GET['id'])) {
    header("Location: jobs.php");
    exit();
}
$job_id = (int)$_GET['id'];
$enrollment_no = $_SESSION['enrollment_no'];

// Check if the current student has already applied for this job
$stmt_check_app = $conn->prepare("SELECT ja.id FROM job_applications ja JOIN students s ON ja.student_id = s.id WHERE s.enrollment_no = ? AND ja.job_id = ?");
// Corrected line with "si"
$stmt_check_app->bind_param("si", $enrollment_no, $job_id);
$stmt_check_app->execute();
$has_applied = ($stmt_check_app->get_result()->num_rows > 0);
$stmt_check_app->close();

// Fetch Job and File Data
$stmt_job = $conn->prepare("SELECT * FROM jobs WHERE id = ? AND registration_last_date >= CURDATE()");
$stmt_job->bind_param("i", $job_id);
$stmt_job->execute();
$result_job = $stmt_job->get_result();
if ($result_job->num_rows === 0) {
    die("This job posting is either not available or the application deadline has passed. <a href='jobs.php'>Click here to return to job listings.</a>");
}
$job = $result_job->fetch_assoc();
$stmt_job->close();

$stmt_files = $conn->prepare("SELECT file_path, file_type FROM job_files WHERE job_id = ?");
$stmt_files->bind_param("i", $job_id);
$stmt_files->execute();
$files_result = $stmt_files->get_result();
$job_images = [];
$job_pdfs = [];
while($file = $files_result->fetch_assoc()) {
    ($file['file_type'] === 'image') ? $job_images[] = $file : $job_pdfs[] = $file;
}
$stmt_files->close();
$conn->close();

$departments_array = !empty($job['departments']) ? explode(',', $job['departments']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title><?php echo htmlspecialchars($job['company_name']); ?> - Job Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>.data-label { font-weight: bold; color: #6c757d; }</style>
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Job Details</h2>
        <a href="jobs.php" class="btn btn-secondary">← Back to Opportunities</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message']); endif; ?>
    
    <div class="card shadow-sm">
        <div class="card-header"><h4 class="mb-0"><?php echo htmlspecialchars($job['company_name']); ?></h4></div>
        <div class="card-body p-4">

            <!-- ======================= FULL JOB DETAILS DISPLAY ======================= -->
            <div class="row">
                <div class="col-md-4 text-center border-end">
                    <?php 
                        $logo_display_path = (!empty($job['company_logo_path']) && file_exists($job['company_logo_path']))
                                           ? htmlspecialchars($job['company_logo_path'])
                                           : 'https://via.placeholder.com/200x120?text=Logo';
                    ?>
                    <img src="<?php echo $logo_display_path; ?>" class="img-fluid rounded mb-3" style="max-width: 200px;">
                    <h5><?php echo htmlspecialchars($job['company_name']); ?></h5>
                    <?php if(!empty($job['website_url'])): ?><p><a href="<?php echo htmlspecialchars($job['website_url']); ?>" target="_blank">Visit Company Website</a></p><?php endif; ?>
                </div>
                <div class="col-md-8 ps-4">
                     <p><span class="data-label">Eligible Departments:</span> <?php echo implode(', ', $departments_array); ?></p>
                     <p><span class="data-label">Location:</span> <?php echo htmlspecialchars($job['location'] ?? 'N/A'); ?></p>
                     <p><span class="data-label">Package (LPA):</span> <?php echo htmlspecialchars($job['min_package'] ?? 'N/A') . ' - ' . htmlspecialchars($job['max_package'] ?? 'N/A'); ?></p>
                     <p class="text-danger"><span class="data-label">Application Deadline:</span> <?php echo date('d F, Y', strtotime($job['registration_last_date'])); ?></p>
                     <hr>
                     <h5>Full Job Description</h5>
                     <p><?php echo nl2br(htmlspecialchars($job['job_description_text'])); ?></p>
                     
                     <?php if (!empty($job_images) || !empty($job_pdfs)): ?>
                        <h6>Attachments:</h6>
                        <?php foreach($job_images as $image): ?>
                            <a href="<?php echo htmlspecialchars($image['file_path']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm mb-2">View Image: <?php echo basename($image['file_path']); ?></a><br>
                        <?php endforeach; ?>
                        <?php foreach($job_pdfs as $pdf): ?>
                            <a href="<?php echo htmlspecialchars($pdf['file_path']); ?>" target="_blank" class="btn btn-outline-danger btn-sm mb-2">Download PDF: <?php echo basename($pdf['file_path']); ?></a><br>
                        <?php endforeach; ?>
                     <?php endif; ?>
                </div>
            </div>
            <!-- ======================= END OF JOB DETAILS DISPLAY ======================= -->
            
            <hr>
            
            <!-- ======================= APPLY/OTP FORM ======================= -->
            <div class="mt-4 text-center">
                <?php if ($has_applied): ?>
                    <button class="btn btn-success btn-lg" disabled>
                        <i class="bi bi-check-circle-fill"></i> You have already applied
                    </button>
                    <p class="text-muted mt-2">Your application has been recorded. Good luck!</p>
                <?php else: ?>
                    <form action="apply_to_job.php" method="POST" class="row g-3 justify-content-center align-items-center" onsubmit="return confirm('Confirm your application?');">
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                        <div class="col-md-4">
                            <label for="otp" class="form-label"><b>Enter OTP from Email</b></label>
                            <input type="text" class="form-control form-control-lg" name="otp" id="otp" placeholder="5-Digit Code" required>
                        </div>
                        <div class="col-md-3">
                             <button type="submit" class="btn btn-primary btn-lg w-100" style="margin-top:2rem;">Apply Now</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <!-- ======================= END OF APPLY/OTP FORM ======================= -->
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>