<?php

/**
 * Live 1v1 matchmaking queue and battle room synchronisation.
 *
 * Two browsers never talk to each other directly. Each one polls the server
 * every 1.5s; the database tables matchmaking_queue and battle_rooms are the
 * shared state. Polling was chosen over WebSockets because the PHP built-in
 * server and shared hosting cannot keep long-lived socket connections open.
 */
class MatchmakingService
{
    /** Players are only paired when their ratings are this close. */
    public const MAX_RATING_GAP = 200;

    /** Queue rows not refreshed within this many seconds are ignored (ghost players). */
    public const HEARTBEAT_TTL = 10;

    /**
     * Puts the user in the queue (or resets their existing row) and returns their rating.
     */
    public static function join(int $userId): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT battle_rating FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $rating = (int) $stmt->fetchColumn();

        $upsert = $pdo->prepare(
            'INSERT INTO matchmaking_queue (user_id, rating, status, room_id, updated_at)
             VALUES (?, ?, "searching", NULL, NOW())
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), status = "searching", room_id = NULL, updated_at = NOW()'
        );
        $upsert->execute([$userId, $rating]);

        return $rating;
    }

    /**
     * Refreshes the heartbeat, then either reports an existing match,
     * claims a new opponent, or reports that the search continues.
     * Returns the JSON payload for the client.
     */
    public static function poll(int $userId): array
    {
        $pdo = Database::getConnection();

        // Refresh our heartbeat timestamp.
        $hbStmt = $pdo->prepare('UPDATE matchmaking_queue SET updated_at = NOW() WHERE user_id = ? AND status = "searching"');
        $hbStmt->execute([$userId]);

        // First, check if someone else already matched with us.
        $myQStmt = $pdo->prepare('SELECT status, room_id, rating FROM matchmaking_queue WHERE user_id = ?');
        $myQStmt->execute([$userId]);
        $myQueue = $myQStmt->fetch();

        if ($myQueue && $myQueue['status'] === 'matched' && !empty($myQueue['room_id'])) {
            $existing = self::describeExistingMatch($userId, $myQueue);
            if ($existing !== null) {
                return $existing;
            }
        }

        // Otherwise search for a live opponent within the rating gap.
        $myRating = (int) ($myQueue['rating'] ?? 500);

        $findStmt = $pdo->prepare(
            'SELECT q.user_id, q.rating, u.username
             FROM matchmaking_queue q
             JOIN users u ON q.user_id = u.id
             WHERE q.user_id != ?
               AND q.status = "searching"
               AND ABS(q.rating - ?) <= ' . self::MAX_RATING_GAP . '
               AND q.updated_at >= NOW() - INTERVAL ' . self::HEARTBEAT_TTL . ' SECOND
             ORDER BY ABS(q.rating - ?) ASC
             LIMIT 1'
        );
        $findStmt->execute([$userId, $myRating, $myRating]);
        $candidate = $findStmt->fetch();

        if ($candidate) {
            $claimed = self::claimOpponent($userId, $myRating, $candidate);
            if ($claimed !== null) {
                return $claimed;
            }
        }

        return ['status' => 'searching'];
    }

    /**
     * Builds the "matched" payload when another player already paired with us.
     * Returns null if the room row is missing.
     */
    private static function describeExistingMatch(int $userId, array $myQueue): ?array
    {
        $pdo = Database::getConnection();
        $roomId = $myQueue['room_id'];

        $roomStmt = $pdo->prepare('SELECT * FROM battle_rooms WHERE room_id = ?');
        $roomStmt->execute([$roomId]);
        $room = $roomStmt->fetch();

        if (!$room) {
            return null;
        }

        $opponentId = ($room['player1_id'] === $userId) ? $room['player2_id'] : $room['player1_id'];
        $oppStmt = $pdo->prepare('SELECT username, battle_rating FROM users WHERE id = ?');
        $oppStmt->execute([$opponentId]);
        $opponent = $oppStmt->fetch();

        return [
            'status'          => 'matched',
            'room_id'         => $roomId,
            'role'            => ($room['player1_id'] === $userId) ? 'player1' : 'player2',
            'opponent_name'   => $opponent['username'],
            'opponent_rating' => (int) $opponent['battle_rating'],
            'user_rating'     => (int) $myQueue['rating'],
        ];
    }

    /**
     * Tries to claim the candidate. The UPDATE only succeeds while the candidate
     * is still "searching", so two players cannot both claim the same opponent.
     * On success creates the room and returns the "matched" payload, else null.
     */
    private static function claimOpponent(int $userId, int $myRating, array $candidate): ?array
    {
        $pdo = Database::getConnection();
        $candidateId = (int) $candidate['user_id'];
        $roomId = 'room_' . bin2hex(random_bytes(8));

        $claimStmt = $pdo->prepare(
            'UPDATE matchmaking_queue
             SET status = "matched", room_id = ?
             WHERE user_id = ? AND status = "searching"'
        );
        $claimStmt->execute([$roomId, $candidateId]);

        if ($claimStmt->rowCount() !== 1) {
            return null;
        }

        $selfClaim = $pdo->prepare('UPDATE matchmaking_queue SET status = "matched", room_id = ? WHERE user_id = ?');
        $selfClaim->execute([$roomId, $userId]);

        $roomCreate = $pdo->prepare(
            'INSERT INTO battle_rooms (room_id, player1_id, player2_id, status)
             VALUES (?, ?, ?, "active")'
        );
        $roomCreate->execute([$roomId, $userId, $candidateId]);

        return [
            'status'          => 'matched',
            'room_id'         => $roomId,
            'role'            => 'player1',
            'opponent_name'   => $candidate['username'],
            'opponent_rating' => (int) $candidate['rating'],
            'user_rating'     => $myRating,
        ];
    }

    /**
     * Removes the user from the queue.
     */
    public static function leave(int $userId): void
    {
        $del = Database::getConnection()->prepare('DELETE FROM matchmaking_queue WHERE user_id = ?');
        $del->execute([$userId]);
    }

    /**
     * Returns the battle room row, or null if it does not exist.
     */
    public static function findRoom(string $roomId): ?array
    {
        $stmt = Database::getConnection()->prepare('SELECT * FROM battle_rooms WHERE room_id = ?');
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();

        return $room ?: null;
    }

    /**
     * Writes this player's live score and strikes into the room and
     * returns the opponent's latest score, strikes and the room status.
     */
    public static function syncRoom(array $room, int $userId, int $score, int $strikes): array
    {
        $pdo = Database::getConnection();
        $isPlayer1 = ($room['player1_id'] === $userId);

        if ($isPlayer1) {
            $up = $pdo->prepare('UPDATE battle_rooms SET player1_score = ?, player1_strikes = ?, updated_at = NOW() WHERE room_id = ?');
            $opponentScore = (int) $room['player2_score'];
            $opponentStrikes = (int) $room['player2_strikes'];
        } else {
            $up = $pdo->prepare('UPDATE battle_rooms SET player2_score = ?, player2_strikes = ?, updated_at = NOW() WHERE room_id = ?');
            $opponentScore = (int) $room['player1_score'];
            $opponentStrikes = (int) $room['player1_strikes'];
        }
        $up->execute([$score, $strikes, $room['room_id']]);

        return [
            'opponent_score'   => $opponentScore,
            'opponent_strikes' => $opponentStrikes,
            'room_status'      => $room['status'],
        ];
    }
}
