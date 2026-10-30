<?php

/**
 * =====================================================================
 * BANANA PUZZLE ARENA – 1v1 PUZZLE BATTLE API
 * =====================================================================
 *
 * Handles match session setup, score reporting, and Elo rating calculation
 * for real-time head-to-head duels.
 *
 * Features:
 * - Authenticated access via secure session cookies.
 * - Match history and rating retrieval (GET).
 * - Match initialization and opponent pairing (POST action="start").
 * - Match resolution and Elo rating calculation (POST action="finish"):
 *     - Win: +25 rating points
 *     - Loss: -15 rating points (minimum rating floor: 100)
 *     - Draw: 0 rating points
 * - Records match results into the `battle_matches` relational table.
 */

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/Database.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json');

$pdo = Database::getConnection();
$userId = (int) $_SESSION['user_id'];

/**
 * Handles GET requests: returns user's battle rating and recent match history.
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 1. Fetch current rating
    $stmt = $pdo->prepare('SELECT id, username, battle_rating FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // 2. Fetch last 5 matches
    $historyStmt = $pdo->prepare(
        'SELECT id, player1_score, player2_score, winner_id, played_at
         FROM battle_matches
         WHERE player1_id = ?
         ORDER BY played_at DESC
         LIMIT 5'
    );
    $historyStmt->execute([$userId]);
    $matches = $historyStmt->fetchAll();

    echo json_encode([
        'username'      => $user['username'],
        'battle_rating' => (int) $user['battle_rating'],
        'recent_matches' => $matches,
    ]);
    exit;
}

/**
 * Handles POST requests: starts or finishes a battle match.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!is_array($data) || empty($data['action'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Action parameter is required']);
        exit;
    }

    $action = $data['action'];

    // Action 1: START MATCH
    if ($action === 'start') {
        // Look up user's current rating to pair with an appropriately ranked opponent
        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userRow = $stmt->fetch();
        $userRating = (int) ($userRow['battle_rating'] ?? 500);

        // Challenger Bot names and rating variance (+/- 40 rating)
        $botNames = ['Grandmaster Banana', 'Puzzle Striker', 'Ape Rival', 'Monkey Master', 'Blitz Kong'];
        $chosenName = $botNames[array_rand($botNames)];
        $botRating = max(100, $userRating + rand(-35, 45));

        // Store active battle in session
        $_SESSION['active_battle'] = [
            'opponent_name'   => $chosenName,
            'opponent_rating' => $botRating,
            'started_at'      => time(),
        ];

        echo json_encode([
            'status'          => 'started',
            'duration'        => 180, // 3-minute match
            'max_strikes'     => 3,
            'opponent_name'   => $chosenName,
            'opponent_rating' => $botRating,
            'user_rating'     => $userRating,
        ]);
        exit;
    }

    // Action 2: FINISH MATCH & CALCULATE RATING
    if ($action === 'finish') {
        $playerScore = isset($data['player_score']) ? max(0, (int) $data['player_score']) : 0;
        $opponentScore = isset($data['opponent_score']) ? max(0, (int) $data['opponent_score']) : 0;
        $playerStrikes = isset($data['player_strikes']) ? max(0, (int) $data['player_strikes']) : 0;
        $opponentStrikes = isset($data['opponent_strikes']) ? max(0, (int) $data['opponent_strikes']) : 0;

        // Determine outcome
        // Win conditions:
        // - More points when match expires
        // - Opponent struck out (>= 3 strikes) while player survived
        $outcome = 'draw';
        $winnerId = null;
        $ratingDelta = 0;

        if ($playerStrikes >= 3 && $opponentStrikes < 3) {
            $outcome = 'loss';
        } elseif ($opponentStrikes >= 3 && $playerStrikes < 3) {
            $outcome = 'win';
            $winnerId = $userId;
        } elseif ($playerScore > $opponentScore) {
            $outcome = 'win';
            $winnerId = $userId;
        } elseif ($playerScore < $opponentScore) {
            $outcome = 'loss';
        } else {
            $outcome = 'draw';
        }

        // Calculate rating change
        if ($outcome === 'win') {
            $ratingDelta = +25;
        } elseif ($outcome === 'loss') {
            $ratingDelta = -15;
        } else {
            $ratingDelta = 0;
        }

        // Fetch current rating and calculate new rating with floor limit of 100
        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $currentRating = (int) $stmt->fetchColumn();

        $newRating = max(100, $currentRating + $ratingDelta);

        // Update rating in database
        $updateStmt = $pdo->prepare('UPDATE users SET battle_rating = ? WHERE id = ?');
        $updateStmt->execute([$newRating, $userId]);

        // Insert match record into battle_matches
        $matchStmt = $pdo->prepare(
            'INSERT INTO battle_matches (player1_id, player2_id, player1_score, player2_score, winner_id)
             VALUES (?, NULL, ?, ?, ?)'
        );
        $matchStmt->execute([$userId, $playerScore, $opponentScore, $winnerId]);

        unset($_SESSION['active_battle']);

        echo json_encode([
            'outcome'        => $outcome,
            'rating_delta'   => $ratingDelta,
            'current_rating' => $newRating,
            'player_score'   => $playerScore,
            'opponent_score' => $opponentScore,
            'winner_id'      => $winnerId,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown battle action']);
    exit;
}

http_response_code(405);
header('Allow: GET, POST');
echo json_encode(['error' => 'Method not allowed']);
