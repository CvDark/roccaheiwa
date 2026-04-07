// ═══════════════════════════════════════════════════════════════
//   Smart Locker ESP32 — I2C Final Version
//   Address Found: 0x24 | SDA=21, SCL=22
// ═══════════════════════════════════════════════════════════════

#include <Adafruit_PN532.h>
#include <HTTPClient.h>
#include <WiFi.h>
#include <Wire.h>

// ═══════════════════════════════════════════════
//   KONFIGURASI — DUAL-MODE PROFILE
// ═══════════════════════════════════════════════

const String DEVICE_ID = "DEV-DUAL";
const int LOCKER_IDS[] = {1, 2}; // [1]=Commercial, [2]=Institution
const int NUM_LOCKERS = 2;

const char *WIFI_SSID = "test";
const char *WIFI_PASSWORD = "12345678";

// ✅ FIXED: Point to root API to handle both Commercial and Institution
const String SERVER_URL = "https://roccaheiwa.com";

const String NFC_SUBMIT_URL = SERVER_URL + "/api/nfc_submit.php";
const String NFC_CHECK_MODE_URL =
    SERVER_URL + "/api/nfc_check_mode.php?device_id=" + DEVICE_ID;

// ═══════════════════════════════════════════════
//   PIN SETUP
// ═══════════════════════════════════════════════
const int PIN_RELAY = 26;
const int PIN_BUZZER = 13;
const int PIN_LED = 2;

// I2C Setup
#define PN532_SDA (21)
#define PN532_SCL (22)

Adafruit_PN532 nfc(PN532_SDA, PN532_SCL);

// ═══════════════════════════════════════════════
//   SETTINGS
// ═══════════════════════════════════════════════
const int UNLOCK_DURATION = 5000;
const int POLL_INTERVAL = 2000;
const int MODE_CHECK_INTERVAL = 3000;
const int NFC_COOLDOWN = 2000;
const bool RELAY_ACTIVE_LOW = true;

bool isUnlocked = false;
bool nfcReady = false;
bool registerMode = false;

unsigned long unlockStart = 0;
unsigned long lastPoll = 0;
unsigned long lastNFCScan = 0;
unsigned long lastModeCheck = 0;

// ═══════════════════════════════════════════════
//   FUNCTIONS (RELAY, BUZZER, LED)
// ═══════════════════════════════════════════════
void relayON() { digitalWrite(PIN_RELAY, RELAY_ACTIVE_LOW ? LOW : HIGH); }
void relayOFF() { digitalWrite(PIN_RELAY, RELAY_ACTIVE_LOW ? HIGH : LOW); }

void beep(int ms = 100) {
  digitalWrite(PIN_BUZZER, HIGH);
  delay(ms);
  digitalWrite(PIN_BUZZER, LOW);
}

void beepDouble() {
  for (int i = 0; i < 2; i++) {
    digitalWrite(PIN_BUZZER, HIGH);
    delay(150);
    digitalWrite(PIN_BUZZER, LOW);
    delay(100);
  }
}

void blinkLED(int times, int ms = 100) {
  for (int i = 0; i < times; i++) {
    digitalWrite(PIN_LED, HIGH);
    delay(ms);
    digitalWrite(PIN_LED, LOW);
    delay(ms);
  }
}

void unlockSolenoid() {
  if (isUnlocked)
    return;
  Serial.println("[LOCK] UNLOCK");
  relayON();
  isUnlocked = true;
  unlockStart = millis();
  blinkLED(3, 80);
  beepDouble();
  digitalWrite(PIN_LED, HIGH);
}

void lockSolenoid() {
  Serial.println("[LOCK] LOCK");
  relayOFF();
  isUnlocked = false;
  beep(300);
  digitalWrite(PIN_LED, LOW);
}

// ═══════════════════════════════════════════════
//   NETWORKING & SERVER
// ═══════════════════════════════════════════════
void connectWiFi() {
  Serial.print("[WiFi] Connecting to: ");
  Serial.println(WIFI_SSID);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n[WiFi] Connected! IP: " + WiFi.localIP().toString());
    beepDouble();
  } else {
    Serial.println("\n[WiFi] FAILED to connect!");
  }
}

void checkRegisterMode() {
  if (WiFi.status() != WL_CONNECTED)
    return;
  HTTPClient http;
  http.begin(NFC_CHECK_MODE_URL);
  http.addHeader("ngrok-skip-browser-warning", "true");
  int code = http.GET();
  if (code == HTTP_CODE_OK) {
    String res = http.getString();
    res.trim();
    bool wasRegister = registerMode;
    registerMode = (res == "REGISTER");
    if (wasRegister != registerMode) {
      Serial.println("[MODE] Switched to: " +
                     String(registerMode ? "REGISTER" : "ACCESS"));
    }
  } else {
    // ⚠️ DEBUG: Tunjuk HTTP error code
    Serial.println("[MODE] HTTP Error: " + String(code) +
                   " URL: " + NFC_CHECK_MODE_URL);
  }
  http.end();
}

void sendNFCScan(String uid, String purpose) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[NFC] WiFi not connected, skip send");
    return;
  }
  HTTPClient http;
  http.begin(NFC_SUBMIT_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("ngrok-skip-browser-warning", "true");

  String payload = "{\"device_id\":\"" + DEVICE_ID + "\",\"uid\":\"" + uid +
                   "\",\"purpose\":\"" + purpose + "\"}";

  Serial.println("[NFC] Sending UID: " + uid + " | Purpose: " + purpose);
  Serial.println("[NFC] URL: " + NFC_SUBMIT_URL);
  Serial.println("[NFC] Payload: " + payload);

  int code = http.POST(payload);

  // ⚠️ DEBUG: Tunjuk response dari server
  if (code == HTTP_CODE_OK) {
    String response = http.getString();
    Serial.println("[NFC] Server response: " + response);
    beep(100);
  } else {
    Serial.println("[NFC] HTTP Error: " + String(code));
    if (code < 0) {
      Serial.println("[NFC] Connection failed! Check URL or server.");
    }
  }
  http.end();
}

void checkServerStatus(int id) {
  if (WiFi.status() != WL_CONNECTED || registerMode)
    return;

  String url = SERVER_URL + "/api/check_status.php?locker_id=" + String(id);

  HTTPClient http;
  http.begin(url);
  http.addHeader("ngrok-skip-browser-warning", "true");
  int code = http.GET();
  if (code == HTTP_CODE_OK) {
    String res = http.getString();
    res.trim();
    if (res == "UNLOCK") {
      Serial.println("[STATUS] Locker " + String(id) + " -> UNLOCK");
      unlockSolenoid();
    } else if (res == "LOCK" && isUnlocked) {
      Serial.println("[STATUS] Locker " + String(id) + " -> LOCK");
      lockSolenoid();
    }
  } else {
    Serial.println("[STATUS] Locker " + String(id) + " HTTP Error: " + String(code));
  }
  http.end();
}

String formatUID(uint8_t *uid, uint8_t len) {
  String r = "";
  for (int i = 0; i < len; i++) {
    if (uid[i] < 0x10)
      r += "0";
    r += String(uid[i], HEX);
  }
  r.toUpperCase();
  return r;
}

// ═══════════════════════════════════════════════
//   SETUP
// ═══════════════════════════════════════════════
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("\n[SYSTEM] SMART LOCKER I2C STARTING...");
  Serial.println("[CONFIG] Server: " + SERVER_URL);
  Serial.println("[CONFIG] Device ID: " + DEVICE_ID);

  pinMode(PIN_RELAY, OUTPUT);
  pinMode(PIN_BUZZER, OUTPUT);
  pinMode(PIN_LED, OUTPUT);
  relayOFF();

  connectWiFi();

  // Inisialisasi PN532
  nfc.begin();
  uint32_t versiondata = nfc.getFirmwareVersion();

  if (!versiondata) {
    Serial.println("[PN532] ERROR: Chip Not Found!");
    while (1) {
      blinkLED(1, 100);
      delay(100);
    }
  }

  Serial.print("[PN532] Found Chip! Firmware v");
  Serial.print((versiondata >> 16) & 0xFF);
  Serial.print(".");
  Serial.println((versiondata >> 8) & 0xFF);

  nfc.SAMConfig();
  nfcReady = true;
  Serial.println("[SYSTEM] Ready! Tap NFC card to scan.");
  beepDouble();
}

// ═══════════════════════════════════════════════
//   LOOP
// ═══════════════════════════════════════════════
void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[WiFi] Disconnected! Reconnecting...");
    connectWiFi();
  }

  // Auto lock
  if (isUnlocked && millis() - unlockStart > UNLOCK_DURATION) {
    lockSolenoid();
  }

  // Check register mode dari server
  if (millis() - lastModeCheck >= MODE_CHECK_INTERVAL) {
    lastModeCheck = millis();
    checkRegisterMode();
  }

  // Scan NFC
  if (nfcReady && millis() - lastNFCScan >= NFC_COOLDOWN) {
    uint8_t uid[7];
    uint8_t uidLen = 0;
    if (nfc.readPassiveTargetID(PN532_MIFARE_ISO14443A, uid, &uidLen, 500)) {
      String uidStr = formatUID(uid, uidLen);
      lastNFCScan = millis();

      Serial.println("--- NFC DETECTED: " + uidStr + " ---");
      Serial.println("[MODE] Current mode: " +
                     String(registerMode ? "REGISTER" : "ACCESS"));

      beep(80);
      sendNFCScan(uidStr, registerMode ? "register" : "access");
    }
  }

  // Server poll untuk locker status (Dual-Mode)
  if (!isUnlocked && millis() - lastPoll >= POLL_INTERVAL) {
    lastPoll = millis();
    for (int i = 0; i < NUM_LOCKERS; i++) {
        checkServerStatus(LOCKER_IDS[i]);
        delay(100); // Small gap between polls
        if (isUnlocked) break; // Stop loop if unlocked by one of them
    }
  }

  delay(10);
}
