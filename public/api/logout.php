<?php

// Entry point for /api/logout.php. Logs the current user out.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../../app/bootstrap.php';

SessionMiddleware::start();
MethodMiddleware::allow('POST');
AuthController::logout();
