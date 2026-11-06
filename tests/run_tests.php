<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Vapehaus\Tests\ApiIntegrationTest;
use Vapehaus\Tests\DomainTest;
use Vapehaus\Tests\ExecutionTest;
use Vapehaus\Tests\Integration\Api\AdminOrdersFulfillmentTest;
use Vapehaus\Tests\Integration\Api\AuthApiTest;
use Vapehaus\Tests\Integration\Api\CodOrderAndTrackingTest;
use Vapehaus\Tests\Integration\Api\PayMongoCheckoutWebhookTest;
use Vapehaus\Tests\TestCase;
use Vapehaus\Tests\Unit\DesignSystemConstraintTest;
use Vapehaus\Tests\Unit\Execution\AdminDeleteProductExecutionTest;
use Vapehaus\Tests\Unit\Execution\CreateCheckoutSessionExecutionTest;
use Vapehaus\Tests\Unit\Execution\DatabaseHealthExecutionTest;
use Vapehaus\Tests\Unit\Execution\Order\PlaceOrderExecutionTest;
use Vapehaus\Tests\Unit\HmacOtpRepositoryTest;
use Vapehaus\Tests\Unit\CheckoutAuthAndKycConstraintTest;
use Vapehaus\Tests\Unit\PayMongoGatewayTest;
use Vapehaus\Tests\Unit\RootRoutingTest;

$testClasses = [
    CheckoutAuthAndKycConstraintTest::class,
    RootRoutingTest::class,
    DesignSystemConstraintTest::class,
    DomainTest::class,
    ExecutionTest::class,
    HmacOtpRepositoryTest::class,
    AdminDeleteProductExecutionTest::class,
    DatabaseHealthExecutionTest::class,
    PlaceOrderExecutionTest::class,
    CreateCheckoutSessionExecutionTest::class,
    PayMongoGatewayTest::class,
    PayMongoCheckoutWebhookTest::class,
    AuthApiTest::class,
    CodOrderAndTrackingTest::class,
    AdminOrdersFulfillmentTest::class,
    ApiIntegrationTest::class,
];

echo "============================================================\n";
echo "VAPEHAUS — PHP BACKEND AUTOMATED TEST SUITE\n";
echo "Architecture: framework.md (Strict Layer Separation & TDD)\n";
echo "============================================================\n\n";

$totalTests = 0;
$totalAssertions = 0;
$passed = 0;
$failed = 0;
$failures = [];

$startTime = microtime(true);

foreach ($testClasses as $class) {
    $ref = new ReflectionClass($class);
    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
    $className = $ref->getShortName();

    echo "Suite: {$className}\n";

    foreach ($methods as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $totalTests++;
        /** @var TestCase $testInstance */
        $testInstance = new $class();

        try {
            $testInstance->runTest($method->getName());
            $passed++;
            $totalAssertions += $testInstance->getAssertionCount();
            echo "  ✓ {$method->getName()}\n";
        } catch (Throwable $e) {
            $failed++;
            $failures[] = [
                'class' => $className,
                'method' => $method->getName(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            echo "  ✗ {$method->getName()} (FAIL: {$e->getMessage()})\n";
        }
    }
    echo "\n";
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);

echo "============================================================\n";
echo "RESULTS: {$totalTests} tests | {$passed} passed | {$failed} failed | {$totalAssertions} assertions ({$elapsed}ms)\n";
echo "============================================================\n";

if ($failed > 0) {
    echo "\nFAILURES DETAIL:\n";
    foreach ($failures as $f) {
        echo "• {$f['class']}::{$f['method']}\n";
        echo "  Message: {$f['message']}\n";
        echo "  Location: {$f['file']}:{$f['line']}\n\n";
    }
    exit(1);
}

echo "All PHP backend tests passed successfully!\n";
exit(0);
