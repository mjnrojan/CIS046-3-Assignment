<?php

// Entry point for /api/me.php. Returns the logged-in player's profile.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../../app/bootstrap.php';

SessionMiddleware::start();
$userId = AuthMiddleware::requireLogin();
AuthController::me($userId);
