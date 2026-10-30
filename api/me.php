<?php

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/Database.php';

startSecureSession();
$userId = requireLogin();

header('Content-Type: application/json');

/**
 * Returns current authenticated player profile and arena stats.
 *
 * Checks database for rating, rush high score, and ladder progress.
 * If columns have not been migrated yet in MySQL, gracefully falls back
 * to default baseline values so the application remains robust.
 */
try {
    $pdo = Database::getConnection();

    try {
        $stmt = $pdo->prepare(
            'SELECT id, username, ladder_points, ladder_node, rush_high_score, battle_rating 
             FROM users WHERE id = ?'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        // Fallback for pre-migration databases
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
    }

    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User account not found']);
        exit;
    }

    echo json_encode([
        'id'              => (int) $user['id'],
        'username'        => $user['username'],
        'ladder_points'   => isset($user['ladder_points']) ? (int) $user['ladder_points'] : 0,
        'ladder_node'     => isset($user['ladder_node']) ? (int) $user['ladder_node'] : 1,
        'rush_high_score' => isset($user['rush_high_score']) ? (int) $user['rush_high_score'] : 0,
        'battle_rating'   => isset($user['battle_rating']) ? (int) $user['battle_rating'] : 500,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error loading profile: ' . $e->getMessage()]);
}
