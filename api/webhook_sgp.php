<?php
// api/webhook_sgp.php
// Called by SGP: ?contrato=1234&faturaid=12345
header('Content-Type: application/json');
require_once 'db_push.php';
require_once 'push_lib.php';
$config = require_once __DIR__ . '/../.config.php';

// Security Check
$reqToken = $_GET['token'] ?? $_POST['token'] ?? '';
if ($reqToken !== $config['webhook_token']) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Invalid Token']);
    exit;
}

$contrato = $_GET['contrato'] ?? null;
$target = $_GET['target'] ?? null; // Support explicit 'target=all'
$phone = $_GET['telefone'] ?? $_GET['phone'] ?? null;

if (!$contrato && !$target && !$phone) {
    echo json_encode(['error' => 'Missing contrato, telefone or target parameter']);
    exit;
}

// Prepare Message Data
$title = $_GET['title'] ?? $_POST['title'] ?? 'Nova Mensagem';
$body = $_GET['msg'] ?? $_POST['msg'] ?? $_GET['body'] ?? $_POST['body'] ?? 'Você tem uma nova notificação.';
$url = $_GET['url'] ?? $_GET['linkurl'] ?? $_POST['url'] ?? '';
$msgData = [
    'title' => $title,
    'body' => $body,
    'url' => $url,
    'timestamp' => time()
];

// === BROADCAST MODE ===
if ($target === 'all' || $contrato === 'todos' || $contrato === 'all') {
    $subs = getAllSubscriptions();
    $count = 0;
    $errors = 0;

    foreach ($subs as $sub) {
        // Save message for Fetch-on-Push
        saveLastMessage($sub['endpoint'], $msgData);
        
        // Send Signal
        $res = sendPushSignal($sub['endpoint'], $sub['auth'], $sub['p256dh']);
        if ($res['success'] === true) {
            $count++;
        } else {
            $errors++;
            // AUTO-CLEANUP: If 410 (Gone), delete dead subscription
            if (isset($res['code']) && $res['code'] === 410) {
                deleteSubscription($sub['endpoint']);
            }
        }
    }

    echo json_encode([
        'status' => 'Broadcast Completed',
        'sent' => $count,
        'errors' => $errors,
        'total_subs' => count($subs)
    ]);
    exit;
}

// === SINGLE USER MODE ===

// 1. Find subscription for this contract
// $contrato captured at top
// $phone captured at top
$sub = null;

if ($phone) {
    // Sanitize: Remove non-digits
    $phone = preg_replace('/\D/', '', $phone);
    
    // Remove "55" prefix if it exists (but keep it if the number is short? SGP usually sends Full DDI+DDD+Num)
    // Example: 5575999999999 -> 75999999999
    // Example: 557533333333 -> 7533333333
    if (strpos($phone, '55') === 0) {
        $phone = substr($phone, 2);
    }
    
    // === PHONE LOOKUP MODE ===
    $subs = getSubscriptionsByPhone($phone);
    if (!empty($subs)) {
        // Send to ALL devices with this phone (Multi-device support)
        // Similar to broadcast but limited scope
        $count = 0;
        foreach ($subs as $s) {
            saveLastMessage($s['endpoint'], $msgData);
            $res = sendPushSignal($s['endpoint'], $s['auth'], $s['p256dh']);
            if ($res['success']) $count++;
            elseif ($res['code'] === 410) deleteSubscription($s['endpoint']);
        }
        echo json_encode(['status' => 'Processed by Phone', 'phone' => $phone, 'devices_reached' => $count]);
        exit;
    }
}

if ($contrato) {
    $sub = getSubscription($contrato);

    // RETRY Logic: If not found, and input looks like a raw CPF (11 digits), try formatting it.
    if (!$sub && preg_match('/^\d{11}$/', $contrato)) {
        // Format as 000.000.000-00
        $formatted = sprintf('%s.%s.%s-%s',
            substr($contrato, 0, 3),
            substr($contrato, 3, 3),
            substr($contrato, 6, 3),
            substr($contrato, 9, 2)
        );
        $sub = getSubscription($formatted);
    }

    // RETRY Logic: If input IS formatted, try stripping it (just in case DB has raw)
    if (!$sub && preg_match('/^\d{3}\.\d{3}\.\d{3}\-\d{2}$/', $contrato)) {
        $raw = preg_replace('/\D/', '', $contrato);
        $sub = getSubscription($raw);
    }
}

if (!$sub) {
    echo json_encode(['status' => 'No subscriber found', 'ref' => $contrato, 'phone' => $phone]);
    exit;
}

// Save message for "Tickle-and-Fetch"
saveLastMessage($sub['endpoint'], $msgData);

// 3. Send Push Signal (Tickle)
$result = sendPushSignal($sub['endpoint'], $sub['auth'], $sub['p256dh']);

// AUTO-CLEANUP
if ($result['success'] === false && isset($result['code']) && $result['code'] === 410) {
    deleteSubscription($sub['endpoint']);
    $result['status'] = 'Deleted (410 Gone)';
}

echo json_encode([
    'status' => 'Processed', 
    'target' => $contrato, 
    'push_result' => $result
]);
