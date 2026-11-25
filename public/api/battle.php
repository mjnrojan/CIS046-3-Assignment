<?php

// Entry point for /api/battle.php. 1v1 battle: rating/history (GET) and actions (POST).
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../../app/bootstrap.php';

SessionMiddleware::start();
$userId = AuthMiddleware::requireLogin();
MethodMiddleware::allow('GET', 'POST');

if (Request::method() === 'GET') {
    BattleController::show($userId);
} else {
    BattleController::handle($userId);
}
