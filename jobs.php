<?php
// gecm/jobs.php
session_start();
require_once 'db_connect.php';

// --- Auth Check: Must be a logged-in student ---
if (!isset($_SESSION['enrollment_no'])) {
    header("Location: login.php");
    exit();
}
$enrollment_no = $_SESSION['enrollment_no'];

// --- Step 1: Fetch student details including the NEW `status` column ---
$stmt_student = $conn->prepare("SELECT id, branch, status FROM students WHERE enrollment_no = ?");
$stmt_student->bind_param("s", $enrollment_no);
$stmt_student->execute();
$student_result = $stmt_student->get_result();
if ($student_result->num_rows === 0) {
    die("Error: Student record not found.");
}
$student = $student_result->fetch_assoc();
$student_id = $student['id'];
$student_branch = $student['branch'];
$student_status = $student['status'];
$stmt_student->close();

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
    <title>Student Dashboard & Jobs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .analytics-card { border-left: 5px solid; }
        .company-logo-sm { max-height: 30px; max-width: 80px; object-fit: contain; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Student Dashboard</h2>
        <div>
            <a href="profile.php" class="btn btn-secondary">My Profile</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
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