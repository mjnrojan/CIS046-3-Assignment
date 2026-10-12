<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// HTTP redirects to HTTPS. SSL verify is off because this PHP build has no CA bundle.
$apiUrl = 'https://marcconrad.com/uob/banana/api.php';

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 20,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$response = file_get_contents($apiUrl, false, $context);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Failed to reach the banana API',
    ]);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data) || !isset($data['question'], $data['solution'])) {
    http_response_code(502);
    echo json_encode([
        'error' => 'Unexpected response from the banana API',
    ]);
    exit;
}

echo json_encode([
    'question' => $data['question'],
    'solution' => (int) $data['solution'],
], JSON_UNESCAPED_SLASHES);
