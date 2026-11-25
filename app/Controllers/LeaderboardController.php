<?php

/**
 * HTTP handler for the multi-mode leaderboard.
 * Works for guests too; logged-in players also get their own standing.
 */
class LeaderboardController
{
    /**
     * GET /api/leaderboard.php?mode=rush|battle|ladder (default rush)
     */
    public static function show(): void
    {
        $mode = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : 'rush';

        if (!array_key_exists($mode, LeaderboardService::MODES)) {
            Response::error('Invalid leaderboard mode. Must be rush, battle, or ladder', 400);
        }

        $currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $rows = LeaderboardService::top($mode);
        [$rankings, $standing] = LeaderboardService::rank($rows, $mode, $currentUserId);

        // Players outside the top 20 still see their exact rank.
        if ($currentUserId !== null && $standing === null) {
            $standing = LeaderboardService::standingOutsideTop($mode, $currentUserId);
        }

        Response::json([
            'mode'          => $mode,
            'metric_label'  => LeaderboardService::MODES[$mode]['label'],
            'total_shown'   => count($rankings),
            'rankings'      => $rankings,
            'user_standing' => $standing,
        ], 200, JSON_UNESCAPED_SLASHES);
    }
}
