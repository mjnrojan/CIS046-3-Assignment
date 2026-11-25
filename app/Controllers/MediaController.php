<?php

/**
 * HTTP handlers for third-party media: Freesound sounds and encouragement toasts.
 */
class MediaController
{
    /**
     * GET /api/sounds.php?category=click|solve|strike|levelup|gameover|bgm
     * Returns {"preview_url": "...", "cached": bool}.
     */
    public static function sound(): void
    {
        if (FreesoundService::apiKey() === '') {
            Response::error('Freesound API key not configured', 500);
        }

        $category = isset($_GET['category']) ? trim($_GET['category']) : '';

        if (!array_key_exists($category, FreesoundService::SOUND_MAP)) {
            Response::error('Invalid category. Use: ' . implode(', ', array_keys(FreesoundService::SOUND_MAP)), 400);
        }

        $cached = FreesoundService::cached($category);
        if ($cached !== null) {
            Response::json(['preview_url' => $cached, 'cached' => true]);
        }

        $previewUrl = FreesoundService::previewFor($category);

        if ($previewUrl === null) {
            Response::error('Could not retrieve sound from Freesound API', 502);
        }

        Response::json(['preview_url' => $previewUrl, 'cached' => false, 'category' => $category]);
    }

    /**
     * GET /api/sounds_preload.php — every effect URL in one response,
     * so the browser makes one request instead of five on page load.
     * Categories that fail are simply left out.
     */
    public static function preloadSounds(): void
    {
        $urls = [];

        foreach (FreesoundService::PRELOAD_CATEGORIES as $category) {
            $url = FreesoundService::previewFor($category);
            if ($url) {
                $urls[$category] = $url;
            }
        }

        Response::json($urls, 200, JSON_UNESCAPED_SLASHES);
    }

    /**
     * GET /api/encouragement.php[?answer=0-9]
     * Returns an affirmation plus a maths fact about the digit (random if not given).
     */
    public static function encouragement(): void
    {
        if (isset($_GET['answer']) && is_numeric($_GET['answer']) && (int) $_GET['answer'] >= 0 && (int) $_GET['answer'] <= 9) {
            $answer = (int) $_GET['answer'];
        } else {
            $answer = rand(0, 9);
        }

        Response::json([
            'affirmation' => EncouragementService::affirmation(),
            'fact'        => EncouragementService::mathFact($answer),
            'answer'      => $answer,
        ], 200, JSON_UNESCAPED_UNICODE);
    }
}
