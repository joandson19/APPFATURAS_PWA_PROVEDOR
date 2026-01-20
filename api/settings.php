<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$config = require_once __DIR__ . '/../.config.php';

// Only expose safe public settings
echo json_encode([
    'whatsapp_number' => $config['whatsapp_number'] ?? null,
    'app_name' => 'FaturaFacil'
]);
