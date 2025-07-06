<?php
// gecm/admin/manage_admins.php
session_start();
require_once '../db_connect.php';

// --- SECURITY CHECK: SUPER ADMIN ONLY ---
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'super_admin') {
    // You can redirect to dashboard or show an error
    $_SESSION['message'] = ['text' => 'Access Denied: You do not have permission to view this page.', 'type' => 'danger'];
    header("Location: dashboard.php");
    exit();
}

// Fetch all admins
$sql = "SELECT id, admin_name, email, role, branch_assigned FROM admins ORDER BY role, admin_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$admins = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Manage Admins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Manage Admins</h3>
        <div>
            <a href="add_admin.php" class="btn btn-success">＋ Create New Admin</a>
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Assigned Branch</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($admin['admin_name']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><span class="badge bg-<?php echo $admin['role'] === 'super_admin' ? 'danger' : 'primary'; ?>"><?php echo str_replace('_', ' ', ucwords($admin['role'])); ?></span></td>
                            <td><?php echo htmlspecialchars($admin['branch_assigned'] ?? 'N/A'); ?></td>
                            <td class="text-nowrap">
                                <a href="edit_admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <?php // Prevent super admin from deleting themselves
                                if ($_SESSION['admin_id'] !== $admin['id']): ?>
                                    <a href="delete_admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>