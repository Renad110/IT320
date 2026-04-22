<?php
$host = "localhost";
$username = "root";
$password = "root";
$database = "nafl_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// مهم جدًا للعربي
$conn->set_charset("utf8mb4");
?>