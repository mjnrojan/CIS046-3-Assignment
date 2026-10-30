<?php

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/puzzle_cache.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json');

/**
 * Serves one puzzle directly from the session FIFO queue.
 * Memory retrieval takes < 5ms, avoiding blocking HTTP calls
 * to the third-party API during gameplay.
 */
$puzzle = popNextPuzzle();

if ($puzzle === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to retrieve puzzle from cache or remote service']);
    exit;
}

// Store the solution server-side only so the client cannot inspect it
$_SESSION['solution']  = (int) $puzzle['solution'];
$_SESSION['issued_at'] = time();

echo json_encode([
    'question' => $puzzle['question'],
], JSON_UNESCAPED_SLASHES);

