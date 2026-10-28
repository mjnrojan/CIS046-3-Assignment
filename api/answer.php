<?php

require_once __DIR__ . '/../src/session.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json');

/**
 * Allows POST only.
 * Answering uses up the stored puzzle, so a GET would be the wrong HTTP method.
 */
function rejectUnlessPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return;
    }

    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

/**
 * Reads guess from a JSON body such as {"guess": 4}.
 * Accepts an integer, or a one-character string, from 0 to 9.
 * Returns null when the value is missing or not in that range.
 */
function readGuess(): ?int
{
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);

    if (!is_array($data) || !array_key_exists('guess', $data)) {
        return null;
    }

    $guess = $data['guess'];

    if (is_int($guess) && $guess >= 0 && $guess <= 9) {
        return $guess;
    }

    if (is_string($guess) && preg_match('/^[0-9]$/', $guess) === 1) {
        return (int) $guess;
    }

    return null;
}

rejectUnlessPost();

$guess = readGuess();

if ($guess === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Guess must be an integer from 0 to 9']);
    exit;
}

if (!isset($_SESSION['solution'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No puzzle to answer']);
    exit;
}

$expected = (int) $_SESSION['solution'];
unset($_SESSION['solution'], $_SESSION['issued_at']);

echo json_encode([
    'correct' => $guess === $expected,
]);
