<?php
/**
 * Database connection — works both locally (XAMPP) and on Railway.
 *
 * On Railway: set the DATABASE_URL environment variable to the
 * PostgreSQL connection string provided in the Railway dashboard.
 *
 * Locally: falls back to the hardcoded XAMPP credentials below.
 */

// ── Railway / production ────────────────────────────────────────
if (!empty($_ENV['DATABASE_URL']) || !empty(getenv('DATABASE_URL'))) {
    $url = parse_url($_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL'));

    $host     = $url['host'];
    $port     = $url['port']     ?? 5432;
    $dbname   = ltrim($url['path'], '/');
    $user     = $url['user'];
    $password = $url['pass'];

// ── Local XAMPP fallback ────────────────────────────────────────
} else {
    $host     = 'localhost';
    $port     = 5432;
    $dbname   = 'rafiq';
    $user     = 'postgres';
    $password = '123456789';
}

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Show a clean error — never expose credentials in production
    $safe = defined('APP_DEBUG') && APP_DEBUG
        ? $e->getMessage()
        : 'Database connection failed. Please try again later.';
    http_response_code(503);
    die($safe);
}
?>
