<?php
// api/subscribe.php
header('Content-Type: application/json');

// 1. IMMEDIATE LOGGING TEST
$logFile = __DIR__ . '/subscribe_log.txt';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - ACCESS HIT\n", FILE_APPEND);

// 2. GET TEST MODE (Manual verify in browser)
if (isset($_GET['test'])) {
    echo json_encode(['status' => 'Script Reached', 'log_writable' => is_writable(__DIR__)]);
    exit;
}

require_once 'db_push.php';

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    file_put_contents($logFile, "ERROR: NO DATA RECEIVED\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$userRef = $data['user_ref'] ?? 'unknown'; 
$subscription = $data['subscription'] ?? [];
$phone = $data['phone'] ?? null; // Capture Phone

$logEntry = date('Y-m-d H:i:s') . " - Receive REF: " . $userRef . " PHONE: " . ($phone ?? 'N/A') . "\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

if (saveSubscription($userRef, $subscription, $phone)) {
    file_put_contents($logFile, "SUCCESS SAVE\n", FILE_APPEND);
    echo json_encode(['success' => true]);
} else {
    file_put_contents($logFile, "FAIL SAVE\n", FILE_APPEND);
    http_response_code(500);
    file_put_contents('debug_log.txt', date('Y-m-d H:i:s') . " - Falha ao salvar: " . json_encode($data) . "\n", FILE_APPEND);
    echo json_encode(['error' => 'Failed to save subscription']);
}
