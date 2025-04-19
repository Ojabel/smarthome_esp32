<?php
// get_schedule.php

header("Content-Type: application/json");

// Database connection settings
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error", 
        "message" => "Connection failed: " . $conn->connect_error
    ]);
    exit;
}

$board_id = $_GET['board_id'] ?? '';
if (empty($board_id)) {
    echo json_encode([
        "status" => "error", 
        "message" => "No board_id provided"
    ]);
    exit;
}

// Query: Get only due schedules for the board (not executed and scheduled_time <= NOW())
$sql = "SELECT * FROM schedules 
        WHERE board_id = '$board_id' 
          AND executed = 0 
          AND scheduled_time <= NOW() 
        ORDER BY scheduled_time ASC";
$result = $conn->query($sql);
$schedules = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}

// For each due schedule, update the device state and mark the schedule as executed.
foreach ($schedules as $schedule) {
    $gpio = trim($schedule['gpio']);
    $action = $schedule['action']; // "on" or "off"
    
    // Retrieve the device's current GPIO pins and state.
    $sqlDevice = "SELECT gpio_pins, gpio_state FROM devices WHERE board_id = '$board_id'";
    $resultDevice = $conn->query($sqlDevice);
    if ($resultDevice && $resultDevice->num_rows > 0) {
        $device = $resultDevice->fetch_assoc();
        // Split the comma-separated GPIO pins and trim each element.
        $pins = array_map('trim', explode(',', $device['gpio_pins']));
        $stateArr = str_split($device['gpio_state']);
        
        // Find the index of the scheduled gpio.
        $index = array_search($gpio, $pins);
        if ($index !== false) {
            // Update the state based on the action.
            $stateArr[$index] = ($action === "on") ? '1' : '0';
            $new_state = implode('', $stateArr);
            // Update the devices table with the new gpio_state.
            $updateSQL = "UPDATE devices SET gpio_state = '$new_state' WHERE board_id = '$board_id'";
            $conn->query($updateSQL);
        }
    }
    
    // Mark the schedule as executed.
    $scheduleId = intval($schedule['id']);
    $updateScheduleSQL = "UPDATE schedules SET executed = 1 WHERE id = $scheduleId";
    $conn->query($updateScheduleSQL);
}

$conn->close();

// Return the due schedules (they have now been processed).
echo json_encode([
    "status" => "success", 
    "schedules" => $schedules
]);
?>
