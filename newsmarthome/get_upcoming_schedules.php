<?php
// get_upcoming_schedules.php

header("Content-Type: application/json");

// Database connection settings
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
    exit;
}

$upcomingSchedules = [];
$sql = "SELECT * FROM schedules WHERE scheduled_time >= NOW() AND executed = 0 ORDER BY scheduled_time ASC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $upcomingSchedules[] = $row;
    }
}
$conn->close();

echo json_encode(["status" => "success", "schedules" => $upcomingSchedules]);
?>
