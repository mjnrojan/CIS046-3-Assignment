<?php

// Entry point for /api/puzzle.php. Serves the next Banana puzzle (question only).
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../../app/bootstrap.php';

SessionMiddleware::start();
AuthMiddleware::requireLogin();
PuzzleController::next();
