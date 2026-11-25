<?php

/**
 * Classic Ladder rules and progress storage.
 *
 * Scoring is a small state machine driven by guess events:
 * a first-try solve is worth 50 points, each wrong guess chips 15 off,
 * and the reward never drops below 10.
 */
class LadderService
{
    private const ADD_COLUMNS_SQL =
        'ALTER TABLE users ADD COLUMN ladder_points INT NOT NULL DEFAULT 0, ADD COLUMN ladder_node INT NOT NULL DEFAULT 1';

    /**
     * Points available after $attempts wrong guesses: 50, 35, 20, then 10 forever.
     */
    public static function potentialPoints(int $attempts): int
    {
        return max(10, 50 - ($attempts * 15));
    }

    /**
     * Returns ['ladder_node' => int, 'ladder_points' => int] for the user.
     * If the columns are missing on an older database, adds them and retries.
     */
    public static function getUser(int $userId): array
    {
        $pdo = Database::getConnection();

        try {
            $stmt = $pdo->prepare('SELECT ladder_node, ladder_points FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                return [
                    'ladder_node'   => isset($user['ladder_node']) ? (int) $user['ladder_node'] : 1,
                    'ladder_points' => isset($user['ladder_points']) ? (int) $user['ladder_points'] : 0,
                ];
            }
        } catch (PDOException $e) {
            try {
                $pdo->exec(self::ADD_COLUMNS_SQL);
                $stmt = $pdo->prepare('SELECT ladder_node, ladder_points FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                if ($user) {
                    return [
                        'ladder_node'   => (int) $user['ladder_node'],
                        'ladder_points' => (int) $user['ladder_points'],
                    ];
                }
            } catch (Exception $ex) {
                // Fall through to the default below.
            }
        }

        return ['ladder_node' => 1, 'ladder_points' => 0];
    }

    /**
     * Adds the earned points, moves the user to the next node,
     * and returns the updated ladder state.
     */
    public static function recordSolve(int $userId, int $pointsEarned): array
    {
        $pdo = Database::getConnection();
        $sql = 'UPDATE users
                SET ladder_points = ladder_points + ?,
                    ladder_node   = ladder_node + 1
                WHERE id = ?';

        try {
            $pdo->prepare($sql)->execute([$pointsEarned, $userId]);
        } catch (PDOException $e) {
            try {
                $pdo->exec(self::ADD_COLUMNS_SQL);
                $pdo->prepare($sql)->execute([$pointsEarned, $userId]);
            } catch (Exception $ex) {
                // Leave progress unchanged if the database cannot be updated.
            }
        }

        return self::getUser($userId);
    }
}
