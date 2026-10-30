<?php

/**
 * Database migration and reset runner.
 *
 * Drops existing tables, creates the fresh Banana Puzzle Arena schema,
 * and seeds ready-to-test demonstration accounts.
 *
 * Run from terminal: php sql/migrate.php
 */

require_once __DIR__ . '/../src/Database.php';

echo "=== Banana Puzzle Arena Schema Migration ===\n";

try {
    $pdo = Database::getConnection();
    $sqlFile = __DIR__ . '/schema.sql';

    if (!file_exists($sqlFile)) {
        throw new RuntimeException("schema.sql file not found at: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);

    echo "✔ Successfully dropped old tables and recreated all schema tables:\n";
    echo "  - users (with ladder_points, ladder_node, rush_high_score, battle_rating)\n";
    echo "  - scores (with mode, score, correct, total)\n";
    echo "  - battle_matches (for 1v1 history)\n";
    echo "  - matchmaking_queue (for live Elo matchmaking)\n";
    echo "  - battle_rooms (for active 2-browser state synchronization)\n";
    echo "  - progress (for milestones)\n";
    echo "  - indexes created for fast leaderboards\n";

    // Seed two demonstration accounts for instant 2-browser multiplayer testing
    $seedPassword = password_hash('password123', PASSWORD_BCRYPT);

    $seedStmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, ladder_points, ladder_node, rush_high_score, battle_rating)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    // Account 1: Primary Demo Player (500 Elo)
    $seedStmt->execute(['pilot_player', $seedPassword, 150, 4, 18, 500]);
    // Account 2: Rival Multiplayer Opponent (515 Elo - within 200 Elo match range)
    $seedStmt->execute(['rival_player', $seedPassword, 120, 3, 16, 515]);

    echo "✔ Seeded 2 demo multiplayer accounts (password: password123):\n";
    echo "  - 'pilot_player' (500 Elo)\n";
    echo "  - 'rival_player' (515 Elo)\n";
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
