#include <Wire.h>
#include <Arduino_GFX_Library.h>
#include <MAX30105.h>
#include <heartRate.h>
#include <Adafruit_AHTX0.h>
#include <Adafruit_BMP280.h>

// --- 1. Manual Color Definitions (Fixes 'BLACK' error) ---
#define BLACK   0x0000
#define WHITE   0xFFFF
#define RED     0xF800
#define GREEN   0x07E0
#define BLUE    0x001F
#define CYAN    0x07FF
#define MAGENTA 0xF81F
#define YELLOW  0xFFE0

// --- Corrected Pinout for your connections ---
#define SCK_PIN 12
#define MOSI_PIN 11
#define CS_PIN 13
#define DC_PIN 9
#define RST_PIN 10
#define BLK_PIN 14 
#define SDA_PIN 1  
#define SCL_PIN 2  

// --- Hardware Instances ---
Arduino_DataBus *bus = new Arduino_ESP32SPI(DC_PIN, CS_PIN, SCK_PIN, MOSI_PIN);
Arduino_GFX *gfx = new Arduino_GC9A01(bus, RST_PIN, 0 /* rotation */, true /* IPS */);

MAX30105 particleSensor;
Adafruit_AHTX0 aht;
Adafruit_BMP280 bmp;

// --- Variables ---
long lastBeat = 0;
float beatsPerMinute;
int beatAvg = 0;
float temp = 0, hum = 0, pres = 0;

// --- 2. Forward Declaration for UI (Required by some compilers) ---
void updateUI();

void setup() {
  Serial.begin(115200);

  // 1. Backlight On
  pinMode(BLK_PIN, OUTPUT);
  digitalWrite(BLK_PIN, HIGH);

  // 2. Start Display
  gfx->begin();
  gfx->fillScreen(BLACK);
  
  // 3. Start I2C Sensors
  Wire.begin(SDA_PIN, SCL_PIN);

  if (!particleSensor.begin(Wire, I2C_SPEED_FAST)) {
    gfx->setCursor(20, 100);
    gfx->setTextColor(RED);
    gfx->println("MAX30102 Fail");
  }
  particleSensor.setup(); 
  
  aht.begin();
  bmp.begin(0x76); 

  gfx->fillScreen(BLACK);
}

void loop() {
  // --- Read Heart Rate ---
  long irValue = particleSensor.getIR();
  
  // checkForBeat is part of heartRate.h
  if (checkForBeat(irValue) == true) {
    long delta = millis() - lastBeat;
    lastBeat = millis();
    beatsPerMinute = 60 / (delta / 1000.0);
    
    if (beatsPerMinute < 255 && beatsPerMinute > 20) {
      beatAvg = (int)beatsPerMinute; 
    }
  }

  // --- Read Environment (Every 2 seconds) ---
  static unsigned long lastEnvUpdate = 0;
  if (millis() - lastEnvUpdate > 2000) {
    sensors_event_t humidity_event, temp_event; // Renamed to avoid 't' conflict
    aht.getEvent(&humidity_event, &temp_event);
    
    temp = temp_event.temperature;
    hum = humidity_event.relative_humidity;
    pres = bmp.readPressure() / 100.0F;
    
    lastEnvUpdate = millis();
    updateUI(); 
  }
}

void updateUI() {
  // Using background color in setTextColor(color, background) 
  // prevents character ghosting without flickering the whole screen
  
  gfx->setCursor(60, 60);
  gfx->setTextColor(RED, BLACK); 
  gfx->setTextSize(3);
  gfx->printf("BPM: %d   ", beatAvg); // Extra spaces clear old digits

  gfx->setCursor(40, 110);
  gfx->setTextColor(CYAN, BLACK);
  gfx->setTextSize(2);
  gfx->printf("Temp: %.1f C", temp);

  gfx->setCursor(40, 140);
  gfx->setTextColor(CYAN, BLACK);
  gfx->printf("Hum:  %.0f %% ", hum);

  gfx->setCursor(40, 170);
  gfx->setTextColor(YELLOW, BLACK);
  gfx->printf("Pres: %.0f hPa ", pres);
}