<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Database;

use PDO;
use Vapehaus\Infrastructure\Environment\Env;

final class DatabaseConnection
{
    private static ?PDO $instance = null;

    public static function getPdo(?string $customDsn = null): PDO
    {
        if (self::$instance !== null && $customDsn === null) {
            return self::$instance;
        }

        if ($customDsn !== null) {
            $pdo = new PDO($customDsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            if (str_starts_with($customDsn, 'sqlite:')) {
                $pdo->exec('PRAGMA foreign_keys = ON;');
            }
            return $pdo;
        }

        // Check DATABASE_URL first (Render / Supabase PostgreSQL)
        $databaseUrl = Env::get('DATABASE_URL');
        if (!empty($databaseUrl) && is_string($databaseUrl)) {
            $parsed = parse_url($databaseUrl);
            if ($parsed !== false && isset($parsed['scheme'])) {
                $driver = $parsed['scheme'] === 'postgres' || $parsed['scheme'] === 'postgresql' ? 'pgsql' : $parsed['scheme'];
                $host = $parsed['host'] ?? '127.0.0.1';
                $port = $parsed['port'] ?? ($driver === 'pgsql' ? 5432 : 3306);
                $db = ltrim($parsed['path'] ?? 'vapehaus_db', '/');
                $user = $parsed['user'] ?? 'postgres';
                $pass = $parsed['pass'] ?? '';

                $dsn = "{$driver}:host={$host};port={$port};dbname={$db}";
                if ($driver === 'pgsql') {
                    $dsn .= ";sslmode=require";
                }

                $pdo = new PDO($dsn, $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                self::$instance = $pdo;
                return $pdo;
            }
        }

        // Check DB_DRIVER
        $driver = strtolower((string) Env::get('DB_DRIVER', 'sqlite'));

        if ($driver === 'mysql') {
            $host = (string) Env::get('DB_HOST', '127.0.0.1');
            $port = (string) Env::get('DB_PORT', '3306');
            $database = (string) Env::get('DB_DATABASE', 'vapehaus_db');
            $username = (string) Env::get('DB_USERNAME', 'root');
            $password = (string) Env::get('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            self::$instance = $pdo;
            return $pdo;
        }

        if ($driver === 'pgsql') {
            $host = (string) Env::get('DB_HOST', '127.0.0.1');
            $port = (string) Env::get('DB_PORT', '5432');
            $database = (string) Env::get('DB_DATABASE', 'postgres');
            $username = (string) Env::get('DB_USERNAME', 'postgres');
            $password = (string) Env::get('DB_PASSWORD', '');

            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            self::$instance = $pdo;
            return $pdo;
        }

        // Default: SQLite (zero-config, high determinism)
        $dbPath = dirname(__DIR__, 3) . '/database';
        if (!is_dir($dbPath)) {
            mkdir($dbPath, 0777, true);
        }
        $dsn = 'sqlite:' . $dbPath . '/vapehaus.sqlite';

        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        self::$instance = $pdo;
        return $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
