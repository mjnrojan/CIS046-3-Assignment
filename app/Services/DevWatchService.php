<?php

/**
 * Finds the newest modification time of the frontend files.
 * Used by the development live-reload poller (frontend/js/dev-reload.js).
 */
class DevWatchService
{
    private const WATCHED_EXTENSIONS = ['html', 'js', 'css'];

    /**
     * Recursively scans $dir and returns the largest mtime of any .html/.js/.css file.
     * Returns 0 when the folder is missing or unreadable.
     */
    public static function latestMtime(string $dir): int
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
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $maxMtime = max($maxMtime, self::latestMtime($path));
            } elseif (is_file($path) && in_array(pathinfo($path, PATHINFO_EXTENSION), self::WATCHED_EXTENSIONS, true)) {
                $mtime = filemtime($path);
                if ($mtime !== false && $mtime > $maxMtime) {
                    $maxMtime = $mtime;
                }
            }
        }

        return $maxMtime;
    }
}
