<?php

// Entry point for /api/dev_watch.php. Development live-reload: newest frontend file time. No session needed.
// Pipeline: middleware checks run first, then the controller handles the request.
require_once __DIR__ . '/../app/bootstrap.php';

header('Cache-Control: no-store, no-cache, must-revalidate');
Response::json(['timestamp' => DevWatchService::latestMtime(__DIR__ . '/../frontend')]);
