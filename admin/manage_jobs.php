<?php
// gecm/admin/manage_jobs.php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

// Fetch all jobs
$sql_jobs = "SELECT id, company_name, hr_name, departments, registration_last_date FROM jobs ORDER BY created_at DESC";
$jobs = $conn->query($sql_jobs)->fetch_all(MYSQLI_ASSOC);

// Fetch all unique passout years from the students table for the modal
$sql_years = "SELECT DISTINCT passout_year FROM students ORDER BY passout_year DESC";
$passout_years = $conn->query($sql_years)->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Manage Job Postings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Manage Job Postings</h3>
        <div>
            <a href="add_job.php" class="btn btn-success">＋ Add New Job</a>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message']); endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Departments</th>
                            <th>Application Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($job['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($job['departments']); ?></td>
                            <td><?php echo htmlspecialchars($job['registration_last_date']); ?></td>
                            <td class="text-nowrap">
                                <a href="view_job_applicants.php?job_id=<?php echo $job['id']; ?>" class="btn btn-sm btn-success">Applicants</a>
                                <button type="button" class="btn btn-sm btn-warning notify-btn" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#notifyModal"
                                        data-job-id="<?php echo $job['id']; ?>"
                                        data-company-name="<?php echo htmlspecialchars($job['company_name']); ?>">
                                    Notify
                                </button>
                                <a href="view_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-info">View</a>
                                <a href="edit_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="delete_job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Notify Students Modal -->
<div class="modal fade" id="notifyModal" tabindex="-1" aria-labelledby="notifyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notifyModalLabel">Notify Students for...</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="notify_students.php" method="POST">
        <div class="modal-body">
          <input type="hidden" name="job_id" id="modal_job_id">
          <p>Select the passout years of students to notify:</p>
          <div id="passout-years-container">
            <?php foreach ($passout_years as $year): ?>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="passout_years[]" value="<?php echo $year['passout_year']; ?>" id="year_<?php echo $year['passout_year']; ?>">
                    <label class="form-check-label" for="year_<?php echo $year['passout_year']; ?>">
                        Batch of <?php echo $year['passout_year']; ?>
                    </label>
                </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-warning">Send Notifications</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const notifyModal = document.getElementById('notifyModal');
    notifyModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const jobId = button.getAttribute('data-job-id');
        const companyName = button.getAttribute('data-company-name');
        
        const modalTitle = notifyModal.querySelector('.modal-title');
        const modalJobIdInput = notifyModal.querySelector('#modal_job_id');
        
        modalTitle.textContent = 'Notify Students for ' + companyName;
        modalJobIdInput.value = jobId;
    });
});
</script>

</body>
</html>