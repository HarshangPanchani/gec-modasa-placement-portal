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

// --- AJAX HANDLER FOR DYNAMIC FILTERS ---
// This block handles live requests from JavaScript to update filter dropdowns.
if (isset($_GET['action']) && $_GET['action'] == 'get_dynamic_filters') {
    $passout_year = $_GET['passout_year'] ?? null;
    $company_name = $_GET['company_name'] ?? null;
    
    $response = [
        'companies' => [],
        'posting_years' => []
    ];

    // Base query for filtering
    $company_sql = "SELECT DISTINCT j.company_name FROM jobs j JOIN job_applications ja ON j.id = ja.job_id JOIN students s ON ja.student_id = s.id WHERE ja.is_selected = 'Yes'";
    $year_sql = "SELECT DISTINCT j.posting_year FROM jobs j JOIN job_applications ja ON j.id = ja.job_id JOIN students s ON ja.student_id = s.id WHERE ja.is_selected = 'Yes'";
    $params = [];
    $types = "";

    // Build WHERE clause based on input
    if (!empty($passout_year)) {
        $where_clause = " AND s.passout_year = ?";
        $params[] = $passout_year;
        $types .= "i";
    }
    if (!empty($company_name)) {
        $where_clause = " AND j.company_name = ?";
        // Use a different param array for the second query
        $params2[] = $company_name;
        $types2 = "s";
    }
    
    // Get Companies based on Year (if year is selected)
    if (!empty($passout_year)) {
        $stmt_company = $conn->prepare($company_sql . $where_clause);
        $stmt_company->bind_param($types, ...$params);
        $stmt_company->execute();
        $response['companies'] = $stmt_company->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_company->close();
    } else {
        // If no year selected, get all companies
        $response['companies'] = $conn->query($company_sql . " ORDER BY j.company_name ASC")->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get Posting Years based on Company (if company is selected)
    if (!empty($company_name)) {
        $stmt_year = $conn->prepare($year_sql . $where_clause);
        $stmt_year->bind_param($types2, ...$params2);
        $stmt_year->execute();
        $response['posting_years'] = $stmt_year->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_year->close();
    } else {
         // If no company selected, get all posting years
        $response['posting_years'] = $conn->query($year_sql . " ORDER BY j.posting_year DESC")->fetch_all(MYSQLI_ASSOC);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit(); // Stop script execution after sending JSON
}


// --- Pre-fetch data for initial filter dropdowns ---
$passout_years = $conn->query("SELECT DISTINCT passout_year FROM students ORDER BY passout_year DESC")->fetch_all(MYSQLI_ASSOC);
$branches = $conn->query("SELECT DISTINCT branch FROM students ORDER BY branch ASC")->fetch_all(MYSQLI_ASSOC);
$cities = $conn->query("SELECT DISTINCT city FROM students WHERE city IS NOT NULL AND city != '' ORDER BY city ASC")->fetch_all(MYSQLI_ASSOC);
// Initial load gets ALL companies and years. JS will refine this.
$companies = $conn->query("SELECT DISTINCT company_name FROM jobs ORDER BY company_name ASC")->fetch_all(MYSQLI_ASSOC);
$posting_years = $conn->query("SELECT DISTINCT posting_year FROM jobs ORDER BY posting_year DESC")->fetch_all(MYSQLI_ASSOC);

// --- Initialize variables ---
$report_data = [];
$grouped_data = [];
$report_generated = false;
$summary_stats = [];
$grouping_level = 'none'; // 'none', 'company', 'year', 'year_company'

// --- HANDLE FORM SUBMISSION & REPORT GENERATION ---
if (isset($_GET['generate_report'])) {
    $report_generated = true;

    // --- 1. Build the Dynamic SQL Query based on filters ---
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
    
    // Define order for grouping
    $order_by_clause = " ORDER BY s.passout_year DESC, j.company_name ASC, s.branch, s.enrollment_no";
    if ($report_type !== 'placed') {
        $order_by_clause = " ORDER BY s.passout_year DESC, s.branch, s.enrollment_no";
    }

    $stmt = $conn->prepare($base_sql . $order_by_clause);
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
            'max_ctc' => $total_placed > 0 ? max($ctc_values) : 0,
            'min_ctc' => $total_placed > 0 ? min($ctc_values) : 0,
            'avg_ctc' => $total_placed > 0 ? array_sum($ctc_values) / $total_placed : 0
        ];
    }
    
    // --- 3. Determine Grouping Level and Structure Data ---
    if ($report_type === 'placed' && !empty($report_data)) {
        $is_year_all = empty($_GET['passout_year']);
        $is_company_all = empty($_GET['company_name']);

        if ($is_year_all && $is_company_all) { $grouping_level = 'year_company'; }
        elseif ($is_year_all && !$is_company_all) { $grouping_level = 'year'; }
        elseif (!$is_year_all && $is_company_all) { $grouping_level = 'company'; }
        // else: both specific, level remains 'none' for a single table view

        // Create the grouped data array based on the determined level
        if ($grouping_level === 'year_company') {
            foreach ($report_data as $row) { $grouped_data[$row['passout_year']][$row['company_name']][] = $row; }
        } elseif ($grouping_level === 'company') {
            foreach ($report_data as $row) { $grouped_data[$row['company_name']][] = $row; }
        } elseif ($grouping_level === 'year') {
            foreach ($report_data as $row) { $grouped_data[$row['passout_year']][] = $row; }
        }
    }

    // --- 4. Handle EXPORT request ---
    // (This remains unchanged, exporting a flat file is best for data analysis)
    if (isset($_GET['export'])) {
        $spreadsheet = new Spreadsheet();
        $sheet_index = 0;

        if ($report_type === 'placed' && !empty($report_data)) {
            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Summary');
            $summarySheet->setCellValue('A1', 'Placement Report Summary');
            $summarySheet->mergeCells('A1:B1');
            $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $summaryData = [['Total Students Placed', $summary_stats['total_placed']],['Highest Package (LPA)', $summary_stats['max_ctc']],['Lowest Package (LPA)', $summary_stats['min_ctc']],['Average Package (LPA)', number_format($summary_stats['avg_ctc'], 2)]];
            $summarySheet->fromArray($summaryData, NULL, 'A3');
            $summarySheet->getStyle('A3:A6')->getFont()->setBold(true);
            $summarySheet->getColumnDimension('A')->setAutoSize(true);
            $summarySheet->getColumnDimension('B')->setAutoSize(true);
            
            $detailSheet = $spreadsheet->createSheet();
            $sheet_index = 1;
        } else {
            $detailSheet = $spreadsheet->getActiveSheet();
        }
        
        $spreadsheet->setActiveSheetIndex($sheet_index);
        $detailSheet->setTitle('Detailed Report');
        
        $headers = ['Student Name', 'Enrollment Number', 'Email', 'Branch', 'Passout Year', 'CGPA'];
        if ($report_type === 'placed') {
            $headers[] = 'Company Name';
            $headers[] = 'Posting Year';
            $headers[] = 'CTC (LPA)';
        }
        $detailSheet->fromArray($headers, NULL, 'A1');
        $detailSheet->getStyle('A1:' . $detailSheet->getHighestColumn() . '1')->getFont()->setBold(true);

        $row_index = 2;
        foreach ($report_data as $row) {
            $data_row = [$row['name'], $row['enrollment_no'], $row['email'], $row['branch'], $row['passout_year'], $row['current_cgpa']];
            if ($report_type === 'placed') {
                $data_row[] = $row['company_name'];
                $data_row[] = $row['posting_year'];
                $data_row[] = $row['CTC'];
            }
            $detailSheet->fromArray($data_row, NULL, 'A'.$row_index);
            $detailSheet->getStyle('B'.$row_index)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $row_index++;
        }
        
        if ($report_type === 'placed') { $detailSheet->getStyle('I')->getNumberFormat()->setFormatCode('#,##0.00'); }
        foreach ($detailSheet->getColumnDimensions() as $col) { $col->setAutoSize(true); }

        $filename = "Placement_Report_" . ucfirst($report_type) . "_" . date('Y-m-d') . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Generate Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .card.sub-group { margin-left: 20px; border-left: 3px solid #0d6efd; }
    </style>
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
                <div class="row">
                    <div class="col-md-4 mb-3"><label for="report_type" class="form-label fw-bold">1. Report Type</label><select name="report_type" id="report_type" class="form-select" required><option value="placed" <?php if(isset($_GET['report_type']) && $_GET['report_type'] == 'placed') echo 'selected'; ?>>Placed Students</option><option value="unplaced" <?php if(isset($_GET['report_type']) && $_GET['report_type'] == 'unplaced') echo 'selected'; ?>>Unplaced (Active) Students</option></select></div>
                </div>
                <p class="fw-bold mt-3">2. Apply Filters (Optional)</p>
                <div class="row">
                    <div class="col-md-3 mb-3"><label class="form-label">Passout Year</label><select name="passout_year" id="passout_year_filter" class="form-select"><option value="">All</option><?php foreach ($passout_years as $year): ?><option value="<?php echo $year['passout_year']; ?>" <?php if(isset($_GET['passout_year']) && $_GET['passout_year'] == $year['passout_year']) echo 'selected'; ?>><?php echo $year['passout_year']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Branch</label><select name="branch" class="form-select" <?php if ($_SESSION['admin_role'] === 'sub_admin') echo 'disabled'; ?>><option value="">All</option><?php foreach ($branches as $branch): $selected = ($_SESSION['admin_role'] === 'sub_admin' && $_SESSION['admin_branch'] === $branch['branch']) ? 'selected' : (isset($_GET['branch']) && $_GET['branch'] == $branch['branch'] ? 'selected' : ''); ?><option value="<?php echo $branch['branch']; ?>" <?php echo $selected; ?>><?php echo $branch['branch']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">All</option><option value="Male" <?php if(isset($_GET['gender']) && $_GET['gender'] == 'Male') echo 'selected'; ?>>Male</option><option value="Female" <?php if(isset($_GET['gender']) && $_GET['gender'] == 'Female') echo 'selected'; ?>>Female</option><option value="Other" <?php if(isset($_GET['gender']) && $_GET['gender'] == 'Other') echo 'selected'; ?>>Other</option></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">City</label><select name="city" class="form-select"><option value="">All</option><?php foreach ($cities as $city): ?><option value="<?php echo $city['city']; ?>" <?php if(isset($_GET['city']) && $_GET['city'] == $city['city']) echo 'selected'; ?>><?php echo $city['city']; ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="row" id="placed-filters">
                    <div class="col-md-3 mb-3"><label class="form-label">Company</label><select name="company_name" id="company_filter" class="form-select"><option value="">All</option><?php foreach ($companies as $company): ?><option value="<?php echo $company['company_name']; ?>" <?php if(isset($_GET['company_name']) && $_GET['company_name'] == $company['company_name']) echo 'selected'; ?>><?php echo $company['company_name']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Company Posting Year</label><select name="posting_year" id="posting_year_filter" class="form-select"><option value="">All</option><?php foreach ($posting_years as $year): ?><option value="<?php echo $year['posting_year']; ?>" <?php if(isset($_GET['posting_year']) && $_GET['posting_year'] == $year['posting_year']) echo 'selected'; ?>><?php echo $year['posting_year']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Min CGPA</label><input type="number" step="0.1" name="cgpa_min" class="form-control" placeholder="e.g., 7.5" value="<?php echo $_GET['cgpa_min'] ?? ''; ?>"></div>
                    <div class="col-md-3 mb-3"><label class="form-label">Max CGPA</label><input type="number" step="0.1" name="cgpa_max" class="form-control" placeholder="e.g., 9.0" value="<?php echo $_GET['cgpa_max'] ?? ''; ?>"></div>
                </div>
                <div class="mt-3 d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="reports.php" class="btn btn-secondary">Clear Filters</a>
                    <button type="submit" name="generate_report" value="1" class="btn btn-primary">Generate Report</button>
                    <?php if ($report_generated && !empty($report_data)): ?><a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']) . '&export=excel'; ?>" class="btn btn-success">Export to Excel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <?php if ($report_generated): ?>
        <?php if (empty($report_data)): ?>
            <div class="alert alert-warning mt-4">No records found matching your criteria.</div>
        <?php else: ?>
            <!-- SUMMARY CARD FOR PLACED STUDENTS -->
            <?php if ($report_type === 'placed' && !empty($summary_stats)): ?>
            <div class="card shadow-sm mt-4">
                 <div class="card-header"><h5 class="mb-0">Report Summary</h5></div>
                 <div class="card-body">
                    <div class="row text-center">
                        <div class="col"><h5><?php echo $summary_stats['total_placed']; ?></h5><span class="text-muted">Total Placed</span></div>
                        <div class="col"><h5><?php echo number_format($summary_stats['max_ctc'], 2); ?></h5><span class="text-muted">Highest CTC</span></div>
                        <div class="col"><h5><?php echo number_format($summary_stats['min_ctc'], 2); ?></h5><span class="text-muted">Lowest CTC</span></div>
                        <div class="col"><h5><?php echo number_format($summary_stats['avg_ctc'], 2); ?></h5><span class="text-muted">Average CTC</span></div>
                    </div>
                 </div>
            </div>
            <?php endif; ?>

            <!-- DETAILED RESULTS -->
            <div class="mt-4">
                <h4 class="mb-3">Report Results (<?php echo count($report_data); ?> records found)</h4>
                
                <?php
                // --- RENDER TABLES BASED ON GROUPING LEVEL ---

                // Case: NO GROUPING (Single flat table)
                if ($grouping_level === 'none'): ?>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <table id="report-table" class="table table-striped table-bordered w-100">
                                <?php include '_report_table_content.php'; // Use a partial for table content ?>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php
                // Case: GROUP BY COMPANY
                if ($grouping_level === 'company'):
                    foreach ($grouped_data as $company_name => $student_list): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header"><h5 class="mb-0">Company: <?php echo htmlspecialchars($company_name); ?></h5></div>
                            <div class="card-body">
                                <table class="table table-striped table-bordered w-100 report-table-grouped">
                                    <?php $report_data = $student_list; include '_report_table_content.php'; ?>
                                </table>
                            </div>
                        </div>
                <?php endforeach; endif; ?>

                <?php
                // Case: GROUP BY YEAR
                if ($grouping_level === 'year'):
                    foreach ($grouped_data as $year => $student_list): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header"><h5 class="mb-0">Passout Year: <?php echo htmlspecialchars($year); ?></h5></div>
                            <div class="card-body">
                                <table class="table table-striped table-bordered w-100 report-table-grouped">
                                    <?php $report_data = $student_list; include '_report_table_content.php'; ?>
                                </table>
                            </div>
                        </div>
                <?php endforeach; endif; ?>

                <?php
                // Case: GROUP BY YEAR, THEN COMPANY
                if ($grouping_level === 'year_company'):
                    foreach ($grouped_data as $year => $companies_data): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header"><h4 class="mb-0">Passout Year: <?php echo htmlspecialchars($year); ?></h4></div>
                            <div class="card-body">
                            <?php foreach ($companies_data as $company_name => $student_list): ?>
                                <div class="card sub-group mb-3">
                                    <div class="card-header bg-light"><h5 class="mb-0">Company: <?php echo htmlspecialchars($company_name); ?></h5></div>
                                    <div class="card-body">
                                        <table class="table table-striped table-bordered w-100 report-table-grouped">
                                            <?php $report_data = $student_list; include '_report_table_content.php'; ?>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>
                <?php endforeach; endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

</div>

<!-- Reusable Table Content Partial -->
<?php ob_start(); ?>
<thead>
    <tr>
        <th>Name</th><th>Enrollment</th><th>Branch</th>
        <?php if ($_GET['report_type'] === 'placed'): ?>
            <th>Company</th><th>Posting Year</th><th>CTC (LPA)</th><th>Passout Year</th>
        <?php else: ?>
            <th>Passout Year</th>
        <?php endif; ?>
    </tr>
</thead>
<tbody>
    <?php foreach ($report_data as $row): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['name']); ?></td>
        <td><?php echo htmlspecialchars($row['enrollment_no']); ?></td>
        <td><?php echo htmlspecialchars($row['branch']); ?></td>
        <?php if ($_GET['report_type'] === 'placed'): ?>
            <td><?php echo htmlspecialchars($row['company_name']); ?></td>
            <td><?php echo htmlspecialchars($row['posting_year']); ?></td>
            <td><?php echo htmlspecialchars($row['CTC']); ?></td>
        <?php endif; ?>
        <td><?php echo htmlspecialchars($row['passout_year']); ?></td>
    </tr>
    <?php endforeach; ?>
</tbody>
<?php file_put_contents('_report_table_content.php', ob_get_clean()); ?>

<!-- JAVASCRIPT LIBRARIES & CUSTOM SCRIPT -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    // --- Initialize DataTables ---
    // Single table for non-grouped results
    if ($('#report-table').length) {
        $('#report-table').DataTable({ "order": [] });
    }
    // Multiple tables for grouped results
    if ($('.report-table-grouped').length) {
        $('.report-table-grouped').DataTable({ "order": [] });
    }

    // --- Dynamic Filter Logic ---
    function updateFilters() {
        const passoutYear = $('#passout_year_filter').val();
        const companyName = $('#company_filter').val();

        // Show a loading indicator if you want
        // $('#company_filter, #posting_year_filter').prop('disabled', true);

        $.ajax({
            url: 'reports.php',
            type: 'GET',
            data: {
                action: 'get_dynamic_filters',
                passout_year: passoutYear,
                company_name: companyName
            },
            dataType: 'json',
            success: function(response) {
                // Update Company dropdown
                const companySelect = $('#company_filter');
                const currentCompany = companySelect.val(); // Preserve selection if possible
                companySelect.empty().append('<option value="">All</option>');
                $.each(response.companies, function(i, item) {
                    companySelect.append($('<option>', {
                        value: item.company_name,
                        text: item.company_name
                    }));
                });
                companySelect.val(currentCompany);

                // Update Posting Year dropdown
                const yearSelect = $('#posting_year_filter');
                const currentYear = yearSelect.val(); // Preserve selection
                yearSelect.empty().append('<option value="">All</option>');
                $.each(response.posting_years, function(i, item) {
                     yearSelect.append($('<option>', {
                        value: item.posting_year,
                        text: item.posting_year
                    }));
                });
                yearSelect.val(currentYear);
            },
            error: function() {
                console.error("Failed to fetch dynamic filters.");
            },
            complete: function() {
                 // $('#company_filter, #posting_year_filter').prop('disabled', false);
            }
        });
    }

    // Attach event listeners
    $('#passout_year_filter, #company_filter').on('change', function() {
        updateFilters();
    });

    // --- UI Logic for Report Type ---
    function togglePlacedFilters() {
        const placedFilters = $('#placed-filters');
        if ($('#report_type').val() === 'unplaced') {
            placedFilters.hide();
        } else {
            placedFilters.show();
        }
    }

    $('#report_type').on('change', togglePlacedFilters);

    // Initial setup on page load
    togglePlacedFilters();
    // Do not run updateFilters() on load if the page has GET params,
    // as the PHP has already populated the dropdowns correctly for the current report.
    <?php if (!isset($_GET['generate_report'])): ?>
        updateFilters(); // Run on initial load only if it's a fresh page.
    <?php endif; ?>
});
</script>
</body>
</html>




<thead>
    <tr>
        <th>Name</th><th>Enrollment</th><th>Branch</th>
                    <th>Company</th><th>Posting Year</th><th>CTC (LPA)</th><th>Passout Year</th>
            </tr>
</thead>
<tbody>
        <tr>
        <td>harshang p.</td>
        <td>230163107019</td>
        <td>Computer Engineering</td>
                    <td>thingslista automation llp</td>
            <td>2025</td>
            <td>1.30</td>
                <td>2026</td>
    </tr>
    </tbody>
