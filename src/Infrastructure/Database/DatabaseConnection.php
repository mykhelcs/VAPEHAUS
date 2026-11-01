<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Database;

use PDO;
use Throwable;
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

        // 1. Check DATABASE_URL first (Render / Supabase URL format)
        $databaseUrl = Env::get('DATABASE_URL');
        if (!empty($databaseUrl) && is_string($databaseUrl)) {
            $parsed = parse_url($databaseUrl);
            if ($parsed !== false && isset($parsed['scheme'])) {
                $driver = $parsed['scheme'] === 'postgres' || $parsed['scheme'] === 'postgresql' ? 'pgsql' : $parsed['scheme'];
                $host = $parsed['host'] ?? '';
                $port = $parsed['port'] ?? ($driver === 'pgsql' ? 5432 : 3306);
                $db = ltrim($parsed['path'] ?? 'vapehaus_db', '/');
                $user = $parsed['user'] ?? 'postgres';
                $pass = $parsed['pass'] ?? '';

                if (!empty($host)) {
                    $dsn = "{$driver}:host={$host};port={$port};dbname={$db}";
                    if ($driver === 'pgsql') {
                        $dsn .= ";sslmode=require";
                    }

                    try {
                        $pdo = new PDO($dsn, $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                        self::$instance = $pdo;
                        return $pdo;
                    } catch (Throwable $e) {
                        if (Env::get('APP_ENV') !== 'production') {
                            error_log("DatabaseConnection: Remote DB via DATABASE_URL failed (" . $e->getMessage() . "), falling back.");
                        } else {
                            throw $e;
                        }
                    }
                }
            }
        }

        // 2. Check DB_DRIVER or infer driver from DB_HOST / parameters
        $rawDriver = Env::get('DB_DRIVER');
        $host = (string) (Env::get('DB_HOST') ?? '');

        if (!empty($rawDriver)) {
            $driver = strtolower((string) $rawDriver);
        } elseif (!empty($host)) {
            // If DB_HOST is set without DB_DRIVER, detect based on host or default to pgsql
            $driver = str_contains($host, 'supabase') || str_contains($host, 'postgres') ? 'pgsql' : 'mysql';
        } else {
            $driver = 'sqlite';
        }

        if ($driver === 'mysql') {
            $port = (string) Env::get('DB_PORT', '3306');
            $database = (string) (Env::get('DB_DATABASE') ?? Env::get('DB_NAME') ?? 'vapehaus_db');
            $username = (string) (Env::get('DB_USERNAME') ?? Env::get('DB_USER') ?? 'root');
            $password = (string) Env::get('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                self::$instance = $pdo;
                return $pdo;
            } catch (Throwable $e) {
                if (Env::get('APP_ENV') !== 'production') {
                    error_log("DatabaseConnection: MySQL failed (" . $e->getMessage() . "), falling back to local SQLite.");
                } else {
                    throw $e;
                }
            }
        }

        if ($driver === 'pgsql') {
            $port = (string) Env::get('DB_PORT', '5432');
            $database = (string) (Env::get('DB_DATABASE') ?? Env::get('DB_NAME') ?? 'postgres');
            $username = (string) (Env::get('DB_USERNAME') ?? Env::get('DB_USER') ?? 'postgres');
            $password = (string) Env::get('DB_PASSWORD', '');

            $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode=require";
            try {
                $pdo = new PDO($dsn, $username, $password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                self::$instance = $pdo;
                return $pdo;
            } catch (Throwable $e) {
                if (Env::get('APP_ENV') !== 'production') {
                    error_log("DatabaseConnection: pgsql failed (" . $e->getMessage() . "), falling back to local SQLite.");
                } else {
                    throw $e;
                }
            }
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
