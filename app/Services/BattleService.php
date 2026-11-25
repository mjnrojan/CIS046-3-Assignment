<?php

/**
 * 1v1 battle rating, bot matches and match results.
 *
 * Rating rule: win +25, loss -15, draw 0, never below 100.
 * This is a simplified fixed-step rating, not the full Elo formula
 * (which would scale the change by the rating difference).
 */
class BattleService
{
    public const WIN_DELTA = 25;
    public const LOSS_DELTA = -15;
    public const RATING_FLOOR = 100;

    private const BOT_NAMES = ['Grandmaster Banana', 'Puzzle Striker', 'Ape Rival', 'Monkey Master', 'Blitz Kong'];

    /**
     * Returns username, rating and last 5 matches, or null if the user is missing.
     */
    public static function summary(int $userId): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT id, username, battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $historyStmt = $pdo->prepare(
            'SELECT id, player1_score, player2_score, winner_id, played_at
             FROM battle_matches
             WHERE player1_id = ? OR player2_id = ?
             ORDER BY played_at DESC
             LIMIT 5'
        );
        $historyStmt->execute([$userId, $userId]);

        return [
            'username'       => $user['username'],
            'battle_rating'  => (int) $user['battle_rating'],
            'recent_matches' => $historyStmt->fetchAll(),
        ];
    }

    /**
     * Sets up a solo match against a named bot rated close to the player.
     */
    public static function startBotMatch(int $userId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $userRating = (int) ($stmt->fetchColumn() ?: 500);

        $botName = self::BOT_NAMES[array_rand(self::BOT_NAMES)];
        $botRating = max(self::RATING_FLOOR, $userRating + rand(-35, 45));

        MatchmakingService::leave($userId);

        return [
            'status'          => 'started',
            'duration'        => 180,
            'max_strikes'     => 3,
            'opponent_name'   => $botName,
            'opponent_rating' => $botRating,
            'user_rating'     => $userRating,
            'is_bot'          => true,
        ];
    }

    /**
     * Decides 'win', 'loss' or 'draw'.
     * 1. Striking out (3) while the opponent did not is a loss.
     * 2. Otherwise more solves wins and fewer solves loses.
     * 3. On equal solves, fewer strikes wins.
     */
    public static function resolveOutcome(int $playerScore, int $opponentScore, int $playerStrikes, int $opponentStrikes): string
    {
        if ($playerStrikes >= 3 && $opponentStrikes < 3) {
            return 'loss';
        }
        if ($playerScore > $opponentScore) {
            return 'win';
        }
        if ($playerScore < $opponentScore) {
            return 'loss';
        }
        if ($playerStrikes < $opponentStrikes) {
            return 'win';
        }
        if ($playerStrikes > $opponentStrikes) {
            return 'loss';
        }
        return 'draw';
    }

    /**
     * Rating change for an outcome.
     */
    public static function ratingDelta(string $outcome): int
    {
        if ($outcome === 'win') {
            return self::WIN_DELTA;
        }
        if ($outcome === 'loss') {
            return self::LOSS_DELTA;
        }
        return 0;
    }

    /**
     * Applies the result: updates the rating, stores the match, leaves the queue.
     * Returns the JSON payload for the client.
     */
    public static function finish(int $userId, int $playerScore, int $opponentScore, int $playerStrikes, int $opponentStrikes, ?string $roomId): array
    {
        $pdo = Database::getConnection();

        $outcome = self::resolveOutcome($playerScore, $opponentScore, $playerStrikes, $opponentStrikes);
        $winnerId = ($outcome === 'win') ? $userId : null;
        $ratingDelta = self::ratingDelta($outcome);

        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $currentRating = (int) $stmt->fetchColumn();

        $newRating = max(self::RATING_FLOOR, $currentRating + $ratingDelta);

        $updateStmt = $pdo->prepare('UPDATE users SET battle_rating = ? WHERE id = ?');
        $updateStmt->execute([$newRating, $userId]);

        // Find the opponent's user id for multiplayer matches (null for bot matches).
        $oppUserId = null;
        if (!empty($roomId)) {
            $room = MatchmakingService::findRoom($roomId);
            if ($room) {
                $oppUserId = ($room['player1_id'] === $userId) ? $room['player2_id'] : $room['player1_id'];
            }
        }

        $matchStmt = $pdo->prepare(
            'INSERT INTO battle_matches (player1_id, player2_id, player1_score, player2_score, winner_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $matchStmt->execute([$userId, $oppUserId, $playerScore, $opponentScore, $winnerId]);

        MatchmakingService::leave($userId);

        return [
            'outcome'        => $outcome,
            'rating_delta'   => $ratingDelta,
            'current_rating' => $newRating,
            'player_score'   => $playerScore,
            'opponent_score' => $opponentScore,
            'winner_id'      => $winnerId,
        ];
    }
}
