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

if (empty($input['invoice_id']) || empty($input['contract_id'])) {
    http_response_code(400);
    echo json_encode(["message" => "ID da fatura e do contrato são obrigatórios."]);
    exit;
}

$invoiceId = $input['invoice_id'];
$contractId = $input['contract_id'];

$curl = curl_init();

$postData = [
    'app' => $config['sgp_app_name'],
    'token' => $config['sgp_token'],
    'contrato' => $contractId
];

curl_setopt_array($curl, array(
    CURLOPT_URL => $config['sgp_url'] . '/api/ura/pagamento/pix/' . $invoiceId,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $postData,
));

$response = curl_exec($curl);
$err = curl_error($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

curl_close($curl);

if ($err) {
    http_response_code(500);
    echo json_encode(["message" => "Erro cURL: " . $err]);
} else {
    // SGP returns simple JSON { status: 1, pix: "..." }
    echo $response;
}
