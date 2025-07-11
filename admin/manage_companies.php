<?php
// gecm/admin/manage_companies.php
session_start();
require_once '../db_connect.php';

// Auth check
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch all companies
$sql = "SELECT id, company_name, hr_name, hr_email, registration_last_date FROM companies ORDER BY company_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$companies = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Companies</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Manage Companies</h3>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Registered Company List</h3>
                <div>
                    <a href="add_company.php" class="btn btn-success">Add Job Company</a>
                </div>
            </div>
            <div class="table-responsive">
                <table id="companies-table" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>HR Name</th>
                            <th>HR Email</th>
                            <th>Registration Ends</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                            <td><?php echo htmlspecialchars($company['hr_name']); ?></td>
                            <td><?php echo htmlspecialchars($company['hr_email']); ?></td>
                            <td><?php echo htmlspecialchars($company['registration_last_date']); ?></td>
                            <td class="text-nowrap">
                                <a href="view_company.php?id=<?php echo $company['id']; ?>" class="btn btn-sm btn-info">View</a>
                                <a href="edit_company.php?id=<?php echo $company['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="delete_company.php?id=<?php echo $company['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this company? This is permanent.');">Delete</a>
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
    $('#companies-table').DataTable();
});
</script>

</body>
</html>