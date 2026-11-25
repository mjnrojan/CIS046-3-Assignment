<?php

/**
 * HTTP handlers for fetching a puzzle, answering it and warming the cache.
 *
 * Anti-cheat: the solution is stored only in $_SESSION['solution'].
 * The browser receives the question image URL and never the answer.
 */
class PuzzleController
{
    /**
     * GET /api/puzzle.php — returns {"question": imageUrl}.
     */
    public static function next(): void
    {
        $puzzle = PuzzleCacheService::popNext();

        if ($puzzle === null) {
            Response::error('Failed to retrieve puzzle from cache or remote service', 502);
        }

        $_SESSION['solution']  = (int) $puzzle['solution'];
        $_SESSION['issued_at'] = time();

        Response::json(['question' => $puzzle['question']], 200, JSON_UNESCAPED_SLASHES);
    }

    /**
     * POST /api/answer.php  {"guess": 0-9}
     * The stored solution is deleted after one guess, so it cannot be replayed.
     */
    public static function answer(): void
    {
        $guess = self::readGuess(Request::json());

        if ($guess === null) {
            Response::error('Guess must be an integer from 0 to 9', 400);
        }

        if (!isset($_SESSION['solution'])) {
            Response::error('No puzzle to answer', 400);
        }

        $expected = (int) $_SESSION['solution'];
        unset($_SESSION['solution'], $_SESSION['issued_at']);

        Response::json(['correct' => $guess === $expected]);
    }

    /**
     * Accepts an integer 0-9 or a one-character digit string.
     * Returns null when the value is missing or out of range.
     */
    private static function readGuess(?array $data): ?int
    {
        if ($data === null || !array_key_exists('guess', $data)) {
            return null;
        }

        $guess = $data['guess'];

        if (is_int($guess) && $guess >= 0 && $guess <= 9) {
            return $guess;
        }

        if (is_string($guess) && preg_match('/^[0-9]$/', $guess) === 1) {
            return (int) $guess;
        }

        return null;
    }

    /**
     * GET /api/warmup.php — called by the arena hub so the first puzzle of any
     * mode loads from memory. Uses a bigger batch (5) than the in-game refill (2)
     * because the player is still on the menu screen.
     */
    public static function warmup(): void
    {
        PuzzleCacheService::replenish(5);

        $stats = PuzzleCacheService::stats();

        Response::json([
            'puzzles_cached' => $stats['count'],
            'capacity'       => $stats['capacity'],
            // Note: no code sets 'sound_cache', so this is always false (kept as-is).
            'sounds_ready'   => isset($_SESSION['sound_cache']),
        ]);
    }
}
