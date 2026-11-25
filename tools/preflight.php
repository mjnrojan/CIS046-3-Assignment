<?php

/**
 * Pre-flight check run by start.bat before the server starts.
 *
 * Why: during a live demo, a missing .env or a stopped MySQL service gives a
 * confusing error deep inside the game. Checking up front prints one clear
 * message instead. Exit code 0 = ready, 1 = something must be fixed first.
 *
 * Run by hand: php tools/preflight.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

$ok = true;

/**
 * Prints one check result and remembers whether any required check failed.
 */
function report(string $label, bool $passed, string $hint = '', bool $required = true): void
{
    global $ok;

    $tag = $passed ? '[ OK ]' : ($required ? '[FAIL]' : '[WARN]');
    echo "  {$tag} {$label}" . (!$passed && $hint !== '' ? " -> {$hint}" : '') . "\n";

    if (!$passed && $required) {
        $ok = false;
    }
}

echo "Pre-flight checks\n";

report('PHP 8.1 or newer (found ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), 'install a newer PHP');
report('pdo_mysql extension', extension_loaded('pdo_mysql'), 'enable extension=pdo_mysql in php.ini');
report('openssl extension (HTTPS calls to external APIs)', extension_loaded('openssl'), 'enable extension=openssl in php.ini');
report('.env file present', Env::fileExists(), 'copy .env.example to .env and fill in your values');

if (Env::fileExists()) {
    try {
        $pdo = Database::getConnection();
        report('MySQL reachable at ' . Env::get('DB_HOST') . ':' . Env::get('DB_PORT'), true);

        $hasUsers = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn() !== false;
        report('Database tables exist', $hasUsers, 'run: start.bat --reset   (or php sql/migrate.php)');
    } catch (Exception $e) {
        report('MySQL reachable', false, 'start MySQL (XAMPP control panel or the MySQL service). ' . $e->getMessage());
    }

    report('Freesound API key set (sound effects)', Env::get('FREESOUND_API_KEY') !== '' && Env::get('FREESOUND_API_KEY') !== 'your_api_key_here', 'game works without sound; add FREESOUND_API_KEY to .env', false);
}

echo $ok ? "All required checks passed.\n" : "Fix the [FAIL] items above, then run start.bat again.\n";

exit($ok ? 0 : 1);
