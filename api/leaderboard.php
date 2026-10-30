<?php

/**
 * =====================================================================
 * BANANA PUZZLE ARENA – MULTI-LEADERBOARDS API
 * =====================================================================
 *
 * Provides competitive rank ordering across all three arena modes:
 * 1. Mode "rush": Top Solves in single sprint (rush_high_score DESC)
 * 2. Mode "battle": Competitive Elo Rating (battle_rating DESC)
 * 3. Mode "ladder": Cumulative Adventure Points (ladder_points DESC)
 *
 * Employs database indexes (idx_users_rush, idx_users_battle, idx_users_ladder)
 * for sub-millisecond query execution.
 *
 * Also computes the authenticated player's personal standing in the ranking.
 */

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/Database.php';

startSecureSession();

header('Content-Type: application/json');

/**
 * Rejects any non-GET requests.
 * Retrieving leaderboard standings is an idempotent read operation.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$pdo = Database::getConnection();
$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

// Validate requested leaderboard mode (defaults to 'rush')
$mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'rush';
$validModes = ['rush', 'battle', 'ladder'];

if (!in_array($mode, $validModes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid leaderboard mode. Must be rush, battle, or ladder']);
    exit;
}

/**
 * Builds and executes the indexed query based on the selected mode.
 */
if ($mode === 'rush') {
    // Mode 1: Puzzle Rush - Top Solves
    $stmt = $pdo->prepare(
        'SELECT id, username, rush_high_score AS score
         FROM users
         ORDER BY rush_high_score DESC, id ASC
         LIMIT 20'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $metricLabel = 'Solves';
} elseif ($mode === 'battle') {
    // Mode 2: Puzzle Battle - Elo Rating
    $stmt = $pdo->prepare(
        'SELECT id, username, battle_rating AS score
         FROM users
         ORDER BY battle_rating DESC, id ASC
         LIMIT 20'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $metricLabel = 'Elo Rating';
} else {
    // Mode 3: Classic Ladder - Total Points & Highest Node
    $stmt = $pdo->prepare(
        'SELECT id, username, ladder_points AS score, ladder_node AS node
         FROM users
         ORDER BY ladder_points DESC, ladder_node DESC, id ASC
         LIMIT 20'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $metricLabel = 'Ladder Points';
}

// Compute rank positions (1, 2, 3...) and sanitize usernames
$rankings = [];
$rankCounter = 1;
$userRankInfo = null;

foreach ($rows as $row) {
    $isSelf = ($currentUserId !== null && (int) $row['id'] === $currentUserId);

    $entry = [
        'rank'     => $rankCounter,
        'user_id'  => (int) $row['id'],
        'username' => htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'),
        'score'    => (int) $row['score'],
        'is_self'  => $isSelf,
    ];

    if ($mode === 'ladder' && isset($row['node'])) {
        $entry['node'] = (int) $row['node'];
    }

    $rankings[] = $entry;

    if ($isSelf) {
        $userRankInfo = $entry;
    }

    $rankCounter++;
}

// If authenticated user is outside the top 20, calculate their exact rank
if ($currentUserId !== null && $userRankInfo === null) {
    if ($mode === 'rush') {
        $rankQuery = $pdo->prepare('SELECT COUNT(*) + 1 FROM users WHERE rush_high_score > (SELECT rush_high_score FROM users WHERE id = ?)');
    } elseif ($mode === 'battle') {
        $rankQuery = $pdo->prepare('SELECT COUNT(*) + 1 FROM users WHERE battle_rating > (SELECT battle_rating FROM users WHERE id = ?)');
    } else {
        $rankQuery = $pdo->prepare('SELECT COUNT(*) + 1 FROM users WHERE ladder_points > (SELECT ladder_points FROM users WHERE id = ?)');
    }

    $rankQuery->execute([$currentUserId]);
    $calculatedRank = (int) $rankQuery->fetchColumn();

    $userQuery = $pdo->prepare('SELECT username, rush_high_score, battle_rating, ladder_points FROM users WHERE id = ?');
    $userQuery->execute([$currentUserId]);
    $userData = $userQuery->fetch();

    if ($userData) {
        $userRankInfo = [
            'rank'     => $calculatedRank,
            'user_id'  => $currentUserId,
            'username' => htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8'),
            'score'    => (int) ($mode === 'rush' ? $userData['rush_high_score'] : ($mode === 'battle' ? $userData['battle_rating'] : $userData['ladder_points'])),
            'is_self'  => true,
        ];
    }
}

echo json_encode([
    'mode'         => $mode,
    'metric_label' => $metricLabel,
    'total_shown'  => count($rankings),
    'rankings'     => $rankings,
    'user_standing'=> $userRankInfo,
], JSON_UNESCAPED_SLASHES);
