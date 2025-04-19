<?php
// schedule.php

// Database connection settings
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'new_smart_home';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch available boards from the devices table
$boards = [];
$result = $conn->query("SELECT board_id, gpio_pins FROM devices");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $boards[] = $row;
    }
}
$conn->close();

// (Optionally, you can pass a message via URL for deletion; toast will be shown below if msg=deleted)
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Schedule Actions - Smart Home</title>
  <!-- Bootstrap CSS & Font Awesome -->
   
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      padding-top: 70px;
      background: url('bg2.jpg') no-repeat center center fixed;
      background-size: cover;
      font-family: 'Segoe UI', sans-serif;
      color: #fff;
    }
    .content-container {
      background: rgba(0, 0, 0, 0.55);
      border-radius: 8px;
      padding: 20px;
      margin-bottom: 20px;
    }
    .toast-container {
      position: fixed;
      top: 80px;
      right: 20px;
      z-index: 1100;
    }
    .board-card {
      cursor: pointer;
      transition: transform 0.3s;
      background: #ffffff; /* White background for board cards */
      color: #000;
      border-radius: 5px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    .board-card:hover {
      transform: scale(1.03);
    }
    .schedule-item {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 5px;
      padding: 10px;
      margin-bottom: 10px;
      position: relative;
    }
    .schedule-item h6, .schedule-item p {
      margin: 0;
    }
    .delete-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      color: #ff6b6b;
      cursor: pointer;
    }
    .modal-body , .modal-title {
      color: #000;
    }


     /* Responsive Toggle Switch adjustments */
     @media (max-width: 490px) {



}
  </style>
</head>
<body>

  
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

  <!-- Toast Container for popup messages -->
  <div class="toast-container"></div>
  
  <div class="container content-container">
    <h2 class="mb-4">Schedule Actions</h2>
    
    <!-- Display Boards -->
   <!-- <h4>Devices</h4> -->
    <div class="row">
      <?php if (!empty($boards)): ?>
        <?php foreach ($boards as $board): ?>
          <div class="col-md-4 mb-3">
            <div class="card board-card" data-board-id="<?php echo htmlspecialchars($board['board_id']); ?>" data-gpio-pins="<?php echo htmlspecialchars($board['gpio_pins']); ?>">
              <div class="card-body">
                <h5 class="card-title">Device: <?php echo htmlspecialchars($board['board_id']); ?></h5>
                <p class="card-text">Outlets: <?php echo htmlspecialchars($board['gpio_pins']); ?></p>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No boards registered.</p>
      <?php endif; ?>
    </div>
    
    <!-- Display Upcoming Schedules -->
    <h4 class="mt-5">Upcoming Schedules</h4>
    <div id="scheduleList">
      <!-- This container will be populated by AJAX -->
    </div>
    
    <!-- Modal for Scheduling an Action -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="scheduleForm" method="POST">
            <div class="modal-header">
              <h5 class="modal-title" id="scheduleModalLabel">Schedule Action</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <!-- Hidden fields for board ID and GPIO -->
              <input type="hidden" name="board_id" id="modalBoardId">
              <input type="hidden" name="gpio" id="modalGPIO">
              
              <!-- GPIO selection area -->
              <div id="gpioSelection" class="mb-3"></div>
              
              <div class="mb-3">
                <label for="scheduleDateTime" class="form-label">Select Date &amp; Time</label>
                <input type="datetime-local" class="form-control" id="scheduleDateTime" name="scheduled_time" required>
              </div>
              <div class="mb-3">
                <label for="action" class="form-label">Action</label>
                <select class="form-select" id="action" name="action" required>
                  <option value="">Select Action</option>
                  <option value="on">Turn ON</option>
                  <option value="off">Turn OFF</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Schedule</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Deletion</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            Are you sure you want to delete this schedule?
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="deleteConfirmButton">Delete</button>
          </div>
        </div>
      </div>
    </div>
    
  </div> <!-- End Container -->
  
  <!-- Bootstrap Bundle with Popper (JS) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Global variable to store the schedule ID to delete.
    let scheduleToDelete = null;
    
    // Function to show the delete confirmation modal.
    function confirmDeletion(scheduleId) {
      scheduleToDelete = scheduleId;
      var deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
      deleteModal.show();
    }
    
    // When the user clicks "Delete" in the confirmation modal.
    document.getElementById('deleteConfirmButton').addEventListener('click', function() {
      if (scheduleToDelete) {
        window.location.href = "delete_schedule.php?id=" + scheduleToDelete;
      }
    });
    
    // Handle schedule form submission using AJAX.
    document.getElementById('scheduleForm').addEventListener('submit', function(e) {
      e.preventDefault(); // Prevent default form submission
      
      const form = e.target;
      const formData = new FormData(form);
      
      fetch('schedule_save.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        // Create or update a toast popup in the .toast-container.
        let toastContainer = document.querySelector('.toast-container');
        let toastEl = document.getElementById('popupToast');
        if (!toastEl) {
          toastEl = document.createElement('div');
          toastEl.id = 'popupToast';
          toastEl.className = 'toast align-items-center border-0';
          toastEl.setAttribute('role', 'alert');
          toastEl.setAttribute('aria-live', 'assertive');
          toastEl.setAttribute('aria-atomic', 'true');
          toastContainer.appendChild(toastEl);
        }
        
        // Set the toast's style and content based on the response.
        if (data.status === 'success') {
          toastEl.classList.remove('text-bg-danger');
          toastEl.classList.add('text-bg-success');
        } else {
          toastEl.classList.remove('text-bg-success');
          toastEl.classList.add('text-bg-danger');
        }
        
        toastEl.innerHTML = `
          <div class="d-flex">
            <div class="toast-body">
              <i class="${data.status === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'}"></i> ${data.message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>`;
          
        var toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        
        // If schedule saved successfully, hide the modal and reset the form.
        if (data.status === 'success') {
          var modalInstance = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
          if (modalInstance) {
            modalInstance.hide();
          }
          form.reset();
          // Optionally, update the upcoming schedules list immediately.
          fetchSchedules();
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    });
    
    // Auto-fetch upcoming schedules and update the schedule list without reloading the page.
    function fetchSchedules() {
      fetch('get_upcoming_schedules.php')
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          const schedules = data.schedules;
          let html = "";
          if (schedules.length > 0) {
            schedules.forEach(schedule => {
              html += `<div class="schedule-item">
                <h6><i class="fas fa-clock"></i> ${schedule.scheduled_time}</h6>
                <p>
                  <strong>Device:</strong> ${schedule.board_id} 
                  <strong>Outlet:</strong> ${schedule.gpio} 
                  <strong>Action:</strong> ${schedule.action.charAt(0).toUpperCase() + schedule.action.slice(1)}
                </p>
                <span class="delete-btn" onclick="confirmDeletion(${schedule.id})">
                  <i class="fas fa-trash-alt"></i>
                </span>
              </div>`;
            });
          } else {
            html = "<p>No upcoming schedules found.</p>";
          }
          document.getElementById("scheduleList").innerHTML = html;
        }
      })
      .catch(error => {
        console.error('Error fetching schedules:', error);
      });
    }
    
    // Call fetchSchedules() every 5 seconds to auto-update the schedule list.
    setInterval(fetchSchedules, 5000);
    
    // Also, fetch schedules on initial page load.
    fetchSchedules();
    
    // When a board card is clicked, open the schedule modal and populate hidden fields.
    document.querySelectorAll('.board-card').forEach(card => {
      card.addEventListener('click', function() {
        const boardId = this.getAttribute('data-board-id');
        const gpioPins = this.getAttribute('data-gpio-pins').split(',');
        
        document.getElementById('modalBoardId').value = boardId;
        
        // Build GPIO selection buttons dynamically.
        let gpioHTML = '<label class="form-label">Select GPIO</label>';
        gpioHTML += '<div class="d-flex flex-wrap gap-2">';
        gpioPins.forEach(gpio => {
          gpio = gpio.trim();
          gpioHTML += `<button type="button" class="btn btn-outline-secondary gpio-btn" data-gpio="${gpio}">${gpio}</button>`;
        });
        gpioHTML += '</div>';
        document.getElementById('gpioSelection').innerHTML = gpioHTML;
        
        // Reset any previously selected GPIO.
        document.getElementById('modalGPIO').value = '';
        
        document.querySelectorAll('.gpio-btn').forEach(btn => {
          btn.addEventListener('click', function() {
            document.querySelectorAll('.gpio-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('modalGPIO').value = this.getAttribute('data-gpio');
          });
        });
        
        var scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
        scheduleModal.show();
      });
    });
    
    // If the URL has msg=deleted, display a toast popup for deletion.
    <?php if(isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
      let toastContainer = document.querySelector('.toast-container');
      let deleteToast = document.createElement('div');
      deleteToast.id = 'popupToast';
      deleteToast.className = 'toast align-items-center text-bg-warning border-0';
      deleteToast.setAttribute('role','alert');
      deleteToast.setAttribute('aria-live','assertive');
      deleteToast.setAttribute('aria-atomic','true');
      deleteToast.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">
            <i class="fas fa-trash-alt"></i> Schedule deleted successfully.
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>`;
      toastContainer.appendChild(deleteToast);
      var toast = new bootstrap.Toast(deleteToast, { delay: 3000 });
      toast.show();
    <?php endif; ?>
  </script>
</body>
</html>
