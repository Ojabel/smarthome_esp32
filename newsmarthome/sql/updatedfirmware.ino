#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiManager.h>          // https://github.com/tzapu/WiFiManager
#include <Preferences.h>
#include <ArduinoJson.h>          // For JSON parsing (if not already installed, install via Library Manager)

// --- Global Variables for Application Settings ---
String serverIP = "192.168.43.31";  // Default Server IP
String boardID = "ESP32_002";       // Default Board ID
String wifiUser = "";               // WiFi Username (optional)

// These endpoints will be constructed from the serverIP
String baseUrl;
String gpioStateUrl;
String registerBoardUrl;
String getScheduleUrl;
String markScheduleExecutedUrl;

// --- GPIO Definitions ---
int gpioPins[] = {21, 22, 23};
const int numPins = sizeof(gpioPins) / sizeof(gpioPins[0]);
String gpioState = "000"; // e.g., "000" means all pins off

// --- Provisioning ---
Preferences preferences; // For storing settings persistently

// --- Boot Button for Registration Mode ---
const int bootButtonPin = 0;  // Use a common boot button (GPIO0)

/////////////////////////////////////////////
// Function: loadConfig()
// Loads saved configuration from non-volatile storage
/////////////////////////////////////////////
void loadConfig() {
  preferences.begin("config", true);  // read-only mode
  serverIP = preferences.getString("serverIP", serverIP);
  boardID  = preferences.getString("boardID", boardID);
  wifiUser = preferences.getString("wifiUser", wifiUser);
  preferences.end();
}

/////////////////////////////////////////////
// Function: saveConfig()
// Saves configuration to non-volatile storage
/////////////////////////////////////////////
void saveConfig() {
  preferences.begin("config", false); // read-write mode
  preferences.putString("serverIP", serverIP);
  preferences.putString("boardID", boardID);
  preferences.putString("wifiUser", wifiUser);
  preferences.end();
}

/////////////////////////////////////////////
// Function: setupConfigPortal()
// Uses WiFiManager to allow the user to provision settings
/////////////////////////////////////////////
void setupConfigPortal() {
  // Create WiFiManager instance
  WiFiManager wm;

  // Create custom parameters for server IP, board ID, and WiFi username
  WiFiManagerParameter customServer("server", "Server IP", serverIP.c_str(), 40);
  WiFiManagerParameter customBoard("board", "Board ID", boardID.c_str(), 20);
  WiFiManagerParameter customWifiUser("wuser", "WiFi Username", wifiUser.c_str(), 32);

  // Add custom parameters to WiFiManager
  wm.addParameter(&customServer);
  wm.addParameter(&customBoard);
  wm.addParameter(&customWifiUser);

  // Optionally, you can set custom AP credentials for the config portal
  // Here the portal will be "ESP_ConfigAP" with password "password"
  if (!wm.autoConnect("ESP_ConfigAP", "password")) {
    Serial.println("Failed to connect and hit timeout");
    delay(3000);
    ESP.restart();
  }

  // Retrieve and save custom parameters entered by the user
  serverIP = String(customServer.getValue());
  boardID  = String(customBoard.getValue());
  wifiUser = String(customWifiUser.getValue());
  saveConfig();
  Serial.println("Configuration saved:");
  Serial.println("Server IP: " + serverIP);
  Serial.println("Board ID: " + boardID);
  Serial.println("WiFi Username: " + wifiUser);
}

/////////////////////////////////////////////
// Function: buildUrls()
// Constructs endpoint URLs using the configured server IP
/////////////////////////////////////////////
void buildUrls() {
  baseUrl = "http://" + serverIP + "/newsmarthome/";
  gpioStateUrl = baseUrl + "get_gpio_state.php";
  registerBoardUrl = baseUrl + "index.php";
  getScheduleUrl = baseUrl + "get_schedule.php";
  markScheduleExecutedUrl = baseUrl + "mark_schedule_executed.php";
}

/////////////////////////////////////////////
// Function: registerBoard()
// Registers the board with the server if needed.
/////////////////////////////////////////////
void registerBoard() {
  HTTPClient http;
  // Create a comma-separated list of GPIO pins
  String pinList = "";
  for (int i = 0; i < numPins; i++) {
    pinList += String(gpioPins[i]);
    if (i < numPins - 1)
      pinList += ",";
  }
  String postData = "board_id=" + boardID + "&gpio_pins=" + pinList;
  
  http.begin(registerBoardUrl);
  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  int httpResponseCode = http.POST(postData);
  
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("Registration Response: " + response);
  } else {
    Serial.println("Error during registration");
  }
  http.end();
}

/////////////////////////////////////////////
// Function: fetchGPIOState()
// Retrieves the current GPIO state from the server and updates the pins if necessary
/////////////////////////////////////////////
void fetchGPIOState() {
  HTTPClient http;
  String requestUrl = gpioStateUrl + "?board_id=" + boardID + "&t=" + String(millis());
  http.begin(requestUrl);
  int httpResponseCode = http.GET();
  
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("GPIO State Response: " + response);
    
    // Find the "gpio_state" key in the response (assumed JSON)
    int stateIndex = response.indexOf("\"gpio_state\":\"");
    if (stateIndex > 0) {
      String newState = response.substring(stateIndex + 14, stateIndex + 14 + numPins);
      if (newState != gpioState) {
        Serial.println("Updating GPIO states to: " + newState);
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
// Function: updateGPIOPins()
// Sets each GPIO pin HIGH or LOW based on the gpioState string
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
// Function: checkSchedules()
// Queries the server for scheduled actions and executes them if applicable
/////////////////////////////////////////////
void checkSchedules() {
  HTTPClient http;
  String url = getScheduleUrl + "?board_id=" + boardID + "&t=" + String(millis());
  http.begin(url);
  int httpResponseCode = http.GET();
  
  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println("Schedule Response: " + response);
    
    // Parse JSON using ArduinoJson
    const size_t capacity = JSON_ARRAY_SIZE(10) + 10 * JSON_OBJECT_SIZE(3) + 200;
    DynamicJsonDocument doc(capacity);
    DeserializationError error = deserializeJson(doc, response);
    if (!error) {
      JsonArray schedules = doc["schedules"].as<JsonArray>();
      for (JsonObject sched : schedules) {
        String schedId = sched["id"].as<String>();
        String schedGpio = sched["gpio"].as<String>();
        String schedAction = sched["action"].as<String>();
        Serial.println("Executing schedule id: " + schedId + ", gpio: " + schedGpio + ", action: " + schedAction);
        
        // Execute the scheduled action for the corresponding GPIO pin
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
        // Mark the schedule as executed on the server
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
// Function: markScheduleExecuted()
// Notifies the server that a schedule (by its ID) has been executed
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
// setup() function
/////////////////////////////////////////////
void setup() {
  Serial.begin(115200);

  // Load any saved configuration from Preferences
  loadConfig();

  // Start provisioning if needed. You might choose to always show the config portal
  // (for example, if a certain button is pressed) or only if no valid config is present.
  // Here, we’ll launch the config portal if the device is not yet connected to WiFi.
  WiFi.mode(WIFI_STA);
  if (WiFi.SSID() == "") {
    // If no network has been set, launch the config portal.
    setupConfigPortal();
  } else {
    // Alternatively, you can force the portal with wm.resetSettings();
    // But here we assume saved settings are valid.
  }

  // At this point, WiFiManager has connected the board.
  Serial.println("WiFi Connected!");
  
  // Build endpoint URLs using the provisioned server IP
  buildUrls();

  // Initialize GPIO pins as outputs and set them LOW
  for (int i = 0; i < numPins; i++) {
    pinMode(gpioPins[i], OUTPUT);
    digitalWrite(gpioPins[i], LOW);
  }
  
  // Check if the boot button is held for 3 seconds to register the board
  pinMode(bootButtonPin, INPUT_PULLUP);
  Serial.println("Hold the boot button for 3 seconds to register the board.");
  unsigned long startTime = millis();
  bool pressed = false;
  unsigned long pressStart = 0;
  
  while (millis() - startTime < 3000) {
    if (digitalRead(bootButtonPin) == LOW) {
      if (!pressed) {
        pressed = true;
        pressStart = millis();
      }
    } else {
      if (pressed)
        break;
    }
    delay(10);
  }
  
  if (pressed && (millis() - pressStart >= 3000)) {
    Serial.println("Boot button held for 3 seconds. Registering board...");
    registerBoard();
    delay(2000);
  } else {
    Serial.println("Boot button not held long enough. Skipping registration.");
  }
}

/////////////////////////////////////////////
// loop() function
/////////////////////////////////////////////
void loop() {
  fetchGPIOState();   // Update GPIO state from server
  checkSchedules();   // Check and execute any pending schedules
  delay(1000);        // Delay 1 second between loops
}
