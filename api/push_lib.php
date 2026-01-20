<?php
// api/push_lib.php
// Minimalist VAPID Sender (Payload-less to avoid encryption complexity)

require_once 'push_config.php';

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function sendPushSignal($endpoint, $auth, $p256dh) {
    $keys = getVapidKeys();
    if (!$keys || !isset($keys['privateKey'])) {
        return ['success' => false, 'error' => 'Server VAPID keys missing'];
    }

    // 1. Parse Endpoint to get Origin (Audience)
    $parsedUrl = parse_url($endpoint);
    $audience = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

    // 2. Create JWT Claim
    $header = [
        'typ' => 'JWT',
        'alg' => 'ES256'
    ];
    $payload = [
        'aud' => $audience,
        'exp' => time() + (12 * 60 * 60), // 12 hours
        'sub' => 'mailto:admin@faturafacil.com' // Should be configurable
    ];

    $unsignedToken = base64UrlEncode(json_encode($header)) . '.' . base64UrlEncode(json_encode($payload));

    // 3. Sign JWT using ECDSA (ES256)
    // Convert PEM to OpenSSL Key Resource
    // Note: The key in push_config might be raw or PEM. Assuming we store PEM in json for simplicity in this env.
    // If we only have raw, this gets harder. Let's verify push_config structure.
    // Ideally push_config generates PEM.
    
    $privateKey = $keys['privateKey']; 
    // Usually VAPID keys are stored as base64 raw numbers, but openssl needs PEM.
    // For this environment, we assume the helper generated a usable PEM or compatible format.
    // If generation failed, this will fail.
    
    $signature = '';
    if (!openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
         return ['success' => false, 'error' => 'OpenSSL Sign Failed: ' . openssl_error_string()];
    }

    // FIX: OpenSSL returns ASN.1 DER, but VAPID needs Raw P-1363 (R|S)
    $rawSignature = signatureDerToRaw($signature, 64);
    $jwt = $unsignedToken . '.' . base64UrlEncode($rawSignature);

    // 4. Send Request (Empty Body for Tickle)
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: vapid t=' . $jwt . ', k=' . $keys['publicKey'],
        'TTL: 60',
        'Content-Length: 0' // No payload encryption needed
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        return ['success' => true];
    } else {
        return ['success' => false, 'code' => $httpCode, 'response' => $result];
    }
}

// Helper to convert ASN.1 DER signature to Raw P-1363 (R|S)
function signatureDerToRaw($der, $keySize) {
    // DER structure: 0x30 + len + 0x02 + lenR + R + 0x02 + lenS + S
    $offset = 2; // Skip Sequence tag & len
    
    // Read R
    $offset++; // Skip Integer tag (0x02)
    $lenR = ord($der[$offset]);
    $offset++;
    $r = substr($der, $offset, $lenR);
    $offset += $lenR;
    
    // Read S
    $offset++; // Skip Integer tag (0x02)
    $lenS = ord($der[$offset]);
    $offset++;
    $s = substr($der, $offset, $lenS);
    
    // Trim leading zeros (DER adds 0x00 if MSB is 1 to indicate positive)
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    
    // Pad to fixed length (32 bytes each for P-256)
    $padLen = $keySize / 2;
    $r = str_pad($r, $padLen, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, $padLen, "\x00", STR_PAD_LEFT);
    
    return $r . $s;
}
