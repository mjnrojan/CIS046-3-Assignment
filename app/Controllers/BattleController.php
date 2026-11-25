<?php

/**
 * HTTP handlers for 1v1 Puzzle Battle.
 * GET returns the player's rating and history; POST dispatches on "action".
 */
class BattleController
{
    /**
     * GET /api/battle.php
     */
    public static function show(int $userId): void
    {
        $summary = BattleService::summary($userId);

        if ($summary === null) {
            Response::error('User not found', 404);
        }

        Response::json($summary);
    }

    /**
     * POST /api/battle.php  {"action": "queue_join|queue_poll|queue_leave|room_sync|start|finish", ...}
     * One endpoint with an action field keeps the frontend to a single URL.
     */
    public static function handle(int $userId): void
    {
        $data = Request::json();

        if ($data === null || empty($data['action'])) {
            Response::error('Action parameter is required', 400);
        }

        switch ($data['action']) {
            case 'queue_join':
                $rating = MatchmakingService::join($userId);
                Response::json(['status' => 'searching', 'rating' => $rating]);
                break;

            case 'queue_poll':
                Response::json(MatchmakingService::poll($userId));
                break;

            case 'queue_leave':
                MatchmakingService::leave($userId);
                Response::json(['status' => 'cancelled']);
                break;

            case 'room_sync':
                self::roomSync($userId, $data);
                break;

            case 'start':
                Response::json(BattleService::startBotMatch($userId));
                break;

            case 'finish':
                Response::json(BattleService::finish(
                    $userId,
                    self::intField($data, 'player_score'),
                    self::intField($data, 'opponent_score'),
                    self::intField($data, 'player_strikes'),
                    self::intField($data, 'opponent_strikes'),
                    $data['room_id'] ?? null
                ));
                break;
        }

        Response::error('Unknown battle action', 400);
    }

    /**
     * Shares this player's live score/strikes and returns the opponent's.
     */
    private static function roomSync(int $userId, array $data): void
    {
        $roomId = $data['room_id'] ?? '';

        if (empty($roomId)) {
            Response::error('Missing room_id', 400);
        }

        $room = MatchmakingService::findRoom($roomId);

        if ($room === null) {
            Response::error('Room not found', 404);
        }

        Response::json(MatchmakingService::syncRoom(
            $room,
            $userId,
            self::intField($data, 'score'),
            self::intField($data, 'strikes')
        ));
    }

    /**
     * Reads a non-negative integer field, defaulting to 0.
     */
    private static function intField(array $data, string $field): int
    {
        return isset($data[$field]) ? max(0, (int) $data[$field]) : 0;
    }
}
