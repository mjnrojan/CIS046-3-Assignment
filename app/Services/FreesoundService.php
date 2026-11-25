<?php

/**
 * Looks up short game sound effects on the Freesound REST API.
 *
 * Interoperability: the browser never calls Freesound directly. Our server
 * adds the secret API key (from .env) and returns only the public MP3 preview
 * URL. This hides the key and avoids CORS problems.
 * The key is sent as a "token" query parameter (Freesound token auth, not OAuth).
 */
class FreesoundService
{
    private const SEARCH_URL = 'https://freesound.org/apiv2/search/text/?';

    /** Game event => Freesound search query and maximum length in seconds. */
    public const SOUND_MAP = [
        'click'    => ['query' => '8bit button click',               'max_duration' => 1],
        'solve'    => ['query' => 'success ding correct',            'max_duration' => 3],
        'strike'   => ['query' => 'error wrong buzz',                'max_duration' => 2],
        'levelup'  => ['query' => 'level up success jingle',         'max_duration' => 5],
        'gameover' => ['query' => 'game over retro 8bit',            'max_duration' => 5],
        'bgm'      => ['query' => 'chiptune puzzle background loop', 'max_duration' => 60],
    ];

    /** Categories sent by the batch preload (background music is loaded on demand). */
    public const PRELOAD_CATEGORIES = ['click', 'solve', 'strike', 'levelup', 'gameover'];

    /**
     * Returns the Freesound API key from .env ('' if not configured).
     */
    public static function apiKey(): string
    {
        return Env::get('FREESOUND_API_KEY');
    }

    /**
     * Returns the cached preview URL for a category, or null if not cached yet.
     */
    public static function cached(string $category): ?string
    {
        $key = 'freesound_' . $category;

        return !empty($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    /**
     * Returns a preview URL for a category: from the session cache if possible,
     * otherwise from the Freesound API (and then caches it). Null on failure.
     */
    public static function previewFor(string $category): ?string
    {
        $cached = self::cached($category);
        if ($cached !== null) {
            return $cached;
        }

        $config = self::SOUND_MAP[$category];
        $url = self::search($config['query'], $config['max_duration'], self::apiKey());

        if ($url !== null) {
            $_SESSION['freesound_' . $category] = $url;
        }

        return $url;
    }

    /**
     * Calls the Freesound text search and returns the first low-quality MP3 preview URL.
     * verify_peer is off for local development only; it must be true in production.
     */
    public static function search(string $query, int $maxDuration, string $apiKey): ?string
    {
        $params = http_build_query([
            'query'     => $query,
            'filter'    => 'duration:[0 TO ' . $maxDuration . ']',
            'fields'    => 'id,name,previews',
            'page_size' => 5,
            'token'     => $apiKey,
        ]);

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 6,
                'ignore_errors' => true,
                'header'        => 'Accept: application/json',
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = @file_get_contents(self::SEARCH_URL . $params, false, $context);
        if (!$response) {
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['results'])) {
            return null;
        }

        foreach ($data['results'] as $sound) {
            if (!empty($sound['previews']['preview-lq-mp3'])) {
                return $sound['previews']['preview-lq-mp3'];
            }
        }

        return null;
    }
}
