<?php

session_start();

header('Content-Type: application/json');

/**
 * Loads one puzzle from the Banana API.
 * PHP makes this call so the browser never receives the answer.
 * Returns question and solution, or an error key when the call fails.
 */
function fetchBananaPuzzle(): array
{
    // The documented HTTP URL redirects to HTTPS. out=json asks for the image link, not CSV or base64.
    $apiUrl = 'https://marcconrad.com/uob/banana/api.php?out=json&base64=no';

    // Certificate checks are off because this PHP install has no CA bundle.
    // The plan suggested 5 seconds. This host often takes longer, so the limit is 15 seconds.
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $response = file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        return ['error' => 'Failed to reach the banana API'];
    }

    $data = json_decode($response, true);

    if (!is_array($data) || !isset($data['question'], $data['solution'])) {
        return ['error' => 'Unexpected response from the banana API'];
    }

    return [
        'question' => $data['question'],
        'solution' => $data['solution'],
    ];
}

$puzzle = fetchBananaPuzzle();

if (isset($puzzle['error'])) {
    http_response_code(502);
    echo json_encode(['error' => $puzzle['error']]);
    exit;
}

$_SESSION['solution'] = (int) $puzzle['solution'];
$_SESSION['issued_at'] = time();

echo json_encode([
    'question' => $puzzle['question'],
], JSON_UNESCAPED_SLASHES);
