<?php

/**
 * HTTP handler for saving a Puzzle Rush run.
 */
class RushController
{
    /**
     * POST /api/rush.php  {"solved": n, "mode": "1m|5m|10m|survival", "strikes": 0-3}
     * Note: "solved" is counted by the browser and trusted here.
     */
    public static function save(int $userId): void
    {
        $data = Request::json();

        if ($data === null || !isset($data['solved'])) {
            Response::error('Missing score data', 400);
        }

        $solved = max(0, (int) $data['solved']);
        $mode = isset($data['mode']) && in_array($data['mode'], RushService::MODES, true) ? $data['mode'] : '5m';
        $strikes = isset($data['strikes']) ? min(3, max(0, (int) $data['strikes'])) : 3;

        try {
            $result = RushService::saveRun($userId, $mode, $solved, $strikes);
        } catch (Exception $e) {
            // Log the real error on the server only; never expose internals to the client.
            error_log('[rush.php] ' . $e->getMessage());
            Response::error('Database error saving rush score', 500);
        }

        Response::json([
            'saved'             => true,
            'solved'            => $solved,
            'mode'              => $mode,
            'is_new_high_score' => $result['is_new_high_score'],
            'rush_high_score'   => $result['rush_high_score'],
        ]);
    }
}
