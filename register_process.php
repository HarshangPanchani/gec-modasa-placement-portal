<?php
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $enrollment = $_POST['enrollment'];
    $email = $_POST['email'];
    $mobile = $_POST['mobile'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hash password securely

    // Check if enrollment or email already exists
    $checkStmt = $conn->prepare("SELECT id FROM students WHERE enrollment_no = ? OR email = ?");
    $checkStmt->bind_param("ss", $enrollment, $email);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        echo "<script>alert('Enrollment number or Email already exists. Please use different credentials.'); window.location.href = 'register.php';</script>";
        $checkStmt->close();
        $conn->close();
        exit();
    }

    $checkStmt->close();

    // Now insert the user
    $stmt = $conn->prepare("INSERT INTO students (name, enrollment_no, email, whatsapp_no, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $enrollment, $email, $mobile, $password);

    if ($stmt->execute()) {
        echo "<script>alert('Registration successful! Please log in.'); window.location.href = 'login.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}
?>
