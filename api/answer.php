<?php

// Entry point for /api/answer.php. Checks a single guess against the stored solution.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
AuthMiddleware::requireLogin();
MethodMiddleware::allow('POST');
PuzzleController::answer();
