<?php
// gecm/admin/view_company.php
session_start();
require_once '../db_connect.php';

// Auth check
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['id'])) { 
    $_SESSION['message'] = ['text' => 'No Company ID provided.', 'type' => 'danger'];
    header("Location: manage_companies.php"); exit(); 
}
$company_id = (int)$_GET['id'];

// Fetch company data
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
$stmt->close();
$conn->close();

$departments_array = !empty($company['departments']) ? explode(',', $company['departments']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>View Company Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-logo { max-height: 120px; max-width: 200px; object-fit: contain; }
        .data-label { font-weight: bold; color: #6c757d; }
    </style>
</head>
<body class="bg-light">
<div class="container mt-5 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Company Full Profile</h3>
        <a href="manage_companies.php" class="btn btn-secondary">← Back to Company List</a>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="row">
                <div class="col-md-4 text-center border-end">
                    <?php 
                        // --- CORRECTED FILE PATH LOGIC ---
                        $logo_path =  $company['logo_path']; // Prepend ../
                        if (empty($company['logo_path']) || !file_exists($logo_path)) {
                            $logo_display_path = 'https://via.placeholder.com/200x120?text=No+Logo';
                        } else {
                            $logo_display_path = htmlspecialchars($logo_path);
                        }
                    ?>
                    <img src="<?php echo $logo_display_path; ?>" class="img-fluid rounded mb-3 profile-logo">
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
                    <p><span class="data-label">Package (LPA):</span> <?php echo htmlspecialchars($company['min_package'] ?? 'N/A') . ' - ' . htmlspecialchars($company['max_package'] ?? 'N/A'); ?></p>
                    <p><span class="data-label">Location:</span> <?php echo htmlspecialchars($company['location'] ?? 'N/A'); ?></p>
                    <p><span class="data-label">Last Date to Apply:</span> <?php echo htmlspecialchars($company['registration_last_date'] ?? 'N/A'); ?></p>
                    <p><span class="data-label">Departments:</span> <?php echo !empty($departments_array) ? implode(', ', $departments_array) : 'N/A'; ?></p>
                    <hr>
                    <h5>Job Description</h5>
                    <p><?php echo !empty($company['job_description_text']) ? nl2br(htmlspecialchars($company['job_description_text'])) : 'N/A'; ?></p>
                    
                    <?php 
                        // --- CORRECTED JD IMAGE LOGIC ---
                        $jd_image_path_check = $company['job_description_image_path'];
                        if(!empty($company['job_description_image_path']) && file_exists($jd_image_path_check)): ?>
                            <p><span class="data-label">JD Image:</span> <a href="<?php echo htmlspecialchars($jd_image_path_check); ?>" target="_blank">View JD Image</a></p>
                    <?php endif; ?>

                    <?php 
                        // --- CORRECTED JD PDF LOGIC ---
                        $jd_pdf_path_check =  $company['job_description_pdf_path'];
                        if(!empty($company['job_description_pdf_path']) && file_exists($jd_pdf_path_check)): ?>
                            <p><span class="data-label">JD PDF:</span> <a href="<?php echo htmlspecialchars($jd_pdf_path_check); ?>" target="_blank">View JD PDF</a></p>
                    <?php endif; ?>
                    
                     <hr>
                    <h5>Interview Process</h5>
                    <p><span class="data-label">Mode:</span> <?php echo htmlspecialchars($company['interview_mode'] ?? 'N/A'); ?></p>
                    <?php if($company['interview_mode'] == 'Offline'): ?><p><span class="data-label">Place:</span> <?php echo htmlspecialchars($company['interview_place'] ?? 'N/A'); ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>