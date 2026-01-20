<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$config = require_once __DIR__ . '/../.config.php';

// Fetch from SGP
$url = $config['sgp_url'] . '/api/ura/manutencao/list/?token=' . $config['sgp_token'] . '&app=' . $config['sgp_app_name'];

$curl = curl_init();
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
curl_close($curl);

if ($err) {
    echo json_encode(["status" => "error", "message" => "Erro de conexão: $err"]);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data)) {
    // SGP might return error object or empty
    echo json_encode([]);
    exit;
}

$activeMaintenances = [];
$now = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));

foreach ($data as $item) {
	if (empty($item['ativa'])) {
		continue; 
	}
    // Check if 'data_final' exists
    if (!empty($item['data_final'])) {
        try {
            $end = new DateTime($item['data_final'], new DateTimeZone('America/Sao_Paulo'));
            
            // If End Date is in the past, skip it, regardless of 'ativa' flag
            if ($end < $now) {
                continue;
            }
        } catch (Exception $e) {
            // Invalid date format, maybe skip or include cautiously
            continue;
        }
    }
    
    // Optional: Filter only 'ativa' == true (though user said even active ones are closed, checking date is safer)
    // if (!$item['ativa']) continue; 

    // Intelligent Message Selection
    $msg = $item['mensagem_central'];
    if (empty($msg)) $msg = $item['mensagem_ura']; // Fallback to URA message
    if (empty($msg)) $msg = $item['descricao'];    // Fallback to Description
    if (empty($msg)) $msg = 'Manutenção programada na rede.';

    // Add to list
    $activeMaintenances[] = [
        'id' => $item['id'],
        'titulo' => $item['descricao'] ?? 'Aviso Importante',
        'mensagem' => $msg,
        'inicio' => $item['data_inicial'],
        'fim' => $item['data_final'],
        'severidade' => $item['severidade'] ?? 'Indisponibilidade'
    ];
}

echo json_encode($activeMaintenances);
