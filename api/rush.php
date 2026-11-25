<?php

// Entry point for /api/rush.php. Saves a finished Puzzle Rush run.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
$userId = AuthMiddleware::requireLogin();
MethodMiddleware::allow('POST');
RushController::save($userId);
