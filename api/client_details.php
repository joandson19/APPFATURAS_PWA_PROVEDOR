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

if (empty($input['contract_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "Contract ID is required."]);
    exit;
}

$contractId = $input['contract_id'];

$curl = curl_init();

// SGP consultacliente uses GET with query params usually, but let's check the user's screenshot.
// Screenshot showed GET request.
// URL: .../api/ura/consultacliente/?token=...&app=...&contrato=...

$params = [
    'token' => $config['sgp_token'],
    'app' => $config['sgp_app_name'],
    'contrato' => $contractId,
    'radius' => '1' // Force realtime check
];

$queryString = http_build_query($params);
$url = $config['sgp_url'] . '/api/ura/consultacliente/?' . $queryString;

curl_setopt_array($curl, array(
  CURLOPT_URL => $url,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
));

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

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
