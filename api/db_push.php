<?php
// api/db_push.php
// Handles SQLite connection for PWA Push Subscriptions

$dbFile = __DIR__ . '/../notifications.db';

try {
    $pdo = new PDO("sqlite:$dbFile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_ref TEXT NOT NULL,  -- CPF or Contract ID
        endpoint TEXT NOT NULL UNIQUE,
        p256dh TEXT NOT NULL,
        auth TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add index for fast lookup
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_ref ON subscriptions (user_ref)");

    // SCHEMA UPDATE: Add last_message column if not exists
    try {
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN last_message TEXT");
    } catch (PDOException $e) {}

    // SCHEMA UPDATE: Add phone column if not exists
    try {
        $pdo->exec("ALTER TABLE subscriptions ADD COLUMN phone TEXT");
        // Index for phone search
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_phone ON subscriptions (phone)");
    } catch (PDOException $e) {}

} catch (PDOException $e) {
    die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}

function saveSubscription($userRef, $subscription, $phone = null) {
    global $pdo;
    
    try {
        // Deconstruct subscription object (standard Web Push format)
        $endpoint = $subscription['endpoint'] ?? '';
        $keys = $subscription['keys'] ?? [];
        $p256dh = $keys['p256dh'] ?? '';
        $auth = $keys['auth'] ?? '';

        if (empty($endpoint) || empty($p256dh) || empty($auth)) {
            file_put_contents(__DIR__ . '/db_error_log.txt', "Invalid Keys/Endpoint: " . json_encode($subscription) . "\n", FILE_APPEND);
            return false;
        }

        // Sanitize Phone (Digits Only)
        $phone = $phone ? preg_replace('/\D/', '', $phone) : null;

        // OPTIMIZATION: Check if exists exactly as is to avoid unnecessary writes
        $check = $pdo->prepare("SELECT id, user_ref, p256dh, auth, phone FROM subscriptions WHERE endpoint = :endpoint");
        $check->execute([':endpoint' => $endpoint]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // If everything matches, DO NOTHING (Idempotent)
            // Loose comparison (==) handles nulls/empty strings
            if ($existing['user_ref'] == $userRef && 
                $existing['p256dh'] == $p256dh && 
                $existing['auth'] == $auth &&
                $existing['phone'] == $phone) {
                return true; 
            }
        }

        $stmt = $pdo->prepare("INSERT OR REPLACE INTO subscriptions (user_ref, endpoint, p256dh, auth, phone) VALUES (:user_ref, :endpoint, :p256dh, :auth, :phone)");
        $result = $stmt->execute([
            ':user_ref' => $userRef,
            ':endpoint' => $endpoint,
            ':p256dh' => $p256dh,
            ':auth' => $auth,
            ':phone' => $phone
        ]);
        
        return $result;
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/db_error_log.txt', "DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

function getSubscription($userRef) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_ref = :user_ref ORDER BY id DESC LIMIT 1");
    $stmt->execute([':user_ref' => $userRef]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getSubscriptionsByPhone($phone) {
    global $pdo;
    // Sanitize input
    $phone = preg_replace('/\D/', '', $phone);
    if (empty($phone)) return [];

    // Support partial match? No, SGP sends full number. 
    // Usually SGP sends with country code? The previous test showed (75)...
    // We will strip everything and match exact digits.
    // If table has '55759...' and search is '759...', we might need LIKE.
    // Let's assume strict match first, or LIKE suffix.
    
    // Using LIKE to match suffix (e.g. searching 99999999 returns 557599999999)
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE phone LIKE :phone OR phone = :exact");
    $stmt->execute([':phone' => "%$phone", ':exact' => $phone]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function saveLastMessage($endpoint, $data) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE subscriptions SET last_message = :msg WHERE endpoint = :endpoint");
        return $stmt->execute([
            ':msg' => json_encode($data),
            ':endpoint' => $endpoint
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

function getLastMessage($endpoint) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT last_message FROM subscriptions WHERE endpoint = :endpoint");
    $stmt->execute([':endpoint' => $endpoint]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['last_message']) {
        // Clear message after reading (One-time fetch)
        $pdo->prepare("UPDATE subscriptions SET last_message = NULL WHERE endpoint = :endpoint")->execute([':endpoint' => $endpoint]);
        return json_decode($row['last_message'], true);
    }
    return null;
}


function getAllSubscriptions() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM subscriptions");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function deleteSubscription($endpoint) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM subscriptions WHERE endpoint = :endpoint");
        return $stmt->execute([':endpoint' => $endpoint]);
    } catch (PDOException $e) {
        return false;
    }
}
