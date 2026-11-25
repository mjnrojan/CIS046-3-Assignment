<?php

/**
 * Loads every backend class in dependency order.
 *
 * Why a plain require list instead of Composer autoloading: the project has
 * no third-party packages, and an explicit list is easy to read and explain.
 * The trade-off is that a new class must be added here by hand.
 *
 * Layers:
 *   Core        – shared plumbing (env, database, request, response)
 *   Middleware  – checks that run before a controller (session, login, HTTP method)
 *   Services    – business rules, database queries and third-party API calls
 *   Controllers – read the request, call services, send the JSON response
 */

$appDir = __DIR__;

require_once $appDir . '/Core/Env.php';
require_once $appDir . '/Core/Database.php';
require_once $appDir . '/Core/Request.php';
require_once $appDir . '/Core/Response.php';

require_once $appDir . '/Middleware/SessionMiddleware.php';
require_once $appDir . '/Middleware/AuthMiddleware.php';
require_once $appDir . '/Middleware/MethodMiddleware.php';

require_once $appDir . '/Services/PuzzleCacheService.php';
require_once $appDir . '/Services/FreesoundService.php';
require_once $appDir . '/Services/EncouragementService.php';
require_once $appDir . '/Services/AuthService.php';
require_once $appDir . '/Services/ProfileService.php';
require_once $appDir . '/Services/LadderService.php';
require_once $appDir . '/Services/RushService.php';
require_once $appDir . '/Services/MatchmakingService.php';
require_once $appDir . '/Services/BattleService.php';
require_once $appDir . '/Services/LeaderboardService.php';
require_once $appDir . '/Services/DevWatchService.php';

require_once $appDir . '/Controllers/AuthController.php';
require_once $appDir . '/Controllers/PuzzleController.php';
require_once $appDir . '/Controllers/LadderController.php';
require_once $appDir . '/Controllers/RushController.php';
require_once $appDir . '/Controllers/BattleController.php';
require_once $appDir . '/Controllers/LeaderboardController.php';
require_once $appDir . '/Controllers/MediaController.php';
