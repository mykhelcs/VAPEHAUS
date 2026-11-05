<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution;

use PDO;
use Vapehaus\Execution\System\CheckDatabaseHealthExecution;
use Vapehaus\Infrastructure\Database\DatabaseConnection;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Tests\TestCase;

final class DatabaseHealthExecutionTest extends TestCase
{
    public function testCheckDatabaseHealthReturnsAccurateMetrics(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $schema = new SchemaManager($pdo);
        $schema->createTables();
        $schema->seedDefaults();

        $execution = new CheckDatabaseHealthExecution($pdo);
        $health = $execution->execute();

        $this->assertTrue($health['connected']);
        $this->assertEquals('sqlite', $health['driver']);
        $this->assertTrue($health['latency_ms'] >= 0.0);
        $this->assertTrue($health['tables_count'] > 0);
        $this->assertEquals(2, $health['counts']['users']); // Admin and Customer seeded
        $this->assertTrue($health['counts']['products'] >= 0);
        $this->assertEquals(3, $health['counts']['categories']);
    }

    public function testCheckDatabaseHealthHandlesQueryFailureGracefully(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE dummy (id INTEGER);');
        $execution = new CheckDatabaseHealthExecution($pdo);
        $health = $execution->execute();

        $this->assertFalse($health['connected']);
        $this->assertEquals('none', $health['driver']);
        $this->assertTrue(!empty($health['error']));
        $this->assertEquals(0, $health['counts']['users']);
    }

    public function testDatabaseConnectionGracefulFallbackToSqlite(): void
    {
        DatabaseConnection::reset();
        putenv('DATABASE_URL=postgresql://invalid_user:invalid_pass@127.0.0.1:54329/nonexistent_db');
        $_ENV['DATABASE_URL'] = 'postgresql://invalid_user:invalid_pass@127.0.0.1:54329/nonexistent_db';
        putenv('DB_HOST=127.0.0.1');
        $_ENV['DB_HOST'] = '127.0.0.1';
        putenv('DB_PORT=54329');
        $_ENV['DB_PORT'] = '54329';
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';

        $pdo = DatabaseConnection::getPdo();
        $this->assertTrue($pdo instanceof PDO);
        $this->assertEquals('sqlite', $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        DatabaseConnection::reset();
        putenv('DATABASE_URL=');
        unset($_ENV['DATABASE_URL']);
        putenv('DB_HOST=');
        unset($_ENV['DB_HOST']);
        putenv('DB_PORT=');
        unset($_ENV['DB_PORT']);
    }
}
