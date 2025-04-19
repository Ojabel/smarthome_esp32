<?php 
// index.php

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle GPIO state updates & new board registration
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $board_id = $_POST['board_id'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $state = $_POST['state'] ?? '';

    if (!empty($board_id)) {
        $sql = "SELECT * FROM devices WHERE board_id = '$board_id'";
        $result = $conn->query($sql);

        if ($result->num_rows == 0) {
            // Create a new board with default GPIO state (All OFF)
            $gpio_pins = $_POST['gpio_pins'] ?? '';
            $initial_state = str_repeat('0', count(explode(',', $gpio_pins)));
            $category = $_POST['category'] ?? 'General';

            $conn->query("INSERT INTO devices (board_id, gpio_pins, gpio_state, category) VALUES ('$board_id', '$gpio_pins', '$initial_state', '$category')");
            echo json_encode(["status" => "new_board_added", "board_id" => $board_id]);
        } else {
            $row = $result->fetch_assoc();
            $gpio_state = str_split($row['gpio_state']);
            $pins = explode(',', $row['gpio_pins']);
            $index = array_search($pin, $pins);
            if ($index !== false) {
                $gpio_state[$index] = $state;
                $new_state = implode('', $gpio_state);
                $update_sql = "UPDATE devices SET gpio_state = '$new_state' WHERE board_id = '$board_id'";
                if ($conn->query($update_sql)) {
                    echo json_encode(["status" => "ok", "gpio_state" => $new_state]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Failed to update GPIO"]);
                }
            }
        }
    }
    exit;
}

// Handle board deletion
if (isset($_GET['delete_board_id'])) {
    $board_id_to_delete = $_GET['delete_board_id'];
    $delete_sql = "DELETE FROM devices WHERE board_id = '$board_id_to_delete'";
    if ($conn->query($delete_sql)) {
        echo "<script>
          alert('Device deleted successfully');
          window.location.href = 'index.php'; // Redirect after deletion
        </script>";
    } else {
        echo "<script>alert('Error deleting device'); window.location.href='index.php';</script>";
    }
    exit;
}

// Fetch all devices to display on the frontend
$devices = [];
$result = $conn->query("SELECT * FROM devices");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $devices[] = $row;
    }
}
$initialCount = count($devices);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Smart Home Dashboard</title>
  <link rel="icon" type="image/png" href="3.png">
  <!-- Bootstrap & Font Awesome -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
  <style>
    /* Background with blur overlay */
    body {
      background: url('bg2.jpg') no-repeat center center fixed;
      background-size: cover;
      height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      transition: background-color 0.3s, color 0.3s;
      

    }
    body::before {
      content: '';
      position: fixed;
      top: 0; 
      left: 0;
      width: 100%; 
      height: 100%;
      background: rgba(0, 0, 0, 0.55);
      filter: blur(8px);
      z-index: -1;
    }

    /* Fixed Welcome Header */
    .mainhead {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      background-color: rgba(20, 15, 15, 0.7);
      padding: 10px;
      z-index: 1001;
      display: flex;
      justify-content: center;
    }
    .mainhead h4 {
      color: whitesmoke; 
      margin: 0;
    }
    
    /* Main Container */
    .container-content {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      min-height: calc(80vh - 60px); /* Adjust for fixed header height */
      padding: 20px;
      position: relative;
      z-index: 1;
      margin-top: 60px; /* Space for fixed header */
    }

    /* Card Grid Layout */
    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 20px;
      width: 100%;
      max-width: 1400px;
    }

    /* Card Styling */
    .card {
      background: rgba(255, 255, 255, 0.2);
      border: none;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(0,0,0,0.15);
      transition: all 0.3s ease;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.25);
    }

    /* Card Header */
    .card-header {
      background: linear-gradient(135deg, rgba(118, 119, 118, 0.6), rgb(133, 160, 134));
      padding: 15px 20px;
      color: #fff;
      font-weight: bold;
      font-size: 1.1rem;
    }

    /* Card Body */
    .card-body {
      padding: 20px;
      color: #fff;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    /* Toggle Container */
    #togglecl {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px;
      border: 1px solid rgba(255, 255, 255, 0.4);
      border-radius: 20px;
      flex-wrap: wrap;
      justify-content: center;
    }

    /* Toggle Switch */
    .switch {
      position: relative;
      display: inline-block;
      width: 100px;
      height: 55px;
      margin: 10px;
    }
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: 0.4s;
      border-radius: 55px;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 45px;
      width: 45px;
      left: 5px;
      bottom: 5px;
      background-color: white;
      transition: 0.4s;
      border-radius: 50%;
    }
    input:checked + .slider {
      background-color: rgb(7, 41, 68);
    }
    input:checked + .slider:before {
      transform: translateX(45px);
    }

    /* Toggle Icon using plug (socket) icon */
    .state-icon {
      font-size: 1.8rem;
      transition: color 0.4s;
    }
    .state-on {
      color: #4caf50;
    }
    .state-off {
      color: #ccc;
    }

    /* Card Footer for Actions */
    .card-footer {
      background-color: rgba(0, 0, 0, 0.3);
      padding: 10px 20px;
      border-top: 1px solid rgb(255, 252, 252);
      text-align: right;
    }
    .card-footer button {
      font-size: 0.9rem;
    }

    /* Floating Global Settings Button */
    .settings-btn-global {
      position: fixed;
      bottom: 30px;
      right: 30px;
      background-color: rgba(8, 8, 8, 0.7);
      border: none;
      border-radius: 50%;
      color: #fff;
      width: 50px;
      height: 50px;
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 1000;
    }
    /* Global Settings Panel */
    .settings-panel-global {
      position: fixed;
      bottom: 90px;
      right: 30px;
      background: rgba(56, 17, 17, 0.73);
      color: whitesmoke;
      border-radius: 8px;
      padding: 10px;
      display: none;
      flex-direction: column;
      gap: 10px;
      z-index: 1000;
    }
    .settings-panel-global button,
    .settings-panel-global select {
      background: transparent;
      border: none;
      color: whitesmoke;
      padding: 5px 10px;
      border-radius: 5px;
      cursor: pointer;
      transition: background 0.3s;
    }
    .settings-panel-global button:hover,
    .settings-panel-global select:hover {
      background: rgba(255,255,255,0.1);
    }
    #deviceFilter option {
        background: rgba(124, 13, 13, 0.77);
    }

    /* Per-card settings dropdown */
    .card-settings {
      position: absolute;
      top: 10px;
      right: 10px;
      z-index: 10;
    }
    .card-settings .dropdown-menu {
      min-width: 100px;
    }

    /* Notification Card Styling */
    .notification-card .card {
      border: 2px solid transparent;
      box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    }
    /* Style for delete notifications */
    .notification-card .notification-delete {
      background: linear-gradient(135deg, #ff4d4d, #ff8080);
      border-color: #ff1a1a;
      color: #fff;
    }
    /* Style for info notifications */
    .notification-card .notification-info {
      background: linear-gradient(135deg, rgba(138, 54, 33, 0.65), #64b5f6);
      border-color: rgb(247, 247, 247);
      color: #fff;
    }
    #device-list {
      width: 100%;
      gap: 20px;
      justify-content: space-around;
    }

    /* Responsive Toggle Switch adjustments */
    @media (max-width: 490px) {

      body {
      background: url('bg2.jpg') no-repeat center center fixed;
      background-size: cover;
      height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
      transition: background-color 0.3s, color 0.3s;
      position: fixed;
      

    }
     

      .card-header {
      font-size: 0.8rem;
    }
      .switch {
        width: 50px;
        height: 30px;
        margin: 5px;

      }
      
     
      .slider:before {
      position: absolute;
      content: "";
      height: 20px;
      width: 20px;
      bottom: 5px;
    }
    input:checked + .slider:before {
      transform: translateX(20px);
    }
    
      .state-icon {
        font-size: 1.1rem;
      }
      #togglecl {
        padding: 5px;
        margin: 1px;
      }

      .dropdown-item{
        font-size: 0.7em;
      }

      
    .settings-btn-global {
      
      width: 30px;
      height: 30px;
      font-size: 1rem;
    }
    .settings-panel-global{
      font-size: 0.8em;
    }
      
    }
  </style>
</head>
<body class="light-mode">

 <!-- Navigation Bar -->
 <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
      <a class="navbar-brand" href="index.php">
        <i class="fas fa-home"></i> Devices
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" 
              aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNavDropdown">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="scheduleDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-calendar-alt"></i> Scheduling
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="scheduleDropdown">
              <li><a class="dropdown-item" href="schedule.php">
                <i class="fas fa-clock"></i> View Schedules
              </a></li>
            </ul>
          </li>
        </ul>
      </div>
    </div>
  </nav>
  
  <!-- Fixed Welcome Header -->
  <div class="mainhead">
    <h4>Welcome home</h4>
  </div>
  
  <!-- Global Settings -->
  <button class="settings-btn-global" id="globalSettingsBtn">
    <i class="fas fa-ellipsis-v"></i>
  </button>
  <div class="settings-panel-global" id="globalSettingsPanel">
    <button onclick="toggleTheme()"><i class="fas fa-adjust"></i> Toggle Theme</button>
    <button onclick="toggleLayout()"><i class="fas fa-th-large"></i> Toggle Layout</button>
    <!-- Filter included in the global settings -->
    <select id="deviceFilter" onchange="filterDevices()">
      <option value="">All Devices</option>
      <option value="General">General</option>
      <option value="Lights">Lights</option>
      <option value="Security">Security</option>
    </select>
  </div>

  <!-- Main container -->
  <div class="container container-content">
    <!-- Notification area -->
    <div id="notification" class="notification-card" style="display:none;"></div>

    <!-- Devices Listing -->
    <div id="device-list" class="row grid-layout">
      <?php if (!empty($devices)): ?>
        <?php foreach ($devices as $device): ?>
          <div class="col-sm-12 col-md-6 col-lg-4 device-item" data-category="<?php echo htmlspecialchars($device['category']); ?>">
            <div class="card">
              <!-- Card Header with Gradient -->
              <div class="card-header">
                Board ID: <?php echo htmlspecialchars($device['board_id']); ?> &mdash; <?php echo htmlspecialchars($device['category']); ?>
              </div>
              <div class="card-body">
                <!-- Per-card settings dropdown -->
                <div class="card-settings dropdown">
                  <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="cardSettingsBtn<?php echo $device['board_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-ellipsis-h"></i>
                  </button>
                  <ul class="dropdown-menu" aria-labelledby="cardSettingsBtn<?php echo $device['board_id']; ?>">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="confirmDelete('<?php echo $device['board_id']; ?>', this)">Delete</a></li>
                  </ul>
                </div>
                <!-- Device Toggles -->
                <?php 
                  $pins = explode(',', $device['gpio_pins']);
                  $states = str_split($device['gpio_state']);
                ?>
                <?php foreach ($pins as $i => $pin): ?>
                  <div class="d-grid" id="togglecl">
                    <label class="switch">
                      <input type="checkbox" class="toggle-gpio"
                             data-board-id="<?php echo $device['board_id']; ?>"
                             data-pin="<?php echo $pin; ?>"
                             <?php echo ($states[$i] == '1') ? 'checked' : ''; ?>>
                      <span class="slider"></span>
                    </label>
                    <i class="state-icon <?php echo ($states[$i] == '1') ? 'state-on fas fa-plug' : 'state-off fas fa-plug'; ?>"></i>
                  </div>
                <?php endforeach; ?>
              </div>
              <!-- Card Footer for additional actions -->
              <div class="card-footer">
                <small class="text-muted">Last updated: <?php echo date("Y-m-d H:i:s"); ?></small>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <p class="text-center text-warning">No devices available.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Global settings panel toggle
    const globalSettingsBtn = document.getElementById('globalSettingsBtn');
    const globalSettingsPanel = document.getElementById('globalSettingsPanel');
    globalSettingsBtn.addEventListener('click', () => {
      globalSettingsPanel.style.display = (globalSettingsPanel.style.display === 'flex') ? 'none' : 'flex';
    });

    // Toggle GPIO state and update icon accordingly
    document.querySelectorAll('.toggle-gpio').forEach(toggle => {
      toggle.addEventListener('change', function() {
        const boardId = this.getAttribute('data-board-id');
        const pin = this.getAttribute('data-pin');
        const newState = this.checked ? '1' : '0';
        const icon = this.parentElement.parentElement.querySelector('.state-icon');
        fetch('index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `board_id=${boardId}&pin=${pin}&state=${newState}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'ok') {
            if(newState === '1'){
              icon.classList.remove('state-off');
              icon.classList.add('state-on');
            } else {
              icon.classList.remove('state-on');
              icon.classList.add('state-off');
            }
          } else {
            alert('Failed to update GPIO state');
          }
        });
      });
    });

    // Confirm deletion with fade-out animation and show a notification card
    function confirmDelete(boardId) {
      if (confirm("Are you sure you want to delete this device?")) {
        window.location.href = `?delete_board_id=${boardId}`;
      }
    }

    // Toggle Dark/Light theme
    function toggleTheme() {
      document.body.classList.toggle('dark-mode');
      document.body.classList.toggle('light-mode');
      globalSettingsPanel.style.display = 'none';
    }

    // Toggle between Grid and List layout
    function toggleLayout() {
      const deviceList = document.getElementById('device-list');
      if (deviceList.classList.contains('grid-layout')) {
        deviceList.classList.remove('grid-layout');
        deviceList.classList.add('list-layout');
        document.querySelectorAll('.device-item').forEach(item => {
          item.classList.remove('col-md-6','col-lg-4');
          item.classList.add('col-12');
        });
      } else {
        deviceList.classList.remove('list-layout');
        deviceList.classList.add('grid-layout');
        document.querySelectorAll('.device-item').forEach(item => {
          item.classList.remove('col-12');
          item.classList.add('col-sm-12','col-md-6','col-lg-4');
        });
      }
      globalSettingsPanel.style.display = 'none';
    }

    // Filter devices by category based on the global settings filter
    function filterDevices() {
      const selectedCategory = document.getElementById('deviceFilter').value;
      document.querySelectorAll('.device-item').forEach(item => {
        const category = item.getAttribute('data-category');
        item.style.display = (selectedCategory === '' || selectedCategory === category) ? '' : 'none';
      });
    }

    // Function to show a notification card (for device addition or deletion)
    function showNotification(message, type = 'info') {
      const notification = document.getElementById('notification');
      let notificationClass = 'notification-info';
      if (type === 'delete') {
        notificationClass = 'notification-delete';
      }
      notification.innerHTML = `<div class="card ${notificationClass} mb-3"><div class="card-body">${message}</div></div>`;
      notification.style.display = 'block';
      setTimeout(() => { notification.style.display = 'none'; }, 3000);
    }

    // Immediate Notifications & Auto-Reload:
    let initialCount = <?php echo $initialCount; ?>;
    setInterval(() => {
      fetch('device_count.php')
        .then(res => res.json())
        .then(data => {
          if(data.count > initialCount) {
            showNotification("A new device has been added. Reloading...", "success");
            setTimeout(() => {
              location.reload();
            }, 2000);
          }
          if(data.count < initialCount) {
            showNotification("A device has been removed. Reloading...", "warning");
            setTimeout(() => {
              location.reload();
            }, 2000);
          }
        })
        .catch(err => console.error(err));
    }, 3000); // Poll every 3 seconds
  </script>
  <!-- Bootstrap Bundle with Popper -->

</body>
</html>
