<?php

// Entry point for /api/ladder.php. Classic Ladder state (GET) and guesses (POST).
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
$userId = AuthMiddleware::requireLogin();
MethodMiddleware::allow('GET', 'POST');

if (Request::method() === 'GET') {
    LadderController::show($userId);
} else {
    LadderController::guess($userId);
}
