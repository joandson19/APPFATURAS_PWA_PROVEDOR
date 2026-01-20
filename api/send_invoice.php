<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method Not Allowed"]);
    exit;
}

$config = require_once __DIR__ . '/../.config.php';
$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['contract_id']) || empty($input['type'])) {
    http_response_code(400);
    echo json_encode(["message" => "Contract ID and Type are required."]);
    exit;
}

$contractId = $input['contract_id'];
$type = $input['type']; // 'email' or 'sms'

// Validate type
if (!in_array($type, ['email', 'sms'])) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid type. Use 'email' or 'sms'."]);
    exit;
}

$curl = curl_init();

$postData = [
    'token' => $config['sgp_token'],
    'app' => $config['sgp_app_name'],
    'contrato' => $contractId,
    'tipo' => $type
];

curl_setopt_array($curl, array(
  CURLOPT_URL => $config['sgp_url'] . '/api/ura/enviafatura/',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => $postData, // Sending as form-data/x-www-form-urlencoded matching screenshot (screenshots show form-data but x-www-form usually simpler for PHP array)
  // Screenshot shows Body: form-data. Curl default with array is multipart/form-data.
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    http_response_code(500);
    echo json_encode(["message" => "Erro cURL: " . $err]);
} else {
    // Check if response is valid JSON
    $json = json_decode($response);
    if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(502);
        echo json_encode(["message" => "Invalid response from SGP", "raw" => $response]);
    } else {
        echo $response;
    }
}
