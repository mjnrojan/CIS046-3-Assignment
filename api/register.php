<?php

// Entry point for /api/register.php. Creates a new account.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
MethodMiddleware::allow('POST');
AuthController::register();
