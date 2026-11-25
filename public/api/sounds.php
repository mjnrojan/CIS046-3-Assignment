<?php

// Entry point for /api/sounds.php. Freesound proxy for one sound category.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../../app/bootstrap.php';

SessionMiddleware::start();
MediaController::sound();
