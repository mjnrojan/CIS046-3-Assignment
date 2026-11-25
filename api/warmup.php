<?php

// Entry point for /api/warmup.php. Pre-fills the puzzle cache from the arena hub.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
AuthMiddleware::requireLogin();
PuzzleController::warmup();
