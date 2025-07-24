<?php
// gecm/admin/reports.php
session_start();
require_once '../db_connect.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

// --- Auth Check ---
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit(); }

// --- AJAX HANDLER FOR DYNAMIC FILTERS ---
// This is the new, correct logic: Placement Year -> Companies
if (isset($_GET['action']) && $_GET['action'] == 'get_companies_for_year') {
    header('Content-Type: application/json');
    $placement_year = $_GET['placement_year'] ?? null;
    
    $response = ['companies' => []];

    if ($placement_year) {
        $sql = "SELECT DISTINCT company_name FROM jobs WHERE posting_year = ? ORDER BY company_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $placement_year);
        $stmt->execute();
        $response['companies'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    echo json_encode($response);
    exit();
}

// --- Pre-fetch static data for filter dropdowns ---
$passout_years_data = $conn->query("SELECT DISTINCT passout_year FROM students ORDER BY passout_year DESC")->fetch_all(MYSQLI_ASSOC);
$branches_data = $conn->query("SELECT DISTINCT branch FROM students ORDER BY branch ASC")->fetch_all(MYSQLI_ASSOC);
$cities_data = $conn->query("SELECT DISTINCT city FROM students WHERE city IS NOT NULL AND city != '' ORDER BY city ASC")->fetch_all(MYSQLI_ASSOC);
// This is for the primary filter now: Placement Year
$placement_years_data = $conn->query("SELECT DISTINCT posting_year FROM jobs ORDER BY posting_year DESC")->fetch_all(MYSQLI_ASSOC);

// --- Initialize variables ---
$report_data = [];
$grouped_data = [];
$report_generated = false;
$summary_stats = [];
$grouping_level = 'none';
$report_type = $_GET['report_type'] ?? 'placed';

// --- HANDLE FORM SUBMISSION & REPORT GENERATION ---
// This backend logic remains robust and works with the new frontend flow
if (isset($_GET['generate_report'])) {
    $report_generated = true;
    $params = [];
    $types = "";
    $where_clauses = [];

    if ($report_type === 'placed') {
        $base_sql = "SELECT s.name, s.enrollment_no, s.email, s.branch, s.passout_year, s.current_cgpa, j.company_name, j.posting_year, ja.CTC, s.gender, s.city FROM job_applications ja JOIN students s ON ja.student_id = s.id JOIN jobs j ON ja.job_id = j.id WHERE ja.is_selected = 'Yes'";
    } else { 
        $base_sql = "SELECT s.name, s.enrollment_no, s.email, s.branch, s.passout_year, s.current_cgpa, s.gender, s.city FROM students s WHERE s.status = 'Active' AND s.id NOT IN (SELECT student_id FROM job_applications WHERE is_selected = 'Yes')";
    }

    if (!empty($_GET['passout_year'])) { $where_clauses[] = "s.passout_year = ?"; $params[] = $_GET['passout_year']; $types .= "i"; }
    if ($_SESSION['admin_role'] === 'sub_admin') { $where_clauses[] = "s.branch = ?"; $params[] = $_SESSION['admin_branch']; $types .= "s"; } 
    elseif (!empty($_GET['branch'])) { $where_clauses[] = "s.branch = ?"; $params[] = $_GET['branch']; $types .= "s"; }
    if (!empty($_GET['gender'])) { $where_clauses[] = "s.gender = ?"; $params[] = $_GET['gender']; $types .= "s"; }
    if (!empty($_GET['city'])) { $where_clauses[] = "s.city = ?"; $params[] = $_GET['city']; $types .= "s"; }
    if (!empty($_GET['cgpa_min'])) { $where_clauses[] = "s.current_cgpa >= ?"; $params[] = $_GET['cgpa_min']; $types .= "d"; }
    if (!empty($_GET['cgpa_max'])) { $where_clauses[] = "s.current_cgpa <= ?"; $params[] = $_GET['cgpa_max']; $types .= "d"; }
    if ($report_type === 'placed') {
        // The form field name is still 'posting_year' but label is 'Placement Year'
        if (!empty($_GET['posting_year'])) { $where_clauses[] = "j.posting_year = ?"; $params[] = $_GET['posting_year']; $types .= "i"; }
        if (!empty($_GET['company_name'])) { $where_clauses[] = "j.company_name = ?"; $params[] = $_GET['company_name']; $types .= "s"; }
    }

    if (!empty($where_clauses)) { $base_sql .= " AND " . implode(" AND ", $where_clauses); }
    
    $order_by_clause = " ORDER BY s.passout_year DESC, s.branch, s.name ASC";
    if ($report_type === 'placed') { $order_by_clause = " ORDER BY j.posting_year DESC, j.company_name ASC, s.branch, s.name ASC"; }

    $stmt = $conn->prepare($base_sql . $order_by_clause);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    if ($report_type === 'placed' && !empty($report_data)) {
        $ctc_values = array_column($report_data, 'CTC');
        $total_placed = count($ctc_values);
        $summary_stats = ['total_placed' => $total_placed, 'max_ctc' => $total_placed ? max($ctc_values) : 0, 'min_ctc' => $total_placed ? min($ctc_values) : 0, 'avg_ctc' => $total_placed ? array_sum($ctc_values) / $total_placed : 0];
        if (empty($_GET['posting_year']) && empty($_GET['company_name'])) { $grouping_level = 'year_company'; }
        elseif (empty($_GET['posting_year'])) { $grouping_level = 'company'; } // Group by company if year is all
        elseif (empty($_GET['company_name'])) { $grouping_level = 'year'; }    // Group by year if company is all
        if ($grouping_level !== 'none') { foreach ($report_data as $row) { if ($grouping_level === 'year_company') { $grouped_data[$row['posting_year']][$row['company_name']][] = $row; } elseif ($grouping_level === 'company') { $grouped_data[$row['company_name']][] = $row; } elseif ($grouping_level === 'year') { $grouped_data[$row['posting_year']][] = $row; } } }
    }

    if (isset($_GET['export'])) {
        $spreadsheet = new Spreadsheet(); $sheet_index = 0;
        if ($report_type === 'placed' && !empty($report_data)) {
            $summarySheet = $spreadsheet->getActiveSheet(); $summarySheet->setTitle('Summary'); $summarySheet->setCellValue('A1', 'Placement Report Summary')->mergeCells('A1:B1')->getStyle('A1')->getFont()->setBold(true)->setSize(16); $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $summaryData = [['Total Students Placed', $summary_stats['total_placed']],['Highest Package (LPA)', $summary_stats['max_ctc']],['Lowest Package (LPA)', $summary_stats['min_ctc']],['Average Package (LPA)', number_format($summary_stats['avg_ctc'], 2)]];
            $summarySheet->fromArray($summaryData, NULL, 'A3')->getStyle('A3:A6')->getFont()->setBold(true); $summarySheet->getColumnDimension('A')->setAutoSize(true); $summarySheet->getColumnDimension('B')->setAutoSize(true);
            $detailSheet = $spreadsheet->createSheet(); $sheet_index = 1;
        } else { $detailSheet = $spreadsheet->getActiveSheet(); }
        $spreadsheet->setActiveSheetIndex($sheet_index)->setTitle('Detailed Report');
        $headers = ['Student Name', 'Enrollment No', 'Email', 'Branch', 'Passout Year', 'CGPA'];
        if ($report_type === 'placed') { $headers = array_merge($headers, ['Placement Year', 'Company Name', 'CTC (LPA)']); }
        $detailSheet->fromArray($headers, NULL, 'A1')->getStyle('A1:' . $detailSheet->getHighestColumn() . '1')->getFont()->setBold(true);
        $row_index = 2;
        foreach ($report_data as $row) {
            $detailSheet->setCellValue('A' . $row_index, $row['name'])->setCellValueExplicit('B' . $row_index, $row['enrollment_no'], DataType::TYPE_STRING)->setCellValue('C' . $row_index, $row['email'])->setCellValue('D' . $row_index, $row['branch'])->setCellValue('E' . $row_index, $row['passout_year'])->setCellValue('F' . $row_index, $row['current_cgpa']);
            if ($report_type === 'placed') { $detailSheet->setCellValue('G' . $row_index, $row['posting_year'])->setCellValue('H' . $row_index, $row['company_name'])->setCellValue('I' . $row_index, $row['CTC']); }
            $row_index++;
        }
        if ($report_type === 'placed') { $detailSheet->getStyle('I')->getNumberFormat()->setFormatCode('#,##0.00" LPA"'); }
        $detailSheet->getStyle('F')->getNumberFormat()->setFormatCode('0.00');
        foreach (range('A', $detailSheet->getHighestColumn()) as $col) { $detailSheet->getColumnDimension($col)->setAutoSize(true); }
        $filename = "Placement_Report_" . ucfirst($report_type) . "_" . date('Y-m-d') . ".xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment;filename="' . $filename . '"'); header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet); $writer->save('php://output'); exit();
    }
}
$conn->close();

function render_report_table($data, $type) { if (empty($data)) return; ?> <table class="table table-striped table-bordered w-100 report-table-class"><thead><tr><th>Name</th><th>Enrollment</th><th>Branch</th><?php if ($type === 'placed'): ?><th>Placement Year</th><th>Company</th><th>CTC (LPA)</th><?php endif; ?><th>Passout Year</th></tr></thead><tbody><?php foreach ($data as $row): ?><tr><td><?= htmlspecialchars($row['name']); ?></td><td><?= htmlspecialchars($row['enrollment_no']); ?></td><td><?= htmlspecialchars($row['branch']); ?></td><?php if ($type === 'placed'): ?><td><?= htmlspecialchars($row['posting_year']); ?></td><td><?= htmlspecialchars($row['company_name']); ?></td><td><?= htmlspecialchars($row['CTC']); ?></td><?php endif; ?><td><?= htmlspecialchars($row['passout_year']); ?></td></tr><?php endforeach; ?></tbody></table><?php }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Generate Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>.card.sub-group { margin-left: 20px; border-left: 3px solid #0d6efd; } .form-label { margin-bottom: 0.5rem; } </style>
</head>
<body class="bg-light">
<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Placement Reporting Engine</h3>
        <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white border-0 pt-3"><h5 class="mb-0">Select Report Criteria</h5></div>
        <div class="card-body p-4">
            <form action="reports.php" method="GET">
                <p class="fw-bold">1. Report Type</p>
                <div class="row mb-3">
                    <div class="col-md-3"><select name="report_type" id="report_type" class="form-select" required><option value="placed" <?php if($report_type == 'placed') echo 'selected'; ?>>Placed Students</option><option value="unplaced" <?php if($report_type == 'unplaced') echo 'selected'; ?>>Unplaced Students</option></select></div>
                </div>
                
                <p class="fw-bold">2. Apply Filters (Optional)</p>
                <!-- Row 1 of filters -->
                <div class="row mb-3">
                    <div class="col-md-3"><label class="form-label">Passout Year</label><select name="passout_year" id="passout_year_filter" class="form-select"><option value="">All</option><?php foreach ($passout_years_data as $year): ?><option value="<?= $year['passout_year']; ?>" <?= (isset($_GET['passout_year']) && $_GET['passout_year'] == $year['passout_year']) ? 'selected' : ''; ?>><?= $year['passout_year']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Branch</label><select name="branch" class="form-select" <?php if ($_SESSION['admin_role'] === 'sub_admin') echo 'disabled'; ?>><option value="">All</option><?php foreach ($branches_data as $branch): ?><option value="<?= $branch['branch']; ?>" <?= ((isset($_SESSION['admin_branch']) && $_SESSION['admin_branch'] === $branch['branch']) || (isset($_GET['branch']) && $_GET['branch'] == $branch['branch'])) ? 'selected' : ''; ?>><?= $branch['branch']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">All</option><option value="Male" <?= (isset($_GET['gender']) && $_GET['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option><option value="Female" <?= (isset($_GET['gender']) && $_GET['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option><option value="Other" <?= (isset($_GET['gender']) && $_GET['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option></select></div>
                    <div class="col-md-3"><label class="form-label">City</label><select name="city" class="form-select"><option value="">All</option><?php foreach ($cities_data as $city): ?><option value="<?= $city['city']; ?>" <?= (isset($_GET['city']) && $_GET['city'] == $city['city']) ? 'selected' : ''; ?>><?= htmlspecialchars($city['city']); ?></option><?php endforeach; ?></select></div>
                </div>
                
                <!-- Row 2 of filters (Placed Student Specific) - CORRECTED ORDER -->
                <div class="row mb-3" id="placed-filters-row">
                    <div class="col-md-3"><label class="form-label">Placement Year</label><select name="posting_year" id="placement_year_filter" class="form-select"><option value="">All</option><?php foreach ($placement_years_data as $year): ?><option value="<?= $year['posting_year']; ?>" <?= (isset($_GET['posting_year']) && $_GET['posting_year'] == $year['posting_year']) ? 'selected' : ''; ?>><?= $year['posting_year']; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><label class="form-label">Company</label><select name="company_name" id="company_filter" class="form-select" disabled><option value="">Select Placement Year first</option></select></div>
                    <div class="col-md-3"><label class="form-label">Min CGPA</label><input type="number" step="0.01" name="cgpa_min" class="form-control" placeholder="e.g., 7.50" value="<?= $_GET['cgpa_min'] ?? ''; ?>"></div>
                    <div class="col-md-3"><label class="form-label">Max CGPA</label><input type="number" step="0.01" name="cgpa_max" class="form-control" placeholder="e.g., 9.00" value="<?= $_GET['cgpa_max'] ?? ''; ?>"></div>
                </div>

                <div class="mt-4 d-flex justify-content-end">
                    <a href="reports.php" class="btn btn-outline-secondary me-2">Clear Filters</a>
                    <button type="submit" name="generate_report" value="1" class="btn btn-primary">Generate Report</button>
                    <?php if ($report_generated && !empty($report_data)): ?><a href="<?= htmlspecialchars($_SERVER['REQUEST_URI']) . '&export=excel'; ?>" class="btn btn-success ms-2">Export to Excel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <?php if ($report_generated): /* This section remains the same */ ?>
        <?php if (empty($report_data)): ?>
            <div class="alert alert-warning mt-4">No records found matching your criteria.</div>
        <?php else: ?>
            <?php if ($report_type === 'placed' && !empty($summary_stats)): ?><div class="card shadow-sm mt-4"><div class="card-header"><h5 class="mb-0">Report Summary</h5></div><div class="card-body"><div class="row text-center"><div class="col"><h5><?= $summary_stats['total_placed']; ?></h5><span class="text-muted">Total Placed</span></div><div class="col"><h5><?= number_format($summary_stats['max_ctc'], 2); ?></h5><span class="text-muted">Highest CTC</span></div><div class="col"><h5><?= number_format($summary_stats['min_ctc'], 2); ?></h5><span class="text-muted">Lowest CTC</span></div><div class="col"><h5><?= number_format($summary_stats['avg_ctc'], 2); ?></h5><span class="text-muted">Average CTC</span></div></div></div></div><?php endif; ?>
            <div class="mt-4"><h4 class="mb-3">Report Results (<?= count($report_data); ?> records found)</h4>
            <?php if ($grouping_level === 'none'): ?><div class="card shadow-sm"><div class="card-body table-responsive"><?php render_report_table($report_data, $report_type); ?></div></div><?php endif; ?>
            <?php if ($grouping_level === 'company'): foreach ($grouped_data as $name => $list): ?><div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0">Company: <?= htmlspecialchars($name); ?></h5></div><div class="card-body table-responsive"><?php render_report_table($list, $report_type); ?></div></div><?php endforeach; endif; ?>
            <?php if ($grouping_level === 'year'): foreach ($grouped_data as $name => $list): ?><div class="card shadow-sm mb-4"><div class="card-header"><h5 class="mb-0">Placement Year: <?= htmlspecialchars($name); ?></h5></div><div class="card-body table-responsive"><?php render_report_table($list, $report_type); ?></div></div><?php endforeach; endif; ?>
            <?php if ($grouping_level === 'year_company'): foreach ($grouped_data as $year => $companies): ?><div class="card shadow-sm mb-4"><div class="card-header"><h4 class="mb-0">Placement Year: <?= htmlspecialchars($year); ?></h4></div><div class="card-body"><?php foreach ($companies as $company => $list): ?><div class="card sub-group mb-3"><div class="card-header bg-light"><h5 class="mb-0">Company: <?= htmlspecialchars($company); ?></h5></div><div class="card-body table-responsive"><?php render_report_table($list, $report_type); ?></div></div><?php endforeach; ?></div></div><?php endforeach; endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('.report-table-class').DataTable({ "order": [], "pageLength": 25 });

    const placementYearFilter = $('#placement_year_filter');
    const companyFilter = $('#company_filter');

    function updateCompanyFilter(preserveCurrent) {
        const selectedYear = placementYearFilter.val();
        companyFilter.prop('disabled', true).html('<option value="">Select Placement Year first</option>');

        if (!selectedYear) return;

        companyFilter.html('<option>Loading companies...</option>');
        $.ajax({
            url: 'reports.php', type: 'GET', data: { action: 'get_companies_for_year', placement_year: selectedYear }, dataType: 'json',
            success: function(response) {
                companyFilter.empty().append('<option value="">All</option>');
                if (response.companies.length > 0) {
                    $.each(response.companies, function(i, item) { companyFilter.append($('<option>', { value: item.company_name, text: item.company_name })); });
                    companyFilter.prop('disabled', false);
                    if (preserveCurrent) { companyFilter.val('<?= $_GET['company_name'] ?? '' ?>'); }
                } else {
                    companyFilter.html('<option value="">No companies found</option>');
                }
            },
            error: function() { companyFilter.html('<option value="">Error loading companies</option>'); }
        });
    }

    placementYearFilter.on('change', function() { updateCompanyFilter(false); });
    
    function initializePage() {
        if ($('#report_type').val() === 'unplaced') {
            $('#placed-filters-row').hide();
        } else {
            $('#placed-filters-row').show();
            if (placementYearFilter.val()) {
                updateCompanyFilter(true);
            }
        }
    }
    $('#report_type').on('change', initializePage);
    initializePage();
});
</script>
</body>
</html>