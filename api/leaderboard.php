<?php

// Entry point for /api/leaderboard.php. Public leaderboard for rush, battle or ladder.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
MethodMiddleware::allow('GET');
LeaderboardController::show();
