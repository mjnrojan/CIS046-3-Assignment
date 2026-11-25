<?php

/**
 * Loads a player's profile and arena stats.
 */
class ProfileService
{
    /**
     * Returns the profile row, or null when the user does not exist.
     * If the stats columns have not been migrated yet, falls back to id + username
     * and fills in default values so the app keeps working.
     */
    public static function find(int $userId): ?array
    {
        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare(
                'SELECT id, username, ladder_points, ladder_node, rush_high_score, battle_rating
                 FROM users WHERE id = ?'
            );
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            // Fallback for pre-migration databases.
            $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }

        if (!$user) {
            return null;
        }

        return [
            'id'              => (int) $user['id'],
            'username'        => $user['username'],
            'ladder_points'   => isset($user['ladder_points']) ? (int) $user['ladder_points'] : 0,
            'ladder_node'     => isset($user['ladder_node']) ? (int) $user['ladder_node'] : 1,
            'rush_high_score' => isset($user['rush_high_score']) ? (int) $user['rush_high_score'] : 0,
            'battle_rating'   => isset($user['battle_rating']) ? (int) $user['battle_rating'] : 500,
        ];
    }
}
