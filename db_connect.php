<?php
// db_connect.php

$servername = "localhost"; 
$username = "root";
$password = "";
$dbname = "gecm_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!$conn->set_charset("utf8mb4")) {
    // Error handling if needed
}
?>