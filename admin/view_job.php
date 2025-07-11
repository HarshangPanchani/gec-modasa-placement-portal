<?php
// gecm/admin/view_job.php
session_start();
require_once '../db_connect.php';

// Auth check
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { header("Location: manage_jobs.php"); exit(); }
$job_id = (int)$_GET['id'];

// --- Fetch main job data ---
$stmt_job = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt_job->bind_param("i", $job_id);
$stmt_job->execute();
$result_job = $stmt_job->get_result();
if ($result_job->num_rows === 0) {
    $_SESSION['message'] = ['text' => 'Job posting not found.', 'type' => 'danger'];
    header("Location: manage_jobs.php");
    exit();
}
$job = $result_job->fetch_assoc();
$stmt_job->close();

// --- Fetch associated files ---
$stmt_files = $conn->prepare("SELECT * FROM job_files WHERE job_id = ?");
$stmt_files->bind_param("i", $job_id);
$stmt_files->execute();
$files_result = $stmt_files->get_result();
$job_images = [];
$job_pdfs = [];
while($file = $files_result->fetch_assoc()) {
    if ($file['file_type'] === 'image') {
        $job_images[] = $file;
    } else {
        $job_pdfs[] = $file;
    }
}
$stmt_files->close();
$conn->close();

$departments_array = !empty($job['departments']) ? explode(',', $job['departments']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>View Job Posting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>.data-label { font-weight: bold; color: #6c757d; }</style>
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Job Posting Details</h3>
        <a href="manage_jobs.php" class="btn btn-secondary">← Back to Job List</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-4 text-center border-end">
                    <?php 
                        $logo_path_check = '../' . $job['company_logo_path'];
                        $logo_display_path = (!empty($job['company_logo_path']) && file_exists($logo_path_check)) 
                                           ? htmlspecialchars($logo_path_check)
                                           : 'https://via.placeholder.com/200x120?text=No+Logo';
                    ?>
                    <img src="<?php echo $logo_display_path; ?>" class="img-fluid rounded mb-3" style="max-width: 200px;">
                    <h5><?php echo htmlspecialchars($job['company_name']); ?></h5>
                    <?php if(!empty($job['website_url'])): ?><p><a href="<?php echo htmlspecialchars($job['website_url']); ?>" target="_blank">Visit Website</a></p><?php endif; ?>
                </div>
                <div class="col-md-8 ps-4">
                    <h5>HR Contact</h5>
                    <p><span class="data-label">Name:</span> <?php echo htmlspecialchars($job['hr_name']); ?></p>
                    <p><span class="data-label">Email:</span> <?php echo htmlspecialchars($job['hr_email']); ?></p>
                    <p><span class="data-label">Contact:</span> <?php echo htmlspecialchars($job['hr_contact']); ?></p>
                    <hr>
                    <h5>Job Opportunity</h5>
                    <p><span class="data-label">Package (LPA):</span> <?php echo htmlspecialchars($job['min_package'] ?? 'N/A') . ' - ' . htmlspecialchars($job['max_package'] ?? 'N/A'); ?></p>
                    <p><span class="data-label">Location:</span> <?php echo htmlspecialchars($job['location'] ?? 'N/A'); ?></p>
                    <p><span class="data-label">Application Deadline:</span> <?php echo htmlspecialchars($job['registration_last_date'] ?? 'N/A'); ?></p>
                    <p><span class="data-label">Eligible Departments:</span> <?php echo !empty($departments_array) ? implode(', ', $departments_array) : 'N/A'; ?></p>
                    <hr>
                    <h5>Job Description</h5>
                    <div class="mb-3">
                        <h6>Description Text:</h6>
                        <p><?php echo !empty($job['job_description_text']) ? nl2br(htmlspecialchars($job['job_description_text'])) : 'N/A'; ?></p>
                    </div>
                    <?php if (!empty($job_images)): ?>
                        <div class="mb-3">
                            <h6>Description Images:</h6>
                            <?php foreach($job_images as $image): ?>
                                <a href="../<?php echo htmlspecialchars($image['file_path']); ?>" target="_blank" class="me-2">Image <?php echo basename($image['file_path']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                     <?php if (!empty($job_pdfs)): ?>
                        <div class="mb-3">
                            <h6>Description PDFs:</h6>
                            <?php foreach($job_pdfs as $pdf): ?>
                                <a href="../<?php echo htmlspecialchars($pdf['file_path']); ?>" target="_blank" class="me-2">PDF <?php echo basename($pdf['file_path']); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                     <hr>
                    <h5>Interview Process</h5>
                    <p><span class="data-label">Mode:</span> <?php echo htmlspecialchars($job['interview_mode'] ?? 'N/A'); ?></p>
                    <?php if($job['interview_mode'] == 'Offline'): ?><p><span class="data-label">Place:</span> <?php echo htmlspecialchars($job['interview_place'] ?? 'N/A'); ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>