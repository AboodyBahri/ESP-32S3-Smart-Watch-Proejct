#include <WiFi.h>
#include <HTTPClient.h>
#include <Wire.h>
#include <Arduino_GFX_Library.h>
#include <MAX30105.h>
#include <heartRate.h> 
#include <Adafruit_AHTX0.h>
#include <Adafruit_BMP280.h>
#include "RTClib.h"
#include "splashscreen240.h" 

// --- Professional Color Palette ---
#define BLACK       0x0000
#define WHITE       0xFFFF
#define RED         0xF800
#define CYAN        0x07FF
#define DARK_GREY   0x2104
#define ORANGE      0xFD20

// --- Pinout ESP32-S3 SuperMini ---
#define SCK_PIN 12
#define MOSI_PIN 11
#define CS_PIN 13
#define DC_PIN 9
#define RST_PIN 10
#define SDA_PIN 6
#define SCL_PIN 5

Arduino_DataBus *bus = new Arduino_ESP32SPI(DC_PIN, CS_PIN, SCK_PIN, MOSI_PIN);
Arduino_GFX *gfx = new Arduino_GC9A01(bus, RST_PIN, 0, true);

MAX30105 particleSensor;
Adafruit_AHTX0 aht;
Adafruit_BMP280 bmp;
RTC_DS3231 rtc;

const char* ssid     = "project_wifi";
const char* password = "12345678";
String serverUrl     = "http://172.20.10.3/healthwatch/api.php";

const byte RATE_SIZE = 4; 
byte rates[RATE_SIZE];     
byte rateSpot = 0;
long lastBeat = 0;        
int beatAvg = 0;
float cTemp = 0, cHum = 0, cPres = 0;
bool fingerDetected = false;
unsigned long lastPostTime = 0;
unsigned long lastDisplayUpdate = 0;

void setup() {
    Serial.begin(115200);
    gfx->begin();
    gfx->fillScreen(BLACK);

    // Splash screen
    gfx->draw16bitBeRGBBitmap(0, 0, (uint16_t *)splash_gif_data, 240, 240);
    delay(3000); 
    gfx->fillScreen(BLACK);

    Wire.begin(SDA_PIN, SCL_PIN);

    // --- Start WiFi early so it's ready for NTP ---
    WiFi.begin(ssid, password);

    // --- RTC INIT + NTP SYNC ---
    if (!rtc.begin()) {
        Serial.println("ERROR: RTC module not found! Check wiring.");
    } else {
        // Wait up to 8 seconds for WiFi
        unsigned long startAttemptTime = millis();
        while (WiFi.status() != WL_CONNECTED && millis() - startAttemptTime < 8000) {
            delay(500);
            Serial.print(".");
        }

        if (WiFi.status() == WL_CONNECTED) {
            // UTC+3 = Saudi Arabia (Mecca/Riyadh)
            configTime(3 * 3600, 0, "pool.ntp.org", "time.nist.gov");
            struct tm timeinfo;
            if (getLocalTime(&timeinfo, 5000)) {
                rtc.adjust(DateTime(
                    timeinfo.tm_year + 1900,
                    timeinfo.tm_mon + 1,
                    timeinfo.tm_mday,
                    timeinfo.tm_hour,
                    timeinfo.tm_min,
                    timeinfo.tm_sec
                ));
                Serial.println("\n[NTP] RTC synced successfully!");

                // Build dynamic server URL from gateway
                IPAddress gateway = WiFi.gatewayIP();
                serverUrl = "http://" + String(gateway[0]) + "." +
                                        String(gateway[1]) + "." +
                                        String(gateway[2]) + ".3/healthwatch/api.php";
                Serial.print("[WiFi] Server URL: ");
                Serial.println(serverUrl);
            } else {
                Serial.println("\n[NTP] Sync failed — keeping existing RTC time.");
            }
        } else {
            Serial.println("\n[WiFi] Not connected — keeping RTC time.");
            if (rtc.lostPower()) {
                // Last resort fallback if battery died and no WiFi
                rtc.adjust(DateTime(F(__DATE__), F(__TIME__)));
                Serial.println("[RTC] Fallback: set to compile time.");
            }
        }

        // Always confirm current RTC time in Serial
        DateTime now = rtc.now();
        Serial.printf("[RTC] Time: %02d:%02d:%02d  Date: %02d/%02d/%04d\n",
            now.hour(), now.minute(), now.second(),
            now.day(), now.month(), now.year());
    }

    aht.begin();
    if (!bmp.begin(0x76)) bmp.begin(0x77);

    if (particleSensor.begin(Wire, I2C_SPEED_FAST)) {
        particleSensor.setup(); 
        particleSensor.setPulseAmplitudeRed(0x2F); 
        particleSensor.setPulseAmplitudeIR(0x2F);
    }

    drawStaticUI(); 
}

void loop() {
    long irValue = particleSensor.getIR();
    fingerDetected = (irValue > 50000);
    
    if (checkForBeat(irValue) == true) {
        long delta = millis() - lastBeat;
        lastBeat = millis();
        float beatsPerMinute = 60 / (delta / 1000.0);
        if (beatsPerMinute < 220 && beatsPerMinute > 45) {
            rates[rateSpot++] = (byte)beatsPerMinute;
            rateSpot %= RATE_SIZE;
            beatAvg = 0;
            for (byte x = 0; x < RATE_SIZE; x++) beatAvg += rates[x];
            beatAvg /= RATE_SIZE;
        }
    }

    if (millis() - lastDisplayUpdate > 500) { 
        lastDisplayUpdate = millis();
        readSensors();
        updateDisplay();
    }

    if (millis() - lastPostTime > 5000) { 
        lastPostTime = millis();
        sendData();
    }
}

void drawStaticUI() {
    gfx->drawCircle(120, 120, 118, DARK_GREY); 
    gfx->drawFastHLine(40, 160, 160, DARK_GREY); 
    gfx->setTextColor(DARK_GREY);
    gfx->setTextSize(1);
    gfx->setCursor(105, 145);
    gfx->print("HEART RATE");
}

void readSensors() {
    sensors_event_t h, t;
    aht.getEvent(&h, &t);
    cHum = h.relative_humidity;
    float tempReading = bmp.readTemperature();
    cTemp = (isnan(tempReading)) ? t.temperature : tempReading;
    cPres = bmp.readPressure() / 100.0F;
}

void updateDisplay() {
    DateTime now = rtc.now();

    // --- Time ---
    gfx->fillRect(40, 45, 160, 45, BLACK); 
    gfx->setCursor(45, 50);
    gfx->setTextColor(WHITE);
    gfx->setTextSize(4);
    gfx->printf("%02d:%02d", now.hour(), now.minute());
    
    gfx->setTextSize(2);
    gfx->setTextColor(CYAN);
    gfx->printf(":%02d", now.second()); 

    // --- Heart Rate ---
    gfx->fillRect(80, 100, 80, 40, BLACK); 
    if (!fingerDetected) {
        gfx->setTextColor(DARK_GREY);
        gfx->setTextSize(3);
        gfx->setCursor(95, 110);
        gfx->print("---");
    } else {
        gfx->setTextColor(RED);
        gfx->setTextSize(4);
        gfx->setCursor(85, 105);
        gfx->printf("%d", beatAvg);
        int pulseSize = (millis() % 1000 < 500) ? 3 : 5;
        gfx->fillCircle(75, 120, pulseSize, RED);
    }

    // --- Environmental Sensors ---
    gfx->fillRect(30, 170, 180, 45, BLACK);
    gfx->setTextSize(2);
    
    gfx->setTextColor(ORANGE);
    gfx->setCursor(45, 175);
    gfx->printf("%.1fC", cTemp);
    
    gfx->setTextColor(CYAN);
    gfx->setCursor(45, 195);
    gfx->printf("%.0f%% RH", cHum);

    gfx->setTextColor(WHITE);
    gfx->setCursor(130, 185);
    gfx->printf("%.0fhPa", cPres);

    // --- WiFi Status Indicator ---
    if (WiFi.status() == WL_CONNECTED) {
        gfx->fillCircle(120, 20, 3, CYAN);
    } else {
        gfx->drawCircle(120, 20, 3, RED);
    }
}

void sendData() {
    if (WiFi.status() != WL_CONNECTED) {
        WiFi.begin(ssid, password);
        return; 
    }

    HTTPClient http;
    http.begin(serverUrl);
    http.setConnectTimeout(1000);
    http.addHeader("Content-Type", "application/json");
    
    String json = "{\"bpm\":" + String(beatAvg) + 
                  ",\"temp\":" + String(cTemp,1) + 
                  ",\"hum\":" + String(cHum,0) + 
                  ",\"pres\":" + String(cPres,0) + 
                  ",\"finger\":" + (fingerDetected ? "true" : "false") + "}";
    
    int httpResponseCode = http.POST(json);
    http.end();
}
