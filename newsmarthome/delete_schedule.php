<?php
// delete_schedule.php

// Database connection settings
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$scheduleId = $_GET['id'] ?? '';

if (empty($scheduleId)) {
    header("Location: schedule.php");
    exit;
}

// Use a prepared statement to avoid SQL injection.
$stmt = $conn->prepare("DELETE FROM schedules WHERE id = ?");
$stmt->bind_param("i", $scheduleId);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: schedule.php?msg=deleted");
    exit;
} else {
    echo "Error deleting schedule: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
