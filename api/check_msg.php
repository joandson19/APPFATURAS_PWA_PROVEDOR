<?php
// api/check_msg.php
header('Content-Type: application/json');
require_once 'db_push.php';

// Service Worker sends the endpoint URL it is subscribed to to identify itself
// We use POST to avoid exposing endpoint length limits in GET
$data = json_decode(file_get_contents('php://input'), true);
$endpoint = $data['endpoint'] ?? $_GET['endpoint'] ?? '';

if (!$endpoint) {
    echo json_encode(['error' => 'No endpoint provided']);
    exit;
}

$msg = getLastMessage($endpoint);

if ($msg) {
    echo json_encode($msg);
} else {
    // Return empty if no message found (fallback)
    echo json_encode([
        'title' => 'FaturaFacil',
        'body' => 'Verifique suas faturas pendentes.',
        'url' => '/'
    ]);
}
