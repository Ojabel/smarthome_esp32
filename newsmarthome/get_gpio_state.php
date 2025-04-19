<?php
//get_gpio_state.php
// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle GET requests for GPIO state
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $board_id = $_GET['board_id'] ?? '';
    
    if (!empty($board_id)) {
        $sql = "SELECT * FROM devices WHERE board_id = '$board_id'";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $gpio_state = $row['gpio_state']; // GPIO state stored as a string (e.g., "010")
            echo json_encode(["gpio_state" => $gpio_state]);
        } else {
            echo json_encode(["status" => "error", "message" => "Board not found"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No board_id provided"]);
    }
    exit;
}


?><?php
// get_gpio_state.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // Allow API calls from any origin

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// Handle GET requests for GPIO state
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $board_id = $_GET['board_id'] ?? '';

    if (!empty($board_id)) {
        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("SELECT gpio_state FROM devices WHERE board_id = ?");
        $stmt->bind_param("s", $board_id);
        $stmt->execute();
        $stmt->bind_result($gpio_state);
        $stmt->fetch();

        if ($gpio_state !== null) {
            echo json_encode(["status" => "success", "gpio_state" => $gpio_state]);
        } else {
            echo json_encode(["status" => "error", "message" => "Board not found"]);
        }

        $stmt->close();
    } else {
        echo json_encode(["status" => "error", "message" => "No board_id provided"]);
    }
}

$conn->close();
?>
