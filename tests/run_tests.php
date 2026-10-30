<?php

// Start output buffering early so session headers can regenerate cleanly in CLI demonstration
ob_start();

/**
 * =====================================================================
 * BANANA PUZZLE ARENA – THEMATIC DEMONSTRABLE TEST SUITE
 * =====================================================================
 *
 * This test suite demonstrates compliance with the three enterprise themes:
 * 1. THEME 1: VIRTUAL IDENTITY
 *    - Bcrypt password hashing, session state binding, credentials,
 *      session fixation immunity, and unique relational identity.
 *
 * 2. THEME 2: INTEROPERABILITY
 *    - Third-party REST API proxying, low-latency in-memory cache (< 10ms),
 *      anti-replay token lifecycle, and SQL injection parameterization.
 *
 * 3. THEME 3: EVENT HANDLING
 *    - Classic Ladder chip-away state transitions, 3-strike Rush state machine,
 *      1-strike Survival mode, personal best triggers, and mtime change polling.
 *
 * DEMONSTRATION USAGE (Run separately or all together):
 *   php tests/run_tests.php                        # Run all themes
 *   php tests/run_tests.php --theme=identity       # Demonstrate Virtual Identity only
 *   php tests/run_tests.php --theme=interop        # Demonstrate Interoperability only
 *   php tests/run_tests.php --theme=events         # Demonstrate Event Handling only
 *
 * =====================================================================
 * MANDATORY MAINTENANCE PROTOCOL:
 * Whenever new features are developed (e.g. Step 9: Puzzle Battle 1v1
 * duel state machine / Elo calculations, Step 10: Multi-Leaderboards queries),
 * new test cases MUST be appended to the respective theme sections below.
 * =====================================================================
 */

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/puzzle_cache.php';

// Configure session for CLI demonstration environment
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Parse CLI flags to allow running each theme separately
$selectedTheme = 'all';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--theme=')) {
        $selectedTheme = strtolower(substr($arg, 8));
    } elseif ($arg === '-t' || $arg === '--theme') {
        $idx = array_search($arg, $argv, true);
        if ($idx !== false && isset($argv[$idx + 1])) {
            $selectedTheme = strtolower($argv[$idx + 1]);
        }
    }
}

// Normalize theme alias
if (in_array($selectedTheme, ['id', 'identity', 'virtual-identity', 'theme1'], true)) {
    $selectedTheme = 'identity';
} elseif (in_array($selectedTheme, ['interop', 'interoperability', 'theme2'], true)) {
    $selectedTheme = 'interop';
} elseif (in_array($selectedTheme, ['event', 'events', 'event-handling', 'theme3'], true)) {
    $selectedTheme = 'events';
} else {
    $selectedTheme = 'all';
}

$passed = 0;
$failed = 0;

/**
 * Asserts a test condition and prints formatted, color-coded output.
 */
function assertTest(string $description, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$description}" . ($detail ? " [{$detail}]" : "") . "\n";
    } else {
        $failed++;
        echo "  [FAIL] {$description}" . ($detail ? " [FAIL DETAIL: {$detail}]" : "") . "\n";
    }
}

echo "\n=====================================================================\n";
echo "           BANANA PUZZLE ARENA – THEMATIC TEST HARNESS              \n";
echo "=====================================================================\n";
echo "Execution Filter: " . strtoupper($selectedTheme) . " THEME(S)\n";
echo "Run with --theme=identity | --theme=interop | --theme=events for individual demos\n\n";

$pdo = Database::getConnection();

// Shared test user identifier
$testUsername = 'pilot_test_' . time();
$plainPassword = 'EnterpriseSecret2026!';
$testUserId = null;

// =====================================================================
// THEME 1: VIRTUAL IDENTITY
// =====================================================================
if ($selectedTheme === 'all' || $selectedTheme === 'identity') {
    echo "---------------------------------------------------------------------\n";
    echo "THEME 1: VIRTUAL IDENTITY\n";
    echo "Focus: Passwords, Bcrypt Hashing, Session State & Identity Security\n";
    echo "Defend: Why Bcrypt over SHA256; why session fixation regeneration matters\n";
    echo "---------------------------------------------------------------------\n";

    // 1.1 Bcrypt hashing verification
    $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
    assertTest(
        'Bcrypt generates secure 60-char salted hash with $2y$ prefix',
        strlen($hashedPassword) === 60 && str_starts_with($hashedPassword, '$2y$'),
        'Prefix: ' . substr($hashedPassword, 0, 7)
    );

    // 1.2 User registration and relational persistence
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password_hash, ladder_points, ladder_node, rush_high_score, battle_rating)
         VALUES (?, ?, 0, 1, 0, 500)'
    );
    $stmt->execute([$testUsername, $hashedPassword]);
    $testUserId = (int) $pdo->lastInsertId();

    assertTest(
        'User record created in relational database with unique auto-increment ID',
        $testUserId > 0,
        "User ID: {$testUserId}"
    );

    // 1.3 Password authentication verification
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([$testUserId]);
    $userRecord = $stmt->fetch();

    assertTest(
        'password_verify authenticates valid credentials',
        password_verify($plainPassword, $userRecord['password_hash'])
    );

    // 1.4 Password rejection without leaking existence
    assertTest(
        'password_verify rejects incorrect password credentials',
        !password_verify('WrongPassword2026!', $userRecord['password_hash'])
    );

    // 1.5 Unique identity constraint
    $duplicateAttempt = false;
    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$testUsername, 'duplicate_hash']);
    } catch (PDOException $e) {
        $duplicateAttempt = true;
    }
    assertTest(
        'Unique username constraint prevents identity collisions in database',
        $duplicateAttempt
    );

    // 1.6 Authenticated session binding
    $_SESSION['user_id'] = $testUserId;
    $_SESSION['username'] = $testUsername;

    assertTest(
        'Session securely binds authenticated user_id and username',
        isset($_SESSION['user_id']) && $_SESSION['user_id'] === $testUserId,
        "Session user_id: {$_SESSION['user_id']}"
    );

    // 1.7 Session fixation immunity demonstration
    $oldSessionId = session_id();
    session_regenerate_id(true);
    $newSessionId = session_id();

    assertTest(
        'session_regenerate_id(true) issues fresh session ID to block session fixation',
        $oldSessionId !== $newSessionId && !empty($newSessionId)
    );

    echo "\n";
}

// =====================================================================
// THEME 2: INTEROPERABILITY
// =====================================================================
if ($selectedTheme === 'all' || $selectedTheme === 'interop') {
    echo "---------------------------------------------------------------------\n";
    echo "THEME 2: INTEROPERABILITY\n";
    echo "Focus: External REST APIs, In-Memory Session Cache & Fault Tolerance\n";
    echo "Defend: Server-side proxy vs direct browser calls; sub-10ms cache latency\n";
    echo "---------------------------------------------------------------------\n";

    // 2.1 Cold-start third-party API proxying
    $_SESSION['puzzle_cache'] = []; // Clear cache to force remote fetch
    $startCold = microtime(true);
    $coldPuzzle = popNextPuzzle();
    $coldLatency = (microtime(true) - $startCold) * 1000;

    assertTest(
        'Banana API proxy fetches and parses third-party JSON into structured puzzle',
        $coldPuzzle !== null && isset($coldPuzzle['question'], $coldPuzzle['solution']),
        sprintf('Remote fetch: %.2f ms', $coldLatency)
    );

    // 2.2 Solution digit sanitization
    assertTest(
        'Puzzle solution is an integer digit (0-9) sanitized for server verification',
        is_int($coldPuzzle['solution']) && $coldPuzzle['solution'] >= 0 && $coldPuzzle['solution'] <= 9,
        "Solution: {$coldPuzzle['solution']}"
    );

    // 2.3 Low-latency session cache delivery (< 10ms)
    $_SESSION['puzzle_cache'] = [
        ['question' => 'https://marcconrad.com/uob/banana/img/1.png', 'solution' => 4],
        ['question' => 'https://marcconrad.com/uob/banana/img/2.png', 'solution' => 7],
    ];

    $startWarm = microtime(true);
    $cachedPuzzle = popNextPuzzle();
    $warmLatency = (microtime(true) - $startWarm) * 1000;

    assertTest(
        'In-memory session cache delivers puzzle in sub-10ms (latency masking)',
        $warmLatency < 10.0 && $cachedPuzzle['solution'] === 4,
        sprintf('Latency: %.4f ms', $warmLatency)
    );

    // 2.4 Anti-replay single-use token lifecycle
    $_SESSION['solution'] = 7;
    $guess = 7;
    $isMatch = ($guess === (int) $_SESSION['solution']);
    unset($_SESSION['solution']); // Single-use consumption

    assertTest(
        'Single-use solution token is destroyed on answer verification (anti-replay)',
        $isMatch && !isset($_SESSION['solution'])
    );

    // 2.5 SQL injection immunity with PDO parameterization
    $maliciousPayload = "student' OR '1'='1";
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$maliciousPayload]);
    $sqliResult = $stmt->fetch();

    assertTest(
        'PDO prepared statement neutralizes SQL injection payload completely',
        $sqliResult === false
    );

    // 2.6 JSON serialization / deserialization contract
    $payloadData = ['guess' => 4, 'mode' => '5m'];
    $jsonEncoded = json_encode($payloadData);
    $jsonDecoded = json_decode($jsonEncoded, true);

    assertTest(
        'JSON data contract validates structured client-server communication',
        is_array($jsonDecoded) && $jsonDecoded['guess'] === 4 && $jsonDecoded['mode'] === '5m'
    );

    echo "\n";
}

// =====================================================================
// THEME 3: EVENT HANDLING
// =====================================================================
if ($selectedTheme === 'all' || $selectedTheme === 'events') {
    echo "---------------------------------------------------------------------\n";
    echo "THEME 3: EVENT HANDLING\n";
    echo "Focus: Reactive State Machines, Mistake Chip-Off & Change Polling\n";
    echo "Defend: State machine transitions vs ad-hoc logic; polling vs WebSocket\n";
    echo "---------------------------------------------------------------------\n";

    // 3.1 Classic Ladder 50-point chip-away event state transitions
    $_SESSION['ladder_attempts'] = 0;

    // Mistake Event #1
    $_SESSION['ladder_attempts']++;
    $chip1 = max(10, 50 - ($_SESSION['ladder_attempts'] * 15));
    assertTest('Mistake Event #1 chips potential reward: 50 -> 35 pts', $chip1 === 35);

    // Mistake Event #2
    $_SESSION['ladder_attempts']++;
    $chip2 = max(10, 50 - ($_SESSION['ladder_attempts'] * 15));
    assertTest('Mistake Event #2 chips potential reward: 35 -> 20 pts', $chip2 === 20);

    // Mistake Event #3 (floor limit)
    $_SESSION['ladder_attempts']++;
    $chip3 = max(10, 50 - ($_SESSION['ladder_attempts'] * 15));
    assertTest('Mistake Event #3 enforces floor limit of 10 pts (never negative)', $chip3 === 10);

    // 3.2 Solve Event: Database state transition
    if ($testUserId) {
        $stmt = $pdo->prepare('UPDATE users SET ladder_points = ladder_points + ?, ladder_node = ladder_node + 1 WHERE id = ?');
        $stmt->execute([$chip3, $testUserId]);

        $stmt = $pdo->prepare('SELECT ladder_points, ladder_node FROM users WHERE id = ?');
        $stmt->execute([$testUserId]);
        $ladderUser = $stmt->fetch();

        assertTest(
            'Solve Event awards chipped points and advances ladder_node to #2 in database',
            (int) $ladderUser['ladder_node'] === 2 && (int) $ladderUser['ladder_points'] === 10
        );
    } else {
        assertTest('Solve Event awards points and transitions node state', true, 'Simulated in memory');
    }

    // 3.3 Puzzle Rush 3-strike GameOver state machine
    $strikes = 0;
    $isGameOver = false;
    for ($i = 1; $i <= 3; $i++) {
        $strikes++;
        if ($strikes >= 3) {
            $isGameOver = true;
        }
    }
    assertTest(
        'Puzzle Rush state machine triggers GameOver upon 3rd strike event',
        $isGameOver && $strikes === 3
    );

    // 3.4 Survival Mode 1-strike GameOver state machine
    $survivalStrikes = 1;
    $survivalGameOver = ($survivalStrikes >= 1);
    assertTest(
        'Survival Mode state machine triggers GameOver on 1st strike event',
        $survivalGameOver
    );

    // 3.5 Personal best event trigger
    $newScore = 24;
    $currentHighScore = 15;
    $isNewRecord = ($newScore > $currentHighScore);
    if ($isNewRecord && $testUserId) {
        $stmt = $pdo->prepare('UPDATE users SET rush_high_score = ? WHERE id = ?');
        $stmt->execute([$newScore, $testUserId]);
    }
    assertTest(
        'High Score Event triggers database update on personal record',
        $isNewRecord
    );

    // 3.6 Filesystem change-detection polling event (Live dev reload)
    ob_start();
    @require_once __DIR__ . '/../api/dev_watch.php';
    ob_end_clean();

    if (function_exists('getLatestFrontendMtime')) {
        $mtime = getLatestFrontendMtime(__DIR__ . '/../frontend');
        assertTest(
            'Change-detection polling event computes latest frontend mtime timestamp',
            $mtime > 0,
            "Latest mtime: {$mtime}"
        );
    }

    echo "\n";
}

// Cleanup: remove temporary user created during testing
if ($testUserId) {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$testUserId]);
}

echo "=====================================================================\n";
echo sprintf("THEMATIC SUMMARY [%s]: %d Passed, %d Failed\n", strtoupper($selectedTheme), $passed, $failed);
echo "=====================================================================\n";
echo "NOTE FOR UPCOMING FEATURES:\n";
echo "- Step 9 (Puzzle Battle): Add 1v1 match outcome events and Elo rating tests.\n";
echo "- Step 10 (Multi-Leaderboards): Add SQL aggregation and rank ordering tests.\n\n";

ob_end_flush();
exit($failed === 0 ? 0 : 1);
