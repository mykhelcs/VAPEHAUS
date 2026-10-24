<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Database;

use PDO;

final class DatabaseConnection
{
    private static ?PDO $instance = null;

    public static function getPdo(?string $customDsn = null): PDO
    {
        if (self::$instance !== null && $customDsn === null) {
            return self::$instance;
        }

        $dsn = $customDsn;
        if ($dsn === null) {
            $dbPath = dirname(__DIR__, 3) . '/database';
            if (!is_dir($dbPath)) {
                mkdir($dbPath, 0777, true);
            }
            $dsn = 'sqlite:' . $dbPath . '/vapehaus.sqlite';
        }

        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if (str_starts_with($dsn, 'sqlite:')) {
            $pdo->exec('PRAGMA foreign_keys = ON;');
        }

        if ($customDsn === null) {
            self::$instance = $pdo;
        }

        return $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
