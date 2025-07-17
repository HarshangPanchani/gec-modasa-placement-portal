<?php
// gecm/admin/reports.php
session_start();
require_once '../db_connect.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

// --- Auth Check ---
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

// --- Pre-fetch data for filter dropdowns ---
$passout_years = $conn->query("SELECT DISTINCT passout_year FROM students ORDER BY passout_year DESC")->fetch_all(MYSQLI_ASSOC);
$branches = $conn->query("SELECT DISTINCT branch FROM students ORDER BY branch ASC")->fetch_all(MYSQLI_ASSOC);
$cities = $conn->query("SELECT DISTINCT city FROM students WHERE city IS NOT NULL AND city != '' ORDER BY city ASC")->fetch_all(MYSQLI_ASSOC);
$companies = $conn->query("SELECT DISTINCT company_name FROM jobs ORDER BY company_name ASC")->fetch_all(MYSQLI_ASSOC);
$posting_years = $conn->query("SELECT DISTINCT posting_year FROM jobs ORDER BY posting_year DESC")->fetch_all(MYSQLI_ASSOC);

// --- Initialize variables ---
$report_data = [];
$report_generated = false;
$summary_stats = [];

// --- HANDLE FORM SUBMISSION & REPORT GENERATION ---
if (isset($_GET['generate_report'])) {
    $report_generated = true;

    // --- 1. Build the Dynamic SQL Query based on filters ---
    // (This query builder logic remains the same)
    $report_type = $_GET['report_type'] ?? 'placed';
    $params = [];
    $types = "";
    $where_clauses = [];

    if ($report_type === 'placed') {
        $base_sql = "SELECT s.name, s.enrollment_no, s.email, s.branch, s.passout_year, s.current_cgpa, j.company_name, j.posting_year, ja.CTC, s.gender, s.city FROM job_applications ja JOIN students s ON ja.student_id = s.id JOIN jobs j ON ja.job_id = j.id WHERE ja.is_selected = 'Yes'";
    } else {
        $base_sql = "SELECT s.name, s.enrollment_no, s.email, s.branch, s.passout_year, s.current_cgpa, s.gender, s.city FROM students s WHERE s.status = 'Active'";
    }

    if (!empty($_GET['passout_year'])) { $where_clauses[] = "s.passout_year = ?"; $params[] = $_GET['passout_year']; $types .= "i"; }
    if ($_SESSION['admin_role'] === 'sub_admin') { $where_clauses[] = "s.branch = ?"; $params[] = $_SESSION['admin_branch']; $types .= "s"; } 
    elseif (!empty($_GET['branch'])) { $where_clauses[] = "s.branch = ?"; $params[] = $_GET['branch']; $types .= "s"; }
    if (!empty($_GET['gender'])) { $where_clauses[] = "s.gender = ?"; $params[] = $_GET['gender']; $types .= "s"; }
    if (!empty($_GET['city'])) { $where_clauses[] = "s.city = ?"; $params[] = $_GET['city']; $types .= "s"; }
    if (!empty($_GET['cgpa_min'])) { $where_clauses[] = "s.current_cgpa >= ?"; $params[] = $_GET['cgpa_min']; $types .= "d"; }
    if (!empty($_GET['cgpa_max'])) { $where_clauses[] = "s.current_cgpa <= ?"; $params[] = $_GET['cgpa_max']; $types .= "d"; }
    if ($report_type === 'placed') {
        if (!empty($_GET['company_name'])) { $where_clauses[] = "j.company_name = ?"; $params[] = $_GET['company_name']; $types .= "s"; }
        if (!empty($_GET['posting_year'])) { $where_clauses[] = "j.posting_year = ?"; $params[] = $_GET['posting_year']; $types .= "i"; }
    }

    if (!empty($where_clauses)) { $base_sql .= " AND " . implode(" AND ", $where_clauses); }
    $base_sql .= " ORDER BY s.branch, s.enrollment_no";

    $stmt = $conn->prepare($base_sql);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // --- 2. Calculate Summary Statistics (if placed report) ---
    if ($report_type === 'placed' && !empty($report_data)) {
        $ctc_values = array_column($report_data, 'CTC');
        $total_placed = count($ctc_values);
        $summary_stats = [
            'total_placed' => $total_placed,
            'max_ctc' => max($ctc_values),
            'min_ctc' => min($ctc_values),
            'avg_ctc' => $total_placed > 0 ? array_sum($ctc_values) / $total_placed : 0
        ];
    }
    
    // --- 3. Handle EXPORT request ---
    // This block now runs before any HTML, preventing the corruption error.
    if (isset($_GET['export'])) {
        $spreadsheet = new Spreadsheet();
        $sheet_index = 0;

        // --- Add Summary Sheet to Excel (if placed report) ---
        if ($report_type === 'placed' && !empty($report_data)) {
            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Summary');
            $summarySheet->setCellValue('A1', 'Placement Report Summary');
            $summarySheet->mergeCells('A1:B1');
            $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $summaryData = [
                ['Total Students Placed', $summary_stats['total_placed']],
                ['Highest Package (LPA)', $summary_stats['max_ctc']],
                ['Lowest Package (LPA)', $summary_stats['min_ctc']],
                ['Average Package (LPA)', number_format($summary_stats['avg_ctc'], 2)]
            ];
            $summarySheet->fromArray($summaryData, NULL, 'A3');
            $summarySheet->getStyle('A3:A6')->getFont()->setBold(true);
            $summarySheet->getColumnDimension('A')->setAutoSize(true);
            $summarySheet->getColumnDimension('B')->setAutoSize(true);
            
            $detailSheet = $spreadsheet->createSheet(); // Create a new sheet for the data
            $sheet_index = 1;
        } else {
            $detailSheet = $spreadsheet->getActiveSheet();
        }
        
        $spreadsheet->setActiveSheetIndex($sheet_index);
        $detailSheet->setTitle('Detailed Report');
        
        // Headers for detailed data sheet
        $headers = ['Student Name', 'Enrollment Number', 'Email', 'Branch', 'Passout Year', 'CGPA'];
        if ($report_type === 'placed') {
            $headers[] = 'Company Name';
            $headers[] = 'CTC (LPA)';
        }
        $detailSheet->fromArray($headers, NULL, 'A1');
        $detailSheet->getStyle('A1:' . $detailSheet->getHighestColumn() . '1')->getFont()->setBold(true);

        // Write the detailed data
        $row_index = 2;
        foreach ($report_data as $row) {
            $detailSheet->setCellValue('A' . $row_index, $row['name']);
            $detailSheet->setCellValueExplicit('B' . $row_index, $row['enrollment_no'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $detailSheet->setCellValue('C' . $row_index, $row['email']);
            $detailSheet->setCellValue('D' . $row_index, $row['branch']);
            $detailSheet->setCellValue('E' . $row_index, $row['passout_year']);
            $detailSheet->setCellValue('F' . $row_index, $row['current_cgpa']);
            if ($report_type === 'placed') {
                $detailSheet->setCellValue('G' . $row_index, $row['company_name']);
                $detailSheet->setCellValue('H' . $row_index, $row['CTC']);
            }
            $row_index++;
        }
        
        // Formatting
        if ($report_type === 'placed') { $detailSheet->getStyle('H')->getNumberFormat()->setFormatCode('#,##0.00'); }
        foreach ($detailSheet->getColumnDimensions() as $col) { $col->setAutoSize(true); }

        // Download the file
        $filename = "Placement_Report_" . ucfirst($report_type) . "_" . date('Y-m-d') . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit(); // Crucial: exit() prevents any HTML from being sent.
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Generate Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- CSS FOR DATATABLES SEARCH BAR -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">
<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Placement Reporting Engine</h3>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Select Report Criteria</h5></div>
        <div class="card-body p-4">
            <form action="reports.php" method="GET">
                 <!-- The entire form from the previous response goes here, unchanged -->
                <div class="row">
                    <div class="col-md-4 mb-3"><label for="report_type" class="form-label fw-bold">1. Report Type</label><select name="report_type" id="report_type" class="form-select" required><option value="placed" <?php if(isset($_GET['report_type']) && $_GET['report_type'] == 'placed') echo 'selected'; ?>>Placed Students</option><option value="unplaced" <?php if(isset($_GET['report_type']) && $_GET['report_type'] == 'unplaced') echo 'selected'; ?>>Unplaced (Active) Students</option></select></div>
                </div>
                <p class="fw-bold mt-3">2. Apply Filters (Optional)</p>
                <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">Passout Year</label><select name="passout_year" class="form-select"><option value="">All</option><?php foreach ($passout_years as $year): ?><option value="<?php echo $year['passout_year']; ?>" <?php if(isset($_GET['passout_year']) && $_GET['passout_year'] == $year['passout_year']) echo 'selected'; ?>><?php echo $year['passout_year']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Branch</label><select name="branch" class="form-select" <?php if ($_SESSION['admin_role'] === 'sub_admin') echo 'disabled'; ?>><option value="">All</option><?php foreach ($branches as $branch): $selected = ($_SESSION['admin_role'] === 'sub_admin' && $_SESSION['admin_branch'] === $branch['branch']) ? 'selected' : (isset($_GET['branch']) && $_GET['branch'] == $branch['branch'] ? 'selected' : ''); ?><option value="<?php echo $branch['branch']; ?>" <?php echo $selected; ?>><?php echo $branch['branch']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">All</option><option value="Male" <?php if(isset($_GET['gender']) && $_GET['gender'] == 'Male') echo 'selected'; ?>>Male</option><option value="Female" <?php if(isset($_GET['gender']) && $_GET['gender'] == 'Female') echo 'selected'; ?>>Female</option><option value="Other" <?php if(isset($_GET['gender']) && $_GET['gender'] == 'Other') echo 'selected'; ?>>Other</option></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">City</label><select name="city" class="form-select"><option value="">All</option><?php foreach ($cities as $city): ?><option value="<?php echo $city['city']; ?>" <?php if(isset($_GET['city']) && $_GET['city'] == $city['city']) echo 'selected'; ?>><?php echo $city['city']; ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="row" id="placed-filters">
                    <div class="col-md-3 mb-3"><label class="form-label">Company</label><select name="company_name" class="form-select"><option value="">All</option><?php foreach ($companies as $company): ?><option value="<?php echo $company['company_name']; ?>" <?php if(isset($_GET['company_name']) && $_GET['company_name'] == $company['company_name']) echo 'selected'; ?>><?php echo $company['company_name']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Company Posting Year</label><select name="posting_year" class="form-select"><option value="">All</option><?php foreach ($posting_years as $year): ?><option value="<?php echo $year['posting_year']; ?>" <?php if(isset($_GET['posting_year']) && $_GET['posting_year'] == $year['posting_year']) echo 'selected'; ?>><?php echo $year['posting_year']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Min CGPA</label><input type="number" step="0.1" name="cgpa_min" class="form-control" placeholder="e.g., 7.5" value="<?php echo $_GET['cgpa_min'] ?? ''; ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Max CGPA</label><input type="number" step="0.1" name="cgpa_max" class="form-control" placeholder="e.g., 9.0" value="<?php echo $_GET['cgpa_max'] ?? ''; ?>"></div>
                </div>
                <div class="mt-3 d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="reports.php" class="btn btn-secondary">Clear Filters</a>
                    <button type="submit" name="generate_report" value="1" class="btn btn-primary">Generate Report</button>
                    <?php if ($report_generated && !empty($report_data)): ?><a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']) . '&export=excel'; ?>" class="btn btn-success">Export This View to Excel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <?php if ($report_generated): ?>
        <!-- SUMMARY CARD FOR PLACED STUDENTS -->
        <?php if ($report_type === 'placed' && !empty($summary_stats)): ?>
        <div class="card shadow-sm mt-4">
             <div class="card-header"><h5 class="mb-0">Report Summary</h5></div>
             <div class="card-body">
                <div class="row text-center">
                    <div class="col"><h5><?php echo $summary_stats['total_placed']; ?></h5><span class="text-muted">Total Placed</span></div>
                    <div class="col"><h5><?php echo $summary_stats['max_ctc']; ?></h5><span class="text-muted">Highest CTC</span></div>
                    <div class="col"><h5><?php echo $summary_stats['min_ctc']; ?></h5><span class="text-muted">Lowest CTC</span></div>
                    <div class="col"><h5><?php echo number_format($summary_stats['avg_ctc'], 2); ?></h5><span class="text-muted">Average CTC</span></div>
                </div>
             </div>
        </div>
        <?php endif; ?>

        <!-- DETAILED RESULTS TABLE -->
        <div class="card shadow-sm mt-4">
            <div class="card-header"><h5 class="mb-0">Report Results (<?php echo count($report_data); ?> records found)</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="report-table" class="table table-striped table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>Name</th><th>Enrollment</th><th>Branch</th><th>Passout Year</th>
                                <?php if ($_GET['report_type'] === 'placed'): ?><th>Company</th><th>CTC (LPA)</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['enrollment_no']); ?></td>
                                <td><?php echo htmlspecialchars($row['branch']); ?></td>
                                <td><?php echo htmlspecialchars($row['passout_year']); ?></td>
                                <?php if ($_GET['report_type'] === 'placed'): ?>
                                    <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['CTC']); ?></td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
<!-- JAVASCRIPT LIBRARIES FOR DATATABLES -->
<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
    // Initialize DataTables for search/sort
    $(document).ready(function() {
        $('#report-table').DataTable();
    });

    // JS to show/hide placed-specific filters
    document.getElementById('report_type').addEventListener('change', function() {
        var placedFilters = document.getElementById('placed-filters');
        if (this.value === 'unplaced') {
            placedFilters.style.display = 'none';
        } else {
            placedFilters.style.display = 'block';
        }
    });
    document.getElementById('report_type').dispatchEvent(new Event('change'));
</script>
</body>
</html>