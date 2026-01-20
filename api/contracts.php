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

if (empty($input['cpf'])) {
    http_response_code(400);
    echo json_encode(["message" => "CPF é obrigatório."]);
    exit;
}

// O SGP geralmente espera o CPF/CNPJ formatado
$cpf = $input['cpf'];

$curl = curl_init();

$postData = [
    "app" => $config['sgp_app_name'],
    "token" => $config['sgp_token'],
    // "status" => 1, // Removido para listar também suspensos/cancelados
    // "tipo" => "F",
    // "plano" => 1,
    "exibir_endereco" => true,
    "cpfcnpj" => $cpf, 
    // "data_cadastro_ini" => "2000-01-01",
    // "data_cadastro_fim" => date('Y-m-d'), 
    // "data_alteracao_ini" => "2000-01-01", 
    // "data_alteracao_fim" => date('Y-m-d') 
];

curl_setopt_array($curl, array(
  CURLOPT_URL => $config['sgp_url'] . '/api/ura/listacontrato/',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 30,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => json_encode($postData),
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json'
  ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

if ($err) {
    http_response_code(500);
    echo json_encode(["message" => "Erro cURL: " . $err]);
} else {
    // Check if response is valid JSON, if not proxy raw (debugging)
    $json = json_decode($response);
    if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(502);
        echo json_encode(["message" => "Resposta inválida do SGP", "raw" => $response]);
    } else {
        echo $response;
    }
}
