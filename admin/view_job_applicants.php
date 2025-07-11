<?php
// gecm/admin/view_job_applicants.php
session_start();
require_once '../db_connect.php';
require '../vendor/autoload.php'; // Include Composer's autoloader for PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Auth check & ID validation
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }
if (!isset($_GET['job_id'])) { header("Location: manage_jobs.php"); exit(); }
$job_id = (int)$_GET['job_id'];

// --- HANDLE THE UPDATE FORM SUBMISSION FROM THE MODAL ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_application'])) {
    $application_id = (int)$_POST['application_id'];
    $was_present = !empty($_POST['was_present']) ? $_POST['was_present'] : null;
    $round1_status = !empty($_POST['round1_status']) ? $_POST['round1_status'] : null;
    $round2_status = !empty($_POST['round2_status']) ? $_POST['round2_status'] : null;
    $round3_status = !empty($_POST['round3_status']) ? $_POST['round3_status'] : null;
    $is_selected = !empty($_POST['is_selected']) ? $_POST['is_selected'] : null;
    $ctc = !empty($_POST['ctc']) ? (float)$_POST['ctc'] : null;

    $stmt_update = $conn->prepare(
        "UPDATE job_applications 
         SET was_present=?, round1_status=?, round2_status=?, round3_status=?, is_selected=?, CTC=? 
         WHERE id=?"
    );
    $stmt_update->bind_param("sssssdi", $was_present, $round1_status, $round2_status, $round3_status, $is_selected, $ctc, $application_id);
    
    if ($stmt_update->execute()) {
        $_SESSION['message'] = ['text' => 'Applicant status updated successfully!', 'type' => 'success'];
    } else {
        $_SESSION['message'] = ['text' => 'Error updating status: ' . $stmt_update->error, 'type' => 'danger'];
    }
    $stmt_update->close();
    
    header("Location: view_job_applicants.php?job_id=" . $job_id);
    exit();
}

// --- Fetch Job and Applicant Data (This block is now safe from the POST request) ---
$stmt_job = $conn->prepare("SELECT company_name FROM jobs WHERE id = ?");
$stmt_job->bind_param("i", $job_id);
$stmt_job->execute();
$job = $stmt_job->get_result()->fetch_assoc();
if (!$job) {
    $_SESSION['message'] = ['text' => 'Job not found.', 'type' => 'danger'];
    header("Location: manage_jobs.php"); 
    exit();
}
$company_name = $job['company_name'];
$stmt_job->close();

$sql_applicants = "
    SELECT s.name, s.enrollment_no, s.email, s.branch, s.whatsapp_no, ja.*, s.id as student_id
    FROM job_applications ja
    JOIN students s ON ja.student_id = s.id
    WHERE ja.job_id = ?
    ORDER BY s.enrollment_no ASC"; // <-- CHANGE MADE HERE
$stmt_applicants = $conn->prepare($sql_applicants);
$stmt_applicants->bind_param("i", $job_id);
$stmt_applicants->execute();
$applicants = $stmt_applicants->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_applicants->close();
$conn->close();

// --- RESTORED EXCEL EXPORT LOGIC ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($company_name, 0, 31));
    
    // Set Headers
    $sheet->fromArray(
        ['Student Name', 'Enrollment Number', 'Email', 'Branch', 'WhatsApp No', 'Present', 'Round 1', 'Round 2', 'Round 3', 'Selected', 'CTC (LPA)'],
        NULL,
        'A1'
    );

    // Style Headers
    $headerStyle = ['font' => ['bold' => true], 'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDDDDDD']]];
    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);
    
        // Write Data row by row to have full control over formatting
    $row = 2; // Start from row 2
    foreach ($applicants as $applicant) {
        $sheet->setCellValue('A' . $row, $applicant['name']);
        
        // --- THIS IS THE FIX ---
        // Explicitly set the cell value as a STRING
        $sheet->setCellValueExplicit(
            'B' . $row, 
            $applicant['enrollment_no'], 
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        // -----------------------

        $sheet->setCellValue('C' . $row, $applicant['email']);
        $sheet->setCellValue('D' . $row, $applicant['branch']);

        // --- ALSO A GOOD PRACTICE FOR PHONE NUMBERS ---
        $sheet->setCellValueExplicit(
            'E' . $row, 
            $applicant['whatsapp_no'], 
            \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
        );
        // ---------------------------------------------

        $sheet->setCellValue('F' . $row, $applicant['was_present']);
        $sheet->setCellValue('G' . $row, $applicant['round1_status']);
        $sheet->setCellValue('H' . $row, $applicant['round2_status']);
        $sheet->setCellValue('I' . $row, $applicant['round3_status']);
        $sheet->setCellValue('J' . $row, $applicant['is_selected']);
        
        // Write CTC as a number for formatting, but check if it's not null
        $ctc_value = !is_null($applicant['CTC']) ? (float)$applicant['CTC'] : null;
        $sheet->setCellValue('K' . $row, $ctc_value);
        
        $row++;
    }

    // Apply number format to the entire CTC column
    $sheet->getStyle('K')->getNumberFormat()->setFormatCode('#,##0.00');

    // Auto-size columns for better readability
    foreach(range('A','K') as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Set filename and headers for download
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $company_name) . '_Applicants_Status.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>View Applicants</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Applicants for <?php echo htmlspecialchars($company_name); ?></h3>
        <div>
            <a href="view_job_applicants.php?job_id=<?php echo $job_id; ?>&export=excel" class="btn btn-success">Export All to Excel</a>
            <a href="manage_jobs.php" class="btn btn-secondary">← Back to Job List</a>
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
            <h5 class="card-title">Total Applicants: <?php echo count($applicants); ?></h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Enrollment</th>
                            <th>Present</th>
                            <th>R1 Status</th>
                            <th>R2 Status</th>
                            <th>R3 Status</th>
                            <th>Selected</th>
                            <th>CTC (LPA)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applicants)): ?>
                            <tr><td colspan="9" class="text-center">No students have applied for this job yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($applicants as $applicant): ?>
                            <tr>
                                <td class="text-nowrap"><?php echo htmlspecialchars($applicant['name']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['enrollment_no']); ?></td>
                                <td><?php echo htmlspecialchars($applicant['was_present'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($applicant['round1_status'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($applicant['round2_status'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($applicant['round3_status'] ?? 'N/A'); ?></td>
                                <td><span class="badge bg-<?php echo $applicant['is_selected'] == 'Yes' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($applicant['is_selected'] ?? 'Pending'); ?></span></td>
                                <td><b><?php echo htmlspecialchars($applicant['CTC'] ?? '-'); ?></b></td>
                                <td>
                                    <a href="view_student.php?id=<?php echo $applicant['student_id']; ?>" target="_blank" class="btn btn-info btn-sm">Profile</a>
                                    <button class="btn btn-primary btn-sm update-btn" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#updateModal"
                                            data-application-id="<?php echo $applicant['id']; ?>"
                                            data-student-name="<?php echo htmlspecialchars($applicant['name']); ?>"
                                            data-was-present="<?php echo $applicant['was_present']; ?>"
                                            data-round1-status="<?php echo $applicant['round1_status']; ?>"
                                            data-round2-status="<?php echo $applicant['round2_status']; ?>"
                                            data-round3-status="<?php echo $applicant['round3_status']; ?>"
                                            data-is-selected="<?php echo $applicant['is_selected']; ?>"
                                            data-ctc="<?php echo $applicant['CTC']; ?>">
                                        Update
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Updating Status -->
<div class="modal fade" id="updateModal" tabindex="-1" aria-labelledby="updateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateModalLabel">Update Status for...</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="view_job_applicants.php?job_id=<?php echo $job_id; ?>" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="application_id">
                    <input type="hidden" name="update_application" value="1">

                    <div class="mb-3"><label class="form-label">Attendance</label>
                        <select name="was_present" id="was_present" class="form-select">
                            <option value="">(Not Set)</option><option value="Yes">Yes</option><option value="No">No</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3"><label class="form-label">Round 1</label>
                            <select name="round1_status" id="round1_status" class="form-select"><option value="">(N/A)</option><option value="Passed">Passed</option><option value="Failed">Failed</option></select>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label">Round 2</label>
                             <select name="round2_status" id="round2_status" class="form-select"><option value="">(N/A)</option><option value="Passed">Passed</option><option value="Failed">Failed</option></select>
                        </div>
                        <div class="col-md-4 mb-3"><label class="form-label">Round 3</label>
                             <select name="round3_status" id="round3_status" class="form-select"><option value="">(N/A)</option><option value="Passed">Passed</option><option value="Failed">Failed</option></select>
                        </div>
                    </div>
                    
                    <hr>

                    <div class="row">
                         <div class="col-md-6 mb-3"><label class="form-label">Final Selection Status</label>
                            <select name="is_selected" id="is_selected" class="form-select">
                                <option value="">(Pending)</option><option value="Yes">Yes - Selected</option><option value="No">No - Not Selected</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3"><label class="form-label">Final CTC (LPA)</label>
                            <input type="number" step="0.01" name="ctc" id="ctc" class="form-control" placeholder="e.g., 4.5">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript for modal pre-fill (No changes needed here)
document.addEventListener('DOMContentLoaded', function () {
    const updateModal = document.getElementById('updateModal');
    updateModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const modalForm = updateModal.querySelector('form');
        updateModal.querySelector('.modal-title').textContent = 'Update Status for ' + button.getAttribute('data-student-name');
        modalForm.querySelector('#application_id').value = button.getAttribute('data-application-id');
        modalForm.querySelector('#was_present').value = button.getAttribute('data-was-present');
        modalForm.querySelector('#round1_status').value = button.getAttribute('data-round1-status');
        modalForm.querySelector('#round2_status').value = button.getAttribute('data-round2-status');
        modalForm.querySelector('#round3_status').value = button.getAttribute('data-round3-status');
        modalForm.querySelector('#is_selected').value = button.getAttribute('data-is-selected');
        modalForm.querySelector('#ctc').value = button.getAttribute('data-ctc');
    });
});
</script>

</body>
</html>