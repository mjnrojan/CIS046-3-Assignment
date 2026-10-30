<?php

/**
 * =====================================================================
 * BANANA PUZZLE ARENA – 1v1 PUZZLE BATTLE API
 * =====================================================================
 *
 * Implements real-time multiplayer matchmaking and 1v1 duel synchronization:
 * - Elo-based matchmaking queue (|rating difference| <= 200).
 * - Heartbeat TTL (10s) to prevent ghost players.
 * - Bidirectional live room polling for 2 connected browsers.
 * - Challenger Bot fallback when solo or after search timeout.
 * - Match outcome resolution and Elo rating updates (+25 win / -15 loss).
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
    $stmt = $pdo->prepare('SELECT id, username, battle_rating FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $historyStmt = $pdo->prepare(
        'SELECT id, player1_score, player2_score, winner_id, played_at
         FROM battle_matches
         WHERE player1_id = ? OR player2_id = ?
         ORDER BY played_at DESC
         LIMIT 5'
    );
    $historyStmt->execute([$userId, $userId]);
    $matches = $historyStmt->fetchAll();

    echo json_encode([
        'username'       => $user['username'],
        'battle_rating'  => (int) $user['battle_rating'],
        'recent_matches' => $matches,
    ]);
    exit;
}

/**
 * Handles POST requests: matchmaking lifecycle and room state synchronization.
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

    // -------------------------------------------------------------
    // ACTION 1: QUEUE JOIN
    // -------------------------------------------------------------
    if ($action === 'queue_join') {
        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $rating = (int) $stmt->fetchColumn();

        $upsert = $pdo->prepare(
            'INSERT INTO matchmaking_queue (user_id, rating, status, room_id, updated_at)
             VALUES (?, ?, "searching", NULL, NOW())
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), status = "searching", room_id = NULL, updated_at = NOW()'
        );
        $upsert->execute([$userId, $rating]);

        echo json_encode([
            'status' => 'searching',
            'rating' => $rating,
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION 2: QUEUE POLL (Check for matches & keep alive)
    // -------------------------------------------------------------
    if ($action === 'queue_poll') {
        // Refresh our heartbeat timestamp
        $hbStmt = $pdo->prepare('UPDATE matchmaking_queue SET updated_at = NOW() WHERE user_id = ? AND status = "searching"');
        $hbStmt->execute([$userId]);

        // First, check if someone else matched with us
        $myQStmt = $pdo->prepare('SELECT status, room_id, rating FROM matchmaking_queue WHERE user_id = ?');
        $myQStmt->execute([$userId]);
        $myQueue = $myQStmt->fetch();

        if ($myQueue && $myQueue['status'] === 'matched' && !empty($myQueue['room_id'])) {
            $roomId = $myQueue['room_id'];

            // Fetch room combatants
            $roomStmt = $pdo->prepare('SELECT * FROM battle_rooms WHERE room_id = ?');
            $roomStmt->execute([$roomId]);
            $room = $roomStmt->fetch();

            if ($room) {
                $opponentId = ($room['player1_id'] === $userId) ? $room['player2_id'] : $room['player1_id'];
                $oppStmt = $pdo->prepare('SELECT username, battle_rating FROM users WHERE id = ?');
                $oppStmt->execute([$opponentId]);
                $opponent = $oppStmt->fetch();

                echo json_encode([
                    'status'          => 'matched',
                    'room_id'         => $roomId,
                    'role'            => ($room['player1_id'] === $userId) ? 'player1' : 'player2',
                    'opponent_name'   => $opponent['username'],
                    'opponent_rating' => (int) $opponent['battle_rating'],
                    'user_rating'     => (int) $myQueue['rating'],
                ]);
                exit;
            }
        }

        // Otherwise, search for a live opponent within 200 Elo
        $myRating = (int) ($myQueue['rating'] ?? 500);

        $findStmt = $pdo->prepare(
            'SELECT q.user_id, q.rating, u.username
             FROM matchmaking_queue q
             JOIN users u ON q.user_id = u.id
             WHERE q.user_id != ?
               AND q.status = "searching"
               AND ABS(q.rating - ?) <= 200
               AND q.updated_at >= NOW() - INTERVAL 10 SECOND
             ORDER BY ABS(q.rating - ?) ASC
             LIMIT 1'
        );
        $findStmt->execute([$userId, $myRating, $myRating]);
        $candidate = $findStmt->fetch();

        if ($candidate) {
            $candidateId = (int) $candidate['user_id'];
            $roomId = 'room_' . bin2hex(random_bytes(8));

            // Atomic pairing
            $claimStmt = $pdo->prepare(
                'UPDATE matchmaking_queue
                 SET status = "matched", room_id = ?
                 WHERE user_id = ? AND status = "searching"'
            );
            $claimStmt->execute([$roomId, $candidateId]);

            if ($claimStmt->rowCount() === 1) {
                // We successfully claimed the opponent! Update our own queue row
                $selfClaim = $pdo->prepare(
                    'UPDATE matchmaking_queue SET status = "matched", room_id = ? WHERE user_id = ?'
                );
                $selfClaim->execute([$roomId, $userId]);

                // Create the active battle room
                $roomCreate = $pdo->prepare(
                    'INSERT INTO battle_rooms (room_id, player1_id, player2_id, status)
                     VALUES (?, ?, ?, "active")'
                );
                $roomCreate->execute([$roomId, $userId, $candidateId]);

                echo json_encode([
                    'status'          => 'matched',
                    'room_id'         => $roomId,
                    'role'            => 'player1',
                    'opponent_name'   => $candidate['username'],
                    'opponent_rating' => (int) $candidate['rating'],
                    'user_rating'     => $myRating,
                ]);
                exit;
            }
        }

        echo json_encode(['status' => 'searching']);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION 3: QUEUE LEAVE / CANCEL
    // -------------------------------------------------------------
    if ($action === 'queue_leave') {
        $del = $pdo->prepare('DELETE FROM matchmaking_queue WHERE user_id = ?');
        $del->execute([$userId]);
        echo json_encode(['status' => 'cancelled']);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION 4: ROOM SYNC (Bidirectional live score & strikes)
    // -------------------------------------------------------------
    if ($action === 'room_sync') {
        $roomId = $data['room_id'] ?? '';
        $score = isset($data['score']) ? max(0, (int) $data['score']) : 0;
        $strikes = isset($data['strikes']) ? max(0, (int) $data['strikes']) : 0;

        if (empty($roomId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing room_id']);
            exit;
        }

        $roomStmt = $pdo->prepare('SELECT * FROM battle_rooms WHERE room_id = ?');
        $roomStmt->execute([$roomId]);
        $room = $roomStmt->fetch();

        if (!$room) {
            http_response_code(404);
            echo json_encode(['error' => 'Room not found']);
            exit;
        }

        $isPlayer1 = ($room['player1_id'] === $userId);

        if ($isPlayer1) {
            $up = $pdo->prepare('UPDATE battle_rooms SET player1_score = ?, player1_strikes = ?, updated_at = NOW() WHERE room_id = ?');
            $up->execute([$score, $strikes, $roomId]);
            $opponentScore = (int) $room['player2_score'];
            $opponentStrikes = (int) $room['player2_strikes'];
        } else {
            $up = $pdo->prepare('UPDATE battle_rooms SET player2_score = ?, player2_strikes = ?, updated_at = NOW() WHERE room_id = ?');
            $up->execute([$score, $strikes, $roomId]);
            $opponentScore = (int) $room['player1_score'];
            $opponentStrikes = (int) $room['player1_strikes'];
        }

        echo json_encode([
            'opponent_score'   => $opponentScore,
            'opponent_strikes' => $opponentStrikes,
            'room_status'      => $room['status'],
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION 5: START SOLO / BOT MATCH
    // -------------------------------------------------------------
    if ($action === 'start') {
        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userRating = (int) ($stmt->fetchColumn() ?: 500);

        $botNames = ['Grandmaster Banana', 'Puzzle Striker', 'Ape Rival', 'Monkey Master', 'Blitz Kong'];
        $chosenName = $botNames[array_rand($botNames)];
        $botRating = max(100, $userRating + rand(-35, 45));

        // Clean any queue records
        $del = $pdo->prepare('DELETE FROM matchmaking_queue WHERE user_id = ?');
        $del->execute([$userId]);

        echo json_encode([
            'status'          => 'started',
            'duration'        => 180,
            'max_strikes'     => 3,
            'opponent_name'   => $chosenName,
            'opponent_rating' => $botRating,
            'user_rating'     => $userRating,
            'is_bot'          => true,
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // ACTION 6: FINISH MATCH & UPDATE ELO
    // -------------------------------------------------------------
    if ($action === 'finish') {
        $playerScore = isset($data['player_score']) ? max(0, (int) $data['player_score']) : 0;
        $opponentScore = isset($data['opponent_score']) ? max(0, (int) $data['opponent_score']) : 0;
        $playerStrikes = isset($data['player_strikes']) ? max(0, (int) $data['player_strikes']) : 0;
        $opponentStrikes = isset($data['opponent_strikes']) ? max(0, (int) $data['opponent_strikes']) : 0;
        $roomId = $data['room_id'] ?? null;

        $outcome = 'draw';
        $winnerId = null;
        $ratingDelta = 0;

        // Competitive Match Resolution:
        // 1. If player struck out (3 strikes) and opponent didn't: definite loss
        if ($playerStrikes >= 3 && $opponentStrikes < 3) {
            $outcome = 'loss';
        }
        // 2. If player has more solved points: WIN
        elseif ($playerScore > $opponentScore) {
            $outcome = 'win';
            $winnerId = $userId;
        }
        // 3. If player has fewer solved points: LOSS (can never win with fewer points)
        elseif ($playerScore < $opponentScore) {
            $outcome = 'loss';
        }
        // 4. If tied score:
        else {
            if ($playerStrikes < $opponentStrikes) {
                $outcome = 'win';
                $winnerId = $userId;
            } elseif ($playerStrikes > $opponentStrikes) {
                $outcome = 'loss';
            } else {
                $outcome = 'draw';
            }
        }

        if ($outcome === 'win') {
            $ratingDelta = +25;
        } elseif ($outcome === 'loss') {
            $ratingDelta = -15;
        } else {
            $ratingDelta = 0;
        }

        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $currentRating = (int) $stmt->fetchColumn();

        $newRating = max(100, $currentRating + $ratingDelta);

        $updateStmt = $pdo->prepare('UPDATE users SET battle_rating = ? WHERE id = ?');
        $updateStmt->execute([$newRating, $userId]);

        // Determine opponent user id if multiplayer
        $oppUserId = null;
        if (!empty($roomId)) {
            $rStmt = $pdo->prepare('SELECT player1_id, player2_id FROM battle_rooms WHERE room_id = ?');
            $rStmt->execute([$roomId]);
            $r = $rStmt->fetch();
            if ($r) {
                $oppUserId = ($r['player1_id'] === $userId) ? $r['player2_id'] : $r['player1_id'];
            }
        }

        $matchStmt = $pdo->prepare(
            'INSERT INTO battle_matches (player1_id, player2_id, player1_score, player2_score, winner_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $matchStmt->execute([$userId, $oppUserId, $playerScore, $opponentScore, $winnerId]);

        // Clean queue
        $del = $pdo->prepare('DELETE FROM matchmaking_queue WHERE user_id = ?');
        $del->execute([$userId]);

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
