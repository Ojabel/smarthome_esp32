<?php
// mark_schedule_executed.php

header("Content-Type: application/json");

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

$scheduleId = $_POST['id'] ?? '';
if (empty($scheduleId)) {
    echo json_encode(["status" => "error", "message" => "No schedule id provided"]);
    exit;
}

$stmt = $conn->prepare("UPDATE schedules SET executed = 1 WHERE id = ?");
$stmt->bind_param("i", $scheduleId);
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Schedule marked as executed"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update schedule"]);
}
$stmt->close();
$conn->close();
?>
