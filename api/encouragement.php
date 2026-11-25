<?php

// Entry point for /api/encouragement.php. Affirmation + maths fact for the toast.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

SessionMiddleware::start();
AuthMiddleware::requireLogin();
MediaController::encouragement();
