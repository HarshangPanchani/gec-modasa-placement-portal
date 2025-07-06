<?php
// gecm/admin/manage_students.php
session_start();
require_once '../db_connect.php';

// Auth check: Ensure an admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// --- Role-Based Data Fetching ---
$sql = "SELECT id, name, enrollment_no, email, branch, passout_year FROM students";
$params = [];
$types = "";

// If the logged-in admin is a SUB_ADMIN, modify the query to filter by their branch
if ($_SESSION['admin_role'] === 'sub_admin') {
    $sql .= " WHERE branch = ?";
    $params[] = $_SESSION['admin_branch'];
    $types .= "s";
}

$sql .= " ORDER BY branch, name"; // Order the results for clarity

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include DataTables for powerful table features -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Manage Students</h3>
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
            <h5 class="card-title">
                Student List 
                <?php if ($_SESSION['admin_role'] === 'sub_admin'): ?>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($_SESSION['admin_branch']); ?> Branch</span>
                <?php else: ?>
                    <span class="badge bg-success">All Branches</span>
                <?php endif; ?>
            </h5>
            <div class="table-responsive">
                <table id="students-table" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Enrollment No</th>
                            <th>Email</th>
                            <th>Branch</th>
                            <th>Passout Year</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                            <td><?php echo htmlspecialchars($student['enrollment_no']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo htmlspecialchars($student['branch']); ?></td>
                            <td><?php echo htmlspecialchars($student['passout_year']); ?></td>
                            <td class="text-nowrap">
                                <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">View</a>
                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="delete_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript libraries for DataTables -->
<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    $('#students-table').DataTable({
        "order": [[ 3, "asc" ]] // Default sort by branch column
    });
});
</script>

</body>
</html>