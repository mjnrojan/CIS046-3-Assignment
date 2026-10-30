<?php

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/Database.php';

startSecureSession();
$userId = requireLogin();

header('Content-Type: application/json');

/**
 * Handles Classic Ladder mode progression.
 *
 * GET: Returns the user's current ladder node, total ladder points,
 *      and current attempt count on the active puzzle.
 * POST: Evaluates the player's guess against $_SESSION['solution'].
 *       - 1st attempt solve: awards 50 points.
 *       - Mistakes chip away 15 points per wrong attempt (floor at 10 pts).
 *       - Wrong answers allow unlimited retries on the same node.
 *       - On solve, advances ladder_node and updates ladder_points in MySQL.
 */

$pdo = Database::getConnection();

// --- GET Request: Return current ladder state ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare('SELECT ladder_node, ladder_points FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    $attempts = isset($_SESSION['ladder_attempts']) ? (int) $_SESSION['ladder_attempts'] : 0;
    $potentialPoints = max(10, 50 - ($attempts * 15));

    echo json_encode([
        'ladder_node'      => isset($user['ladder_node']) ? (int) $user['ladder_node'] : 1,
        'ladder_points'    => isset($user['ladder_points']) ? (int) $user['ladder_points'] : 0,
        'attempts'         => $attempts,
        'potential_points' => $potentialPoints,
    ]);
    exit;
}

// --- POST Request: Evaluate guess ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data) || !isset($data['guess']) || !preg_match('/^[0-9]$/', (string) $data['guess'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Guess must be an integer from 0 to 9']);
    exit;
}

$guess = (int) $data['guess'];

if (!isset($_SESSION['solution'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No active puzzle to answer. Load a puzzle first.']);
    exit;
}

$expected = (int) $_SESSION['solution'];

if (!isset($_SESSION['ladder_attempts'])) {
    $_SESSION['ladder_attempts'] = 0;
}

// Check if guess is correct
if ($guess !== $expected) {
    $_SESSION['ladder_attempts']++;
    $attempts = $_SESSION['ladder_attempts'];
    $potentialPoints = max(10, 50 - ($attempts * 15));

    echo json_encode([
        'correct'          => false,
        'message'          => 'Incorrect guess. Try again!',
        'attempts'         => $attempts,
        'potential_points' => $potentialPoints,
    ]);
    exit;
}

// --- Correct Solve: Calculate points & persist progression ---
$attempts = $_SESSION['ladder_attempts'];
$pointsEarned = max(10, 50 - ($attempts * 15));

// Update user ladder points and advance to next node
$stmt = $pdo->prepare(
    'UPDATE users 
     SET ladder_points = ladder_points + ?, 
         ladder_node   = ladder_node + 1 
     WHERE id = ?'
);
$stmt->execute([$pointsEarned, $userId]);

// Fetch updated values
$stmt = $pdo->prepare('SELECT ladder_node, ladder_points FROM users WHERE id = ?');
$stmt->execute([$userId]);
$updatedUser = $stmt->fetch();

// Reset ladder attempts and clear current puzzle solution
unset($_SESSION['solution'], $_SESSION['issued_at']);
$_SESSION['ladder_attempts'] = 0;

echo json_encode([
    'correct'             => true,
    'message'             => 'Puzzle Solved!',
    'points_earned'       => $pointsEarned,
    'total_ladder_points' => (int) $updatedUser['ladder_points'],
    'next_node'           => (int) $updatedUser['ladder_node'],
]);
