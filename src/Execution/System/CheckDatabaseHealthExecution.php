<?php

declare(strict_types=1);

namespace Vapehaus\Execution\System;

use PDO;
use Throwable;

final class CheckDatabaseHealthExecution
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $startTime = microtime(true);

        try {
            // Ping query
            $this->pdo->query('SELECT 1');
            $latencyMs = round((microtime(true) - $startTime) * 1000, 2);

            $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            // Fetch table list based on driver
            $tables = [];
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                $stmt = $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            // Quick table counts
            $counts = [
                'users' => (int) ($this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() ?: 0),
                'products' => (int) ($this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() ?: 0),
                'orders' => (int) ($this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() ?: 0),
                'categories' => (int) ($this->pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn() ?: 0),
            ];

            return [
                'connected' => true,
                'driver' => $driver,
                'latency_ms' => $latencyMs,
                'tables_count' => count($tables),
                'tables' => $tables,
                'counts' => $counts,
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'driver' => 'none',
                'latency_ms' => 0.0,
                'tables_count' => 0,
                'tables' => [],
                'counts' => [
                    'users' => 0,
                    'products' => 0,
                    'orders' => 0,
                    'categories' => 0,
                ],
                'error' => $e->getMessage(),
                'checked_at' => date('Y-m-d H:i:s'),
            ];
        }
    }
}
