<?php

/**
 * Talks to the Banana puzzle API and keeps a session FIFO cache of puzzles.
 *
 * Interoperability: our PHP server calls Marc Conrad's Banana API over HTTPS,
 * receives JSON, and keeps the solution on the server. The browser only ever
 * sees the question image URL.
 *
 * Serving from $_SESSION['puzzle_cache'] drops response time from ~1.5s
 * (network call) to under 5ms (memory). The cache refills in small batches
 * so a single request never blocks on many network calls.
 */
class PuzzleCacheService
{
    public const MAX_CACHE_SIZE = 50;
    public const REFILL_BATCH_SIZE = 2;

    private const API_URL = 'https://marcconrad.com/uob/banana/api.php?out=json&base64=no';

    /**
     * Fetches one raw puzzle from the remote Banana API.
     * Returns ['question' => url, 'solution' => int] or null on failure.
     *
     * verify_peer is off so it works on local XAMPP installs without a CA bundle.
     * In production this must be true, otherwise TLS certificates are not checked.
     */
    public static function fetchRemote(): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = @file_get_contents(self::API_URL, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['question'], $data['solution'])) {
            return null;
        }

        return [
            'question' => (string) $data['question'],
            'solution' => (int) $data['solution'],
        ];
    }

    /**
     * Makes sure the cache array exists in the session.
     */
    private static function init(): void
    {
        if (!isset($_SESSION['puzzle_cache']) || !is_array($_SESSION['puzzle_cache'])) {
            $_SESSION['puzzle_cache'] = [];
        }
    }

    /**
     * Tops up the cache by up to $batchCount puzzles.
     * Stops early when the cache is full or the remote API fails.
     */
    public static function replenish(int $batchCount = self::REFILL_BATCH_SIZE): void
    {
        self::init();

        $currentCount = count($_SESSION['puzzle_cache']);
        if ($currentCount >= self::MAX_CACHE_SIZE) {
            return;
        }

        $needed = min($batchCount, self::MAX_CACHE_SIZE - $currentCount);

        for ($i = 0; $i < $needed; $i++) {
            $puzzle = self::fetchRemote();
            if ($puzzle === null) {
                // Stop on network failure to avoid slowing down the response.
                break;
            }
            $_SESSION['puzzle_cache'][] = $puzzle;
        }
    }

    /**
     * Removes and returns the oldest cached puzzle (FIFO).
     * If the cache is empty, fetches one immediately so the player is never stuck.
     * Returns null only when the remote API is unreachable.
     */
    public static function popNext(bool $autoReplenish = false): ?array
    {
        self::init();

        if (empty($_SESSION['puzzle_cache'])) {
            $immediate = self::fetchRemote();
            if ($immediate === null) {
                return null;
            }
            $_SESSION['puzzle_cache'][] = $immediate;
        }

        $puzzle = array_shift($_SESSION['puzzle_cache']);

        if ($autoReplenish && count($_SESSION['puzzle_cache']) < 5) {
            self::replenish(self::REFILL_BATCH_SIZE);
        }

        return $puzzle;
    }

    /**
     * Returns how many puzzles are cached and the maximum capacity.
     */
    public static function stats(): array
    {
        self::init();

        return [
            'count'    => count($_SESSION['puzzle_cache']),
            'capacity' => self::MAX_CACHE_SIZE,
        ];
    }
}
