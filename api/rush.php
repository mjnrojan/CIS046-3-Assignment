<?php

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/Database.php';

startSecureSession();
$userId = requireLogin();

header('Content-Type: application/json');

/**
 * Handles Puzzle Rush score submissions.
 *
 * Accepts POST JSON payload containing:
 * - solved: Total count of puzzles solved during the run.
 * - mode: The selected format ('1m', '5m', '10m', or 'survival').
 * - strikes: Number of strikes accumulated (0 to 3).
 *
 * Inserts a match summary into the `scores` table, and updates
 * the user's `rush_high_score` if the new score surpasses their record.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data) || !isset($data['solved'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing score data']);
    exit;
}

$solved = max(0, (int) $data['solved']);
$mode = isset($data['mode']) && in_array($data['mode'], ['1m', '5m', '10m', 'survival'], true)
    ? $data['mode']
    : '5m';
$strikes = isset($data['strikes']) ? min(3, max(0, (int) $data['strikes'])) : 3;
$totalAttempts = $solved + $strikes;

try {
    $pdo = Database::getConnection();

    // 1. Record run in scores table
    $stmt = $pdo->prepare(
        'INSERT INTO scores (user_id, mode, score, correct, total) 
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, 'rush_' . $mode, $solved, $solved, $totalAttempts]);

    // 2. Fetch current user's high score
    $stmt = $pdo->prepare('SELECT rush_high_score FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $currentHigh = isset($user['rush_high_score']) ? (int) $user['rush_high_score'] : 0;

    $isNewHigh = $solved > $currentHigh;
    $finalHigh = $isNewHigh ? $solved : $currentHigh;

    // 3. Update high score if beaten
    if ($isNewHigh) {
        $stmt = $pdo->prepare('UPDATE users SET rush_high_score = ? WHERE id = ?');
        $stmt->execute([$finalHigh, $userId]);
    }

    echo json_encode([
        'saved'             => true,
        'solved'            => $solved,
        'mode'              => $mode,
        'is_new_high_score' => $isNewHigh,
        'rush_high_score'   => $finalHigh,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error saving rush score: ' . $e->getMessage()]);
}
