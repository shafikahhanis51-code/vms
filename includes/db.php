<?php
require_once __DIR__ . '/config.php';

$conn = new mysqli("localhost", "root", "", "visitor_mgmt");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>
