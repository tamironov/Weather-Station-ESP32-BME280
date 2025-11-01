A low-power IoT weather station built with an ESP32 and a BME280 environmental sensor.
The ESP32 measures temperature, humidity, and pressure every 10 minutes and sends the data to a web server via HTTP.
A PHP script on the server stores the readings in a MySQL database and displays them on a real-time web dashboard.

**Features:**
📡 Wi-Fi enabled data transmission
🌡️ BME280 sensor for temperature, humidity, and pressure
🔋 Deep sleep mode for low power consumption
💾 MySQL database to store readings
💻 PHP + HTML GUI for real-time display
🕐 Updates every 10 minutes

**System Overview**
[BME280 Sensor]
       │
       ▼
   [ESP32 Board]
 (Reads & Sends Data)
       │
       ▼
 [PHP Server + MySQL]
 (Stores Data)
       │
       ▼
 [Web Dashboard]
 (Displays in Real Time)
