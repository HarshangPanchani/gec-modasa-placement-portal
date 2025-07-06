<?php
// gecm/admin/profile.php
session_start();
require_once '../db_connect.php';

// Auth check - ensure an admin is logged in, redirect to login if not.
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$action = $_GET['action'] ?? 'display';

// Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_name = $_POST['admin_name'];
    $new_password = $_POST['password'];

    $sql_parts = ["admin_name = ?"];
    $params = [$admin_name];
    $types = "s";

    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $sql_parts[] = "password = ?";
        $params[] = $hashed_password;
        $types .= "s";
    }

    $sql = "UPDATE admins SET " . implode(", ", $sql_parts) . " WHERE id = ?";
    $params[] = $admin_id;
    $types .= "i";

    $stmt_update = $conn->prepare($sql);
    $stmt_update->bind_param($types, ...$params);

    if ($stmt_update->execute()) {
        $_SESSION['admin_name'] = $admin_name; // Update session name
        $_SESSION['message'] = ['text' => 'Profile updated successfully!', 'type' => 'success'];
    } else {
        $_SESSION['message'] = ['text' => 'Error updating profile: ' . $stmt_update->error, 'type' => 'danger'];
    }
    $stmt_update->close();
    header("Location: profile.php");
    exit();
}

// Fetch current admin data
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3>Admin Section</h3>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header"><h4 class="mb-0">My Admin Profile</h4></div>
        <div class="card-body p-4">
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message']['type']; ?>"><?php echo $_SESSION['message']['text']; ?></div>
            <?php unset($_SESSION['message']); endif; ?>

            <?php if ($action === 'edit'): ?>
                <!-- EDIT MODE -->
                <form action="profile.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Admin Name</label>
                        <input type="text" name="admin_name" class="form-control" value="<?php echo htmlspecialchars($admin['admin_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email (Non-Editable)</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password (Leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="profile.php" class="btn btn-secondary">Cancel</a>
                </form>
            <?php else: ?>
                <!-- DISPLAY MODE -->
                <div class="text-end mb-3"><a href="profile.php?action=edit" class="btn btn-primary">Edit My Profile</a></div>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($admin['admin_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($admin['email']); ?></p>
                <p><strong>Role:</strong> <span class="badge bg-success"><?php echo str_replace('_', ' ', ucwords($admin['role'])); ?></span></p>
                <?php if ($admin['role'] === 'sub_admin'): ?>
                    <p><strong>Assigned Branch:</strong> <span class="badge bg-info"><?php echo htmlspecialchars($admin['branch_assigned']); ?></span></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>