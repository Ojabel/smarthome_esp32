#include <WiFi.h>
#include <WiFiManager.h>       // https://github.com/tzapu/WiFiManager
#include <Preferences.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

// --- Global Variables & Default Values ---
// Default values (will be overwritten if the user provisions new values)
String serverIP = "192.168.43.31";   // Default Server IP
String boardID  = "ESP32_001";        // Default Board ID
String wifiUser = "";                // WiFi Username (optional)

// Endpoint URLs – built using the provisioned server IP
String baseUrl;
String gpioStateUrl;
String registerBoardUrl;
String getScheduleUrl;             // This endpoint should return only events that are due (scheduled_time <= NOW())
String markScheduleExecutedUrl;

// WiFiManager default AP credentials
const char* defaultAPName = "ESP_ConfigAP";
const char* defaultAPPass = "password";

// --- GPIO Settings ---
int gpioPins[] = {21, 22, 23};  // Adjust pins as needed
const int numPins = sizeof(gpioPins) / sizeof(gpioPins[0]);
String gpioState = "000";       // e.g., "000" means all pins off

// Global flag indicating if board registration was successful
bool boardRegistered = false;

// Built-in LED pin (commonly GPIO2 for many ESP32 boards)
const int ledPin = 2;

// --- Preferences for storing configuration ---
Preferences preferences;

/////////////////////////////////////////////
// loadConfig()
// Loads saved configuration and registration status from non-volatile storage.
/////////////////////////////////////////////
void loadConfig() {
  preferences.begin("config", true); // read-only mode
  serverIP = preferences.getString("serverIP", serverIP);
  boardID  = preferences.getString("boardID", boardID);
  wifiUser = preferences.getString("wifiUser", wifiUser);
  boardRegistered = preferences.getBool("registered", false);
  preferences.end();
  Serial.println("Loaded configuration:");
  Serial.println("Server IP: " + serverIP);
  Serial.println("Board ID: " + boardID);
  Serial.println("WiFi Username: " + wifiUser);
  Serial.print("Board Registered: ");
  Serial.println(boardRegistered ? "YES" : "NO");
}

/////////////////////////////////////////////
// saveRegistrationStatus()
// Saves the board registration flag to non-volatile storage.
/////////////////////////////////////////////
void saveRegistrationStatus(bool status) {
  preferences.begin("config", false); // read-write mode
  preferences.putBool("registered", status);
  preferences.end();
  Serial.print("Registration status saved: ");
  Serial.println(status ? "YES" : "NO");
}

/////////////////////////////////////////////
// saveConfig()
// Saves other configuration parameters.
/////////////////////////////////////////////
void saveConfig() {
  preferences.begin("config", false); // read-write mode
  preferences.putString("serverIP", serverIP);
  preferences.putString("boardID", boardID);
  preferences.putString("wifiUser", wifiUser);
  preferences.end();
  Serial.println("Configuration saved.");
}

/////////////////////////////////////////////
// setupConfigPortal()
// Launches a captive portal using WiFiManager to provision WiFi and custom parameters.
/////////////////////////////////////////////
void setupConfigPortal() {
  WiFiManager wm;
  
  // Create custom parameters for Server IP, Board ID, and WiFi Username.
  WiFiManagerParameter customServer("server", "Server IP", serverIP.c_str(), 40);
  WiFiManagerParameter customBoard("board", "Board ID", boardID.c_str(), 20);
  WiFiManagerParameter customWifiUser("wuser", "WiFi Username", wifiUser.c_str(), 32);
  
  wm.addParameter(&customServer);
  wm.addParameter(&customBoard);
  wm.addParameter(&customWifiUser);
  
  // Optionally set custom AP credentials for the configuration portal.
  if (!wm.autoConnect(defaultAPName, defaultAPPass)) {
    Serial.println("Failed to connect or hit timeout in configuration portal.");
    delay(3000);
    ESP.restart();
  }
  
  // Retrieve new parameter values.
  serverIP = String(customServer.getValue());
  boardID  = String(customBoard.getValue());
  wifiUser = String(customWifiUser.getValue());
  
  saveConfig();
  
  Serial.println("New configuration:");
  Serial.println("Server IP: " + serverIP);
  Serial.println("Board ID: " + boardID);
  Serial.println("WiFi Username: " + wifiUser);
}

/////////////////////////////////////////////
// buildUrls()
// Constructs full endpoint URLs using the provisioned server IP.
/////////////////////////////////////////////
void buildUrls() {
  baseUrl = "http://" + serverIP + "/newsmarthome/";
  gpioStateUrl = baseUrl + "get_gpio_state.php";
  registerBoardUrl = baseUrl + "index.php";
  getScheduleUrl = baseUrl + "get_schedule.php"; // This endpoint should return only due schedules (scheduled_time <= NOW())
  markScheduleExecutedUrl = baseUrl + "mark_schedule_executed.php";
  Serial.println("Built URLs:");
  Serial.println(gpioStateUrl);
  Serial.println(registerBoardUrl);
  Serial.println(getScheduleUrl);
  Serial.println(markScheduleExecutedUrl);
}

/////////////////////////////////////////////
// registerBoard()
// Registers the board with the server only if it has not been registered before.
/////////////////////////////////////////////
void registerBoard() {
  // Only register if not already registered.
  if (boardRegistered) {
    Serial.println("Board already registered. Skipping registration.");
    return;
  }
  
  HTTPClient http;
  // Create a comma-separated list of GPIO pins.
  String pinList = "";
  for (int i = 0; i < numPins; i++) {
    pinList += String(gpioPins[i]);
    if (i < numPins - 1) {
      pinList += ",";
    }
  }
  String postData = "board_id=" + boardID + "&gpio_pins=" + pinList;
  
  http.begin(registerBoardUrl);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  int httpResponseCode = http.POST(postData);
  
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("Registration Response: " + response);
    // Check for a success keyword in the response (adjust as needed for your server)
    if (response.indexOf("new_board_added") != -1 || response.indexOf("ok") != -1) {
      boardRegistered = true;
      saveRegistrationStatus(true);
      Serial.println("Board registered successfully.");
    } else {
      boardRegistered = false;
      Serial.println("Board registration failed.");
    }
  } else {
    Serial.println("Error during registration");
    boardRegistered = false;
  }
  http.end();
}

/////////////////////////////////////////////
// fetchGPIOState()
// Retrieves the current GPIO state from the server (if registered) and updates the pins.
/////////////////////////////////////////////
void fetchGPIOState() {
  if (!boardRegistered) {
    Serial.println("Board not registered; skipping GPIO state fetch.");
    return;
  }
  
  HTTPClient http;
  String requestUrl = gpioStateUrl + "?board_id=" + boardID + "&t=" + String(millis());
  http.begin(requestUrl);
  int httpResponseCode = http.GET();
  
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("GPIO State Response: " + response);
    
    int stateIndex = response.indexOf("\"gpio_state\":\"");
    if (stateIndex > 0) {
      String newState = response.substring(stateIndex + 14, stateIndex + 14 + numPins);
      if (newState != gpioState) {
        Serial.println("Updating GPIO state to: " + newState);
        gpioState = newState;
        updateGPIOPins();
      }
    } else {
      Serial.println("Unexpected response: " + response);
    }
  } else {
    Serial.println("Error fetching GPIO state");
  }
  http.end();
}

/////////////////////////////////////////////
// updateGPIOPins()
// Sets each GPIO pin HIGH or LOW based on the gpioState string.
/////////////////////////////////////////////
void updateGPIOPins() {
  for (int i = 0; i < numPins; i++) {
    if (gpioState[i] == '1') {
      digitalWrite(gpioPins[i], HIGH);
    } else {
      digitalWrite(gpioPins[i], LOW);
    }
  }
}

/////////////////////////////////////////////
// checkSchedules()
// Queries the server for scheduled actions (if registered) and executes them.
// IMPORTANT: The server endpoint (get_schedule.php) must only return events whose scheduled_time is now or in the past,
// so that the ESP32 will only execute a schedule when the set time and date has been reached.
/////////////////////////////////////////////
void checkSchedules() {
  if (!boardRegistered) {
    Serial.println("Board not registered; skipping schedule check.");
    return;
  }
  
  HTTPClient http;
  String url = getScheduleUrl + "?board_id=" + boardID + "&t=" + String(millis());
  http.begin(url);
  int httpResponseCode = http.GET();
  
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("Schedule Response: " + response);
    
    const size_t capacity = JSON_ARRAY_SIZE(10) + 10 * JSON_OBJECT_SIZE(4) + 300;
    DynamicJsonDocument doc(capacity);
    DeserializationError error = deserializeJson(doc, response);
    if (!error) {
      JsonArray schedules = doc["schedules"].as<JsonArray>();
      for (JsonObject sched : schedules) {
        String schedId = sched["id"].as<String>();
        String schedGpio = sched["gpio"].as<String>();
        String schedAction = sched["action"].as<String>();
        // (Optional: You could also fetch the scheduled_time from the JSON here if needed.)
        Serial.println("Executing schedule id: " + schedId + ", gpio: " + schedGpio + ", action: " + schedAction);
        
        for (int i = 0; i < numPins; i++) {
          if (String(gpioPins[i]) == schedGpio) {
            if (schedAction == "on") {
              digitalWrite(gpioPins[i], HIGH);
              gpioState.setCharAt(i, '1');
            } else {
              digitalWrite(gpioPins[i], LOW);
              gpioState.setCharAt(i, '0');
            }
            break;
          }
        }
        markScheduleExecuted(schedId);
      }
    } else {
      Serial.print("JSON parse error: ");
      Serial.println(error.c_str());
    }
  } else {
    Serial.println("Error fetching schedules: " + String(httpResponseCode));
  }
  http.end();
}

/////////////////////////////////////////////
// markScheduleExecuted()
// Notifies the server that a schedule (by its ID) has been executed.
/////////////////////////////////////////////
void markScheduleExecuted(String scheduleId) {
  HTTPClient http;
  String postData = "id=" + scheduleId;
  http.begin(markScheduleExecutedUrl);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  int httpResponseCode = http.POST(postData);
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("Mark Schedule Executed Response: " + response);
  } else {
    Serial.println("Error marking schedule executed");
  }
  http.end();
}

/////////////////////////////////////////////
// indicateWiFiStatus()
// Uses the built-in LED to indicate WiFi connection status.
// Blinks the LED while not connected; turns LED ON when connected.
/////////////////////////////////////////////
void indicateWiFiStatus() {
  if (WiFi.status() == WL_CONNECTED) {
    digitalWrite(ledPin, HIGH);
  } else {
    digitalWrite(ledPin, HIGH);
    delay(100);
    digitalWrite(ledPin, LOW);
    delay(100);
  }
}

/////////////////////////////////////////////
// setup() function
/////////////////////////////////////////////
void setup() {
  Serial.begin(115200);
  
  // Initialize the built-in LED pin.
  pinMode(ledPin, OUTPUT);
  
  // Load stored configuration (including registration status).
  loadConfig();
  
  // Set WiFi mode to station.
  WiFi.mode(WIFI_STA);
  
  // Launch configuration portal if no WiFi credentials are found.
  if (WiFi.SSID() == "") {
    Serial.println("No WiFi credentials found, launching configuration portal.");
    setupConfigPortal();
  }
  
  // Connect to WiFi (while indicating status with LED).
  Serial.println("Connecting to WiFi...");
  while (WiFi.status() != WL_CONNECTED) {
    indicateWiFiStatus();
    Serial.print(".");
  }
  digitalWrite(ledPin, HIGH);
  Serial.println("\nWiFi Connected!");
  
  // Reload configuration (in case it was updated) and build endpoint URLs.
  loadConfig();
  buildUrls();
  
  // Initialize GPIO pins as outputs and set them LOW.
  for (int i = 0; i < numPins; i++) {
    pinMode(gpioPins[i], OUTPUT);
    digitalWrite(gpioPins[i], LOW);
  }
  
  // Automatically register the board only if not already registered.
  if (!boardRegistered) {
    Serial.println("Board not registered. Registering board now...");
    registerBoard();
  } else {
    Serial.println("Board is already registered. Skipping registration.");
  }
}

/////////////////////////////////////////////
// loop() function
/////////////////////////////////////////////
void loop() {
  indicateWiFiStatus();  // Continuously indicate WiFi status with LED.
  
  if (boardRegistered) {
    fetchGPIOState();   // Update GPIO state from server.
    checkSchedules();   // Check and execute any pending schedules.
  } else {
    Serial.println("Board not registered; skipping server interactions.");
  }
  
  delay(1000); // 1-second loop delay.
}
