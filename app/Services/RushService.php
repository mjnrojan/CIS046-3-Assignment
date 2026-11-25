<?php

/**
 * Stores Puzzle Rush results and personal bests.
 */
class RushService
{
    public const MODES = ['1m', '5m', '10m', 'survival'];

    /**
     * Saves one finished run and updates the high score if it was beaten.
     * Returns whether it is a new record and the (possibly new) high score.
     */
    public static function saveRun(int $userId, string $mode, int $solved, int $strikes): array
    {
        $pdo = Database::getConnection();

        // 1. Record the run in the scores table.
        $stmt = $pdo->prepare(
            'INSERT INTO scores (user_id, mode, score, correct, total)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, 'rush_' . $mode, $solved, $solved, $solved + $strikes]);

        // 2. Read the current high score.
        $stmt = $pdo->prepare('SELECT rush_high_score FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        $currentHigh = isset($user['rush_high_score']) ? (int) $user['rush_high_score'] : 0;

        $isNewHigh = $solved > $currentHigh;
        $finalHigh = $isNewHigh ? $solved : $currentHigh;

        // 3. Update the high score only if it was beaten.
        if ($isNewHigh) {
            $stmt = $pdo->prepare('UPDATE users SET rush_high_score = ? WHERE id = ?');
            $stmt->execute([$finalHigh, $userId]);
        }

        return ['is_new_high_score' => $isNewHigh, 'rush_high_score' => $finalHigh];
    }
}
