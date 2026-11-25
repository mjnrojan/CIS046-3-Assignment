<?php

/**
 * =====================================================================
 * BANANA PUZZLE ARENA – FREESOUND API SOUND PROXY
 * =====================================================================
 *
 * Acts as a secure server-side proxy to the Freesound REST API.
 * The browser never calls Freesound directly (avoids CORS + hides key).
 *
 * Query: GET /api/sounds.php?category=click|solve|strike|levelup|gameover
 * Returns: JSON { "preview_url": "https://..." }
 *
 * Results are cached in $_SESSION to avoid repeated API calls
 * during a single gameplay session.
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

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'Freesound API key not configured']);
    exit;
}

// Map game events to Freesound search terms and tag filters
// Chosen for short, clear 8-bit / retro / indie game sounds
$SOUND_MAP = [
    'click'    => ['query' => '8bit button click', 'max_duration' => 1],
    'solve'    => ['query' => 'success ding correct', 'max_duration' => 3],
    'strike'   => ['query' => 'error wrong buzz', 'max_duration' => 2],
    'levelup'  => ['query' => 'level up success jingle', 'max_duration' => 5],
    'gameover' => ['query' => 'game over retro 8bit', 'max_duration' => 5],
    'bgm'      => ['query' => 'chiptune puzzle background loop', 'max_duration' => 60],
];

// Validate the requested category
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

if (!array_key_exists($category, $SOUND_MAP)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid category. Use: ' . implode(', ', array_keys($SOUND_MAP))]);
    exit;
}

// Serve from session cache if already fetched this session
$cacheKey = 'freesound_' . $category;
if (!empty($_SESSION[$cacheKey])) {
    echo json_encode(['preview_url' => $_SESSION[$cacheKey], 'cached' => true]);
    exit;
}

/**
 * Fetches a matching sound preview URL from the Freesound REST API.
 * Returns the low-quality MP3 preview URL (no OAuth required).
 */
function fetchFreesoundPreview(string $query, int $maxDuration, string $apiKey): ?string
{
    $params = http_build_query([
        'query'     => $query,
        'filter'    => 'duration:[0 TO ' . $maxDuration . ']',
        'fields'    => 'id,name,previews',
        'page_size' => 5,
        'token'     => $apiKey,
    ]);

    $url = 'https://freesound.org/apiv2/search/text/?' . $params;

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

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (empty($data['results'])) {
        return null;
    }

    // Pick the first result that has a low-quality preview MP3
    foreach ($data['results'] as $sound) {
        if (!empty($sound['previews']['preview-lq-mp3'])) {
            return $sound['previews']['preview-lq-mp3'];
        }
    }

    return null;
}

// Fetch from Freesound API
$config     = $SOUND_MAP[$category];
$previewUrl = fetchFreesoundPreview($config['query'], $config['max_duration'], $apiKey);

if ($previewUrl === null) {
    http_response_code(502);
    echo json_encode(['error' => 'Could not retrieve sound from Freesound API']);
    exit;
}

// Cache in session so the same category is instant on next request
$_SESSION[$cacheKey] = $previewUrl;

echo json_encode([
    'preview_url' => $previewUrl,
    'cached'      => false,
    'category'    => $category,
]);

