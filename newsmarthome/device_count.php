<?php
// device_count.php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed"]));
}
$result = $conn->query("SELECT COUNT(*) AS total FROM devices");
$row = $result->fetch_assoc();
echo json_encode(["count" => (int)$row['total']]);
