<?php

// Entry point for /api/sounds_preload.php. All sound effect URLs in one response.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
MediaController::preloadSounds();
