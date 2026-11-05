<?php

/**
 * =====================================================================
 * BANANA PUZZLE ARENA – BATCH SOUND PRELOAD ENDPOINT
 * =====================================================================
 *
 * Returns all sound category URLs in a single JSON response.
 * Called once on page load so the client can preload every audio file
 * into browser memory before the player makes their first move.
 *
 * Uses PHP $_SESSION to cache Freesound URLs so the external API is
 * only hit the very first time — every subsequent page load is instant.
 *
 * Response: { "click": "https://cdn.freesound.org/...", "solve": "...", ... }
 */

require_once __DIR__ . '/../src/session.php';
startSecureSession();

header('Content-Type: application/json');

// Load Freesound API key from .env
$envPath = __DIR__ . '/../.env';
$apiKey  = '';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), 'FREESOUND_API_KEY=')) {
            $apiKey = trim(substr($line, strpos($line, '=') + 1));
        }
    }
}

// Sound categories and their Freesound search terms
$SOUND_MAP = [
    'click'    => ['query' => '8bit button click',       'max_duration' => 1],
    'solve'    => ['query' => 'success ding correct',    'max_duration' => 3],
    'strike'   => ['query' => 'error wrong buzz',        'max_duration' => 2],
    'levelup'  => ['query' => 'level up success jingle', 'max_duration' => 5],
    'gameover' => ['query' => 'game over retro 8bit',    'max_duration' => 5],
];

/**
 * Fetches one sound preview URL from Freesound for a given query.
 * Returns the low-quality MP3 preview URL string, or null on failure.
 */
function fetchOneSound(string $query, int $maxDuration, string $apiKey): ?string
{
    $params = http_build_query([
        'query'     => $query,
        'filter'    => 'duration:[0 TO ' . $maxDuration . ']',
        'fields'    => 'id,name,previews',
        'page_size' => 5,
        'token'     => $apiKey,
    ]);

    $context = stream_context_create([
        'http' => ['method' => 'GET', 'timeout' => 6, 'ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $response = @file_get_contents(
        'https://freesound.org/apiv2/search/text/?' . $params,
        false,
        $context
    );
    if (!$response) return null;

    $data = json_decode($response, true);
    if (empty($data['results'])) return null;

    foreach ($data['results'] as $sound) {
        if (!empty($sound['previews']['preview-lq-mp3'])) {
            return $sound['previews']['preview-lq-mp3'];
        }
    }
    return null;
}

// Build the response — serve from session cache wherever possible
$urls = [];
foreach ($SOUND_MAP as $category => $config) {
    $sessionKey = 'freesound_' . $category;

    if (!empty($_SESSION[$sessionKey])) {
        // Session cache hit — no Freesound API call needed
        $urls[$category] = $_SESSION[$sessionKey];
    } else {
        // Session cache miss — call Freesound and cache the result
        $url = fetchOneSound($config['query'], $config['max_duration'], $apiKey);
        if ($url) {
            $_SESSION[$sessionKey] = $url;
            $urls[$category] = $url;
        }
    }
}

echo json_encode($urls, JSON_UNESCAPED_SLASHES);

