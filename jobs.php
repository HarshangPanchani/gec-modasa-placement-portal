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

// --- Step 1: Fetch the logged-in student's details (ID and branch) ---
$stmt_student = $conn->prepare("SELECT id, branch FROM students WHERE enrollment_no = ?");
$stmt_student->bind_param("s", $enrollment_no);
$stmt_student->execute();
$student_result = $stmt_student->get_result();
if ($student_result->num_rows === 0) {
    die("Error: Could not find student record.");
}
$student = $student_result->fetch_assoc();
$student_id = $student['id'];
$student_branch = $student['branch'];
$stmt_student->close();

// --- Step 2: Fetch Student's Personal Placement Analytics ---
// --- THIS IS THE MODIFIED SQL QUERY ---
$sql_analytics = "
    SELECT
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ?) as total_applied,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND was_present = 'Yes') as total_present,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND was_present = 'No') as total_absent,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND is_selected = 'Yes') as offers_received,
        (SELECT COUNT(*) FROM job_applications WHERE student_id = ? AND is_selected = 'No') as not_selected
";
$stmt_analytics = $conn->prepare($sql_analytics);
// Bind the same student_id for all 5 subqueries
$stmt_analytics->bind_param("iiiii", $student_id, $student_id, $student_id, $student_id, $student_id);
$stmt_analytics->execute();
$analytics = $stmt_analytics->get_result()->fetch_assoc();
$stmt_analytics->close();

// --- Step 3: Fetch Relevant and Active Job Postings ---
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
// New powerful query with a LEFT JOIN to check application status
$sql_jobs = "
    SELECT 
        j.id, j.company_name, j.company_logo_path, j.min_package, j.max_package, j.location, j.registration_last_date,
        ja.id as application_id
    FROM jobs j
    LEFT JOIN job_applications ja ON j.id = ja.job_id AND ja.student_id = ?
    WHERE 
        FIND_IN_SET(?, j.departments) > 0 
        AND j.registration_last_date >= ?
    ORDER BY j.registration_last_date ASC
";

$stmt_jobs = $conn->prepare($sql_jobs);
$stmt_jobs->bind_param("iss", $student_id, $search_term, $today);
$stmt_jobs->execute();
$jobs = $stmt_jobs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_jobs->close();
$conn->close();

$analytics['available_jobs'] = count($jobs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Student Dashboard & Jobs</title>
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
    
    <!-- === UPDATED PLACEMENT ANALYTICS SECTION === -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Your Placement Record</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col"><div class="card analytics-card border-primary"><div class="card-body text-center"><h5><?php echo $analytics['available_jobs']; ?></h5><small class="text-muted">Available</small></div></div></div>
                <div class="col"><div class="card analytics-card border-info"><div class="card-body text-center"><h5><?php echo $analytics['total_applied']; ?></h5><small class="text-muted">Applied</small></div></div></div>
                <div class="col"><div class="card analytics-card border-secondary"><div class="card-body text-center"><h5><?php echo $analytics['total_present']; ?></h5><small class="text-muted">Present</small></div></div></div>
                <div class="col"><div class="card analytics-card border-warning"><div class="card-body text-center"><h5><?php echo $analytics['total_absent']; ?></h5><small class="text-muted">Absent</small></div></div></div>
                <div class="col"><div class="card analytics-card border-success"><div class="card-body text-center"><h5><?php echo $analytics['offers_received']; ?></h5><small class="text-muted">Offers Received</small></div></div></div>
                <div class="col"><div class="card analytics-card border-danger"><div class="card-body text-center"><h5><?php echo $analytics['not_selected']; ?></h5><small class="text-muted">Not Selected</small></div></div></div>
            </div>
        </div>
    </div>

    <!-- === AVAILABLE JOBS TABLE === -->
    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0">Available Job Opportunities for <?php echo htmlspecialchars($student_branch); ?></h5></div>
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
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr>
                                <td>
                                    <?php 
                                        $logo_path = !empty($job['company_logo_path']) && file_exists($job['company_logo_path']) ? $job['company_logo_path'] : '';
                                    ?>
                                    <?php if($logo_path): ?><img src="<?php echo htmlspecialchars($logo_path); ?>" class="company-logo-sm me-2"><?php endif; ?>
                                    <strong><?php echo htmlspecialchars($job['company_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($job['min_package'] ?? 'N/A') . ' - ' . htmlspecialchars($job['max_package'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                                <td class="text-danger"><?php echo date('d-M-Y', strtotime($job['registration_last_date'])); ?></td>
                                <td>
                                    <?php if ($job['application_id']): ?>
                                        <span class="badge bg-success p-2"><i class="bi bi-check-circle"></i> Applied</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary p-2">Available</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view_job_details.php?id=<?php echo $job['id']; ?>" class="btn btn-info btn-sm">View & Apply</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
    $('#jobs-table').DataTable({
        "order": [[ 3, "asc" ]]
    });
});
</script>

</body>
</html>