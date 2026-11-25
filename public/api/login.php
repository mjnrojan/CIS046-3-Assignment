<?php

// Entry point for /api/login.php. Logs a user in.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../../app/bootstrap.php';

SessionMiddleware::start();
MethodMiddleware::allow('POST');
AuthController::login();
