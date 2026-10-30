<?php

/**
 * Database migration and reset runner.
 *
 * Drops existing tables and creates the fresh Banana Puzzle Arena schema.
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
    echo "  - progress (for milestones)\n";
    echo "  - indexes created for fast leaderboards\n";
    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
