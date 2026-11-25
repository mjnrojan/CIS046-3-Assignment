<?php

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/puzzle_cache.php';

startSecureSession();
requireLogin();

header('Content-Type: application/json');

/**
 * Arena warm-up endpoint.
 *
 * Called silently by arena.html on page load so puzzles and sounds
 * are pre-fetched into the PHP session before the player enters any
 * game mode. This means the first puzzle in Classic / Rush / Battle
 * loads instantly from memory instead of blocking on the remote API.
 *
 * We use a larger initial batch (5) here vs the in-game refill (2)
 * because the player is still on the hub screen — network calls
 * happen in the background and do not block anything visible.
 */

// Fill the puzzle session cache with up to 5 puzzles
replenishPuzzleCache(5);

// Also prime the sound URL cache by including the sounds preload logic.
// We do not echo sound URLs here — just ensure the session key exists
// so the first sounds_preload.php call returns instantly.
// (sounds_preload.php already caches its result in $_SESSION['sound_cache'])
$soundsCached = isset($_SESSION['sound_cache']);

$stats = getPuzzleCacheStats();

echo json_encode([
    'puzzles_cached' => $stats['count'],
    'capacity'       => $stats['capacity'],
    'sounds_ready'   => $soundsCached,
]);

