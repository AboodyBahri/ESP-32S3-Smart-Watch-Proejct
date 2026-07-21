<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

define('DATA_FILE', __DIR__ . '/data.json');
define('MAX_POINTS', 120); // 2 minutes of history at 1 reading/sec

// ── ESP32 sends data (POST) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $entry = [
        'ts'   => time(),
        'bpm'  => isset($input['bpm'])  ? (int)$input['bpm']     : 0,
        'temp' => isset($input['temp']) ? (float)$input['temp']  : 0,
        'hum'  => isset($input['hum'])  ? (float)$input['hum']   : 0,
        'pres' => isset($input['pres']) ? (float)$input['pres']  : 0,
        'finger' => isset($input['finger']) ? (bool)$input['finger'] : false,
    ];

    // Load existing data
    $data = [];
    if (file_exists(DATA_FILE)) {
        $data = json_decode(file_get_contents(DATA_FILE), true) ?? [];
    }

    // Append and trim to MAX_POINTS
    $data[] = $entry;
    if (count($data) > MAX_POINTS) {
        $data = array_slice($data, -MAX_POINTS);
    }

    file_put_contents(DATA_FILE, json_encode($data), LOCK_EX);
    echo json_encode(['status' => 'ok']);
    exit;
}

// ── Dashboard fetches data (GET) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists(DATA_FILE)) {
        echo json_encode([]);
        exit;
    }
    echo file_get_contents(DATA_FILE);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
