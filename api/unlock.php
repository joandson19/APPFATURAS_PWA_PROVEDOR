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
    echo json_encode(["message" => "ID do contrato é obrigatório."]);
    exit;
}

$curl = curl_init();

$postData = [
  'app' => $config['sgp_app_name'],
  'token' => $config['sgp_token'],
  'contrato' => $input['contract_id'],
  'data_promessa' => date('Y-m-d'), // Today as promise date? Or +3 days? User snippet had static. Usually today means "I promise to pay".
  'enviar_sms' => 1
];

curl_setopt_array($curl, array(
    CURLOPT_URL => $config['sgp_url'] . '/api/ura/liberacaopromessa/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($postData), // SGP usually accepts JSON if content-type header is set, otherwise form-data. Snippet showed JSON body string.
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    http_response_code(500);
    echo json_encode(["message" => "Erro cURL: " . $err]);
} else {
    echo $response;
}
