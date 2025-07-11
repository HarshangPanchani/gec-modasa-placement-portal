<?php
// gecm/admin/edit_admin.php
session_start();
require_once '../db_connect.php';

// --- SECURITY CHECKS ---
// 1. Must be logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
// 2. Must be a SUPER ADMIN to edit other admins
if ($_SESSION['admin_role'] !== 'super_admin') {
    $_SESSION['message'] = ['text' => 'Access Denied: You do not have permission to edit admins.', 'type' => 'danger'];
    header("Location: dashboard.php");
    exit();
}
// 3. Must have an ID in the URL
if (!isset($_GET['id'])) {
    header("Location: manage_admins.php");
    exit();
}
$admin_id_to_edit = (int)$_GET['id'];

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_name = $_POST['admin_name'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $branch_assigned = ($role === 'sub_admin') ? $_POST['branch_assigned'] : null;
    $new_password = $_POST['password'];

    // Prevent a super admin from accidentally demoting themselves if they are the last one
    if ($admin_id_to_edit == $_SESSION['admin_id'] && $role === 'sub_admin') {
        $stmt_check_super = $conn->prepare("SELECT COUNT(*) as super_count FROM admins WHERE role = 'super_admin'");
        $stmt_check_super->execute();
        $super_count = $stmt_check_super->get_result()->fetch_assoc()['super_count'];
        if ($super_count <= 1) {
            $_SESSION['message'] = ['text' => 'Cannot demote the last Super Admin.', 'type' => 'danger'];
            header("Location: edit_admin.php?id=" . $admin_id_to_edit);
            exit();
        }
    }

    // Check if new email is taken by ANOTHER admin
    $stmt_check_email = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
    $stmt_check_email->bind_param("si", $email, $admin_id_to_edit);
    $stmt_check_email->execute();
    if ($stmt_check_email->get_result()->num_rows > 0) {
        $_SESSION['message'] = ['text' => 'Error: This email is already used by another admin.', 'type' => 'danger'];
    } else {
        $sql_parts = ["admin_name = ?", "email = ?", "role = ?", "branch_assigned = ?"];
        $params = [$admin_name, $email, $role, $branch_assigned];
        $types = "ssss";

        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
        
        $sql = "UPDATE admins SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $params[] = $admin_id_to_edit;
        $types .= "i";
        
        $stmt_update = $conn->prepare($sql);
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['text' => 'Admin details updated successfully.', 'type' => 'success'];
        } else {
            $_SESSION['message'] = ['text' => 'Error updating admin: ' . $stmt_update->error, 'type' => 'danger'];
        }
        header("Location: manage_admins.php");
        exit();
    }
    // Redirect back to the edit page on email validation failure
    header("Location: edit_admin.php?id=" . $admin_id_to_edit);
    exit();
}


// --- FETCH DATA FOR THE ADMIN TO BE EDITED ---
$stmt = $conn->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->bind_param("i", $admin_id_to_edit);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $_SESSION['message'] = ['text' => 'Admin not found.', 'type' => 'danger'];
    header("Location: manage_admins.php");
    exit();
}
$admin = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Edit Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3>Editing Admin: <?php echo htmlspecialchars($admin['admin_name']); ?></h3>
    <hr>
     <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message']['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']['text']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message']); endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form action="edit_admin.php?id=<?php echo $admin_id_to_edit; ?>" method="POST" id="admin-form">
                <div class="mb-3">
                    <label class="form-label">Admin Name *</label>
                    <input type="text" name="admin_name" class="form-control" value="<?php echo htmlspecialchars($admin['admin_name']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Email *</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password (Leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter new password to change">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role *</label>
                    <select name="role" id="role-select" class="form-select" required>
                        <option value="sub_admin" <?php if ($admin['role'] === 'sub_admin') echo 'selected'; ?>>Sub Admin</option>
                        <option value="super_admin" <?php if ($admin['role'] === 'super_admin') echo 'selected'; ?>>Super Admin</option>
                    </select>
                </div>
                <div class="mb-3" id="branch-assignment">
                    <label class="form-label">Assign Branch *</label>
                    <select name="branch_assigned" class="form-select">
                        <option value="">Choose a branch...</option>
                        <?php 
                            $branches = ['Computer Engineering', 'Information Technology', 'Mechanical Engineering', 'Civil Engineering', 'Electrical Engineering', 'Electronics & Communication'];
                            foreach ($branches as $branch) {
                                $selected = ($admin['branch_assigned'] === $branch) ? 'selected' : '';
                                echo "<option value=\"$branch\" $selected>$branch</option>";
                            }
                        ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="manage_admins.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<script>
// Same JavaScript as the add_admin page to show/hide the branch dropdown
const roleSelect = document.getElementById('role-select');
const branchDiv = document.getElementById('branch-assignment');

function toggleBranchVisibility() {
    if (roleSelect.value === 'sub_admin') {
        branchDiv.style.display = 'block';
    } else {
        branchDiv.style.display = 'none';
    }
}

// Run on page load
toggleBranchVisibility();

// Run on change
roleSelect.addEventListener('change', toggleBranchVisibility);
</script>

</body>
</html>