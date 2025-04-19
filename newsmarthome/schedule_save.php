<?php
// schedule_save.php

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

$board_id       = $_POST['board_id'] ?? '';
$gpio           = $_POST['gpio'] ?? '';
$scheduled_time = $_POST['scheduled_time'] ?? '';
$action         = $_POST['action'] ?? '';

if (empty($board_id) || empty($gpio) || empty($scheduled_time) || empty($action)) {
    echo json_encode(["status" => "error", "message" => "All fields are required."]);
    exit;
}

// Insert the new schedule into the schedules table.
$stmt = $conn->prepare("INSERT INTO schedules (board_id, gpio, scheduled_time, action) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $board_id, $gpio, $scheduled_time, $action);

if ($stmt->execute()) {
    // After inserting the schedule, update the devices table to reflect the new expected state.
    $result = $conn->query("SELECT gpio_pins, gpio_state FROM devices WHERE board_id = '$board_id'");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $pins = explode(',', $row['gpio_pins']);           // Array of available GPIOs (as strings)
        $stateArr = str_split($row['gpio_state']);           // Current state string as an array
        
        // Find the index of the scheduled gpio in the pins array.
        $index = array_search(trim($gpio), $pins);
        /*if ($index !== false) {
            // Update the state based on the action: "on" -> '1', "off" -> '0'
            $stateArr[$index] = ($action === "on") ? '1' : '0';
            $new_state = implode('', $stateArr);
            
            // Update the devices table with the new gpio_state.
            $update_stmt = $conn->prepare("UPDATE devices SET gpio_state = ? WHERE board_id = ?");
            $update_stmt->bind_param("ss", $new_state, $board_id);
            $update_stmt->execute();
            $update_stmt->close();
        }*/
    }
    
    echo json_encode(["status" => "success", "message" => "Schedule saved successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Error saving schedule: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
