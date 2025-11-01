#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Adafruit_BME280.h>
#include "esp_wifi.h"
#include "esp_bt.h"

// --- Wi-Fi credentials ---
// Instead, create a separate file called "secrets.h" (ignored by .gitignore).
#include "secrets.h"  // contains: const char* WIFI_SSID and const char* WIFI_PASSWORD

// --- Server settings ---
const char* serverName = "http://weather.yourdomain.com/api/insert.php";
const String apiKey = "YOUR_API_KEY";  // replace locally (do not upload real key)

// --- Sensor setup ---
Adafruit_BME280 bme;

// --- Deep sleep time (microseconds) ---
const uint64_t SLEEP_TIME = 10ULL * 60ULL * 1000000ULL;  // 10 minutes

void setup() {
  Serial.begin(115200);
  delay(200);

  // Turn off onboard LED (optional)
  pinMode(2, OUTPUT);
  digitalWrite(2, LOW);

  // Disable unused peripherals for lower power
  btStop();
  esp_wifi_stop();

  // Initialize BME280
  Wire.begin(21, 22);
  if (!bme.begin(0x76)) {
    Serial.println("BME280 not found!");
    delay(2000);
    esp_deep_sleep_start();
  }

  // Connect to Wi-Fi
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long startAttemptTime = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - startAttemptTime < 10000) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nConnected to WiFi");

    float temp = bme.readTemperature();
    float hum  = bme.readHumidity();
    float pres = bme.readPressure() / 100.0F;

    String url = String(serverName) +
                 "?temperature=" + String(temp) +
                 "&humidity=" + String(hum) +
                 "&pressure=" + String(pres) +
                 "&key=" + apiKey;

    HTTPClient http;
    http.begin(url);
    int httpCode = http.GET();

    if (httpCode > 0) {
      String payload = http.getString();
      Serial.println("Server Response: " + payload);
    } else {
      Serial.println("Error sending request");
    }

    http.end();
    WiFi.disconnect(true);
    WiFi.mode(WIFI_OFF);
    esp_wifi_stop();
  } else {
    Serial.println("\nWiFi connection failed, going to sleep");
  }

  // Enter deep sleep
  Serial.println("Entering deep sleep for 10 minutes...");
  esp_sleep_enable_timer_wakeup(SLEEP_TIME);
  esp_deep_sleep_start();
}

void loop() {
  // Not used — the ESP32 resets automatically after deep sleep
}
