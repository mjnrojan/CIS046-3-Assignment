<?php

/**
 * Builds the rush, battle and ladder leaderboards.
 * Each mode orders by an indexed column (idx_users_rush / _battle / _ladder).
 */
class LeaderboardService
{
    /** Mode => [score column, label shown in the table header]. */
    public const MODES = [
        'rush'   => ['column' => 'rush_high_score', 'label' => 'Solves'],
        'battle' => ['column' => 'battle_rating',   'label' => 'Elo Rating'],
        'ladder' => ['column' => 'ladder_points',   'label' => 'Ladder Points'],
    ];

    /**
     * Returns the top 20 rows for a mode, each with id, username and score
     * (plus node for the ladder).
     *
     * The column names come from the fixed MODES list above, never from user
     * input, so building the ORDER BY this way cannot be used for SQL injection.
     */
    public static function top(string $mode): array
    {
        $pdo = Database::getConnection();

        if ($mode === 'rush') {
            $sql = 'SELECT id, username, rush_high_score AS score
                    FROM users
                    ORDER BY rush_high_score DESC, id ASC
                    LIMIT 20';
        } elseif ($mode === 'battle') {
            $sql = 'SELECT id, username, battle_rating AS score
                    FROM users
                    ORDER BY battle_rating DESC, id ASC
                    LIMIT 20';
        } else {
            $sql = 'SELECT id, username, ladder_points AS score, ladder_node AS node
                    FROM users
                    ORDER BY ladder_points DESC, ladder_node DESC, id ASC
                    LIMIT 20';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Turns raw rows into ranked entries with escaped usernames.
     * Returns [rankings, the current user's entry or null].
     */
    public static function rank(array $rows, string $mode, ?int $currentUserId): array
    {
        $rankings = [];
        $self = null;
        $rank = 1;

        foreach ($rows as $row) {
            $isSelf = ($currentUserId !== null && (int) $row['id'] === $currentUserId);

            $entry = [
                'rank'     => $rank,
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
                $self = $entry;
            }

            $rank++;
        }

        return [$rankings, $self];
    }

    /**
     * Works out the exact rank of a user who is outside the top 20.
     * Returns null if the user no longer exists.
     */
    public static function standingOutsideTop(string $mode, int $userId): ?array
    {
        $pdo = Database::getConnection();
        $column = self::MODES[$mode]['column'];

        $rankQuery = $pdo->prepare(
            "SELECT COUNT(*) + 1 FROM users WHERE {$column} > (SELECT {$column} FROM users WHERE id = ?)"
        );
        $rankQuery->execute([$userId]);
        $calculatedRank = (int) $rankQuery->fetchColumn();

        $userQuery = $pdo->prepare('SELECT username, rush_high_score, battle_rating, ladder_points FROM users WHERE id = ?');
        $userQuery->execute([$userId]);
        $userData = $userQuery->fetch();

        if (!$userData) {
            return null;
        }

        return [
            'rank'     => $calculatedRank,
            'user_id'  => $userId,
            'username' => htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8'),
            'score'    => (int) $userData[$column],
            'is_self'  => true,
        ];
    }
}
