<?php

/**
 * Development live-reload watcher endpoint.
 *
 * Scans frontend project files (HTML, JS, CSS) and returns the most recent
 * file modification timestamp (mtime) as JSON.
 *
 * The browser polls this endpoint every 1-2 seconds during local development.
 * When a developer saves a file, the returned timestamp increases, signaling
 * the browser to reload automatically without manual Ctrl+F5 refreshes.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

/**
 * Recursively scans a directory and finds the maximum file modification time.
 * Only checks web asset files (.html, .js, .css) to keep execution fast (< 2ms).
 */
function getLatestFrontendMtime(string $dir): int
{
    $maxMtime = 0;

    if (!is_dir($dir)) {
        return $maxMtime;
    }

    $items = scandir($dir);
    if ($items === false) {
        return $maxMtime;
    }

    foreach ($items as $item) {
        // Skip current and parent directory pointers
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_dir($path)) {
            $subMtime = getLatestFrontendMtime($path);
            if ($subMtime > $maxMtime) {
                $maxMtime = $subMtime;
            }
        } elseif (is_file($path)) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array($extension, ['html', 'js', 'css'], true)) {
                $mtime = filemtime($path);
                if ($mtime !== false && $mtime > $maxMtime) {
                    $maxMtime = $mtime;
                }
            }
        }
    }

    return $maxMtime;
}

$frontendDir = __DIR__ . '/../frontend';
$latestTimestamp = getLatestFrontendMtime($frontendDir);

echo json_encode([
    'timestamp' => $latestTimestamp,
]);
