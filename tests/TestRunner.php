<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Throwable;
use Vapehaus\Tests\TestCase;

$testClasses = [
    // Domain Unit Tests
    \Vapehaus\Tests\Unit\Domain\UserTest::class,
    \Vapehaus\Tests\Unit\Domain\ProductVariantTest::class,
    \Vapehaus\Tests\Unit\Domain\OrderTest::class,
    \Vapehaus\Tests\Unit\Domain\CouponTest::class,
    \Vapehaus\Tests\Unit\Domain\UserAddressTest::class,

    // Execution Unit Tests
    \Vapehaus\Tests\Unit\Execution\RegisterUserExecutionTest::class,
    \Vapehaus\Tests\Unit\Execution\AddToCartExecutionTest::class,
    \Vapehaus\Tests\Unit\Execution\PlaceOrderExecutionTest::class,
    \Vapehaus\Tests\Unit\Execution\CreatePaymentIntentExecutionTest::class,

    // API Integration Tests
    \Vapehaus\Tests\Integration\Api\AuthApiTest::class,
    \Vapehaus\Tests\Integration\Api\CatalogApiTest::class,
    \Vapehaus\Tests\Integration\Api\CartApiTest::class,
    \Vapehaus\Tests\Integration\Api\OrderApiTest::class,
    \Vapehaus\Tests\Integration\Api\AdminApiTest::class,
    \Vapehaus\Tests\Integration\Api\AddressApiTest::class,
    \Vapehaus\Tests\Integration\Api\CouponApiTest::class,
    \Vapehaus\Tests\Integration\Api\PaymentWebhookApiTest::class,
    \Vapehaus\Tests\Integration\Api\ReviewApiTest::class,
    \Vapehaus\Tests\Integration\Api\KycApiTest::class,
    \Vapehaus\Tests\Integration\Api\RmaApiTest::class,
    \Vapehaus\Tests\Integration\Api\WishlistApiTest::class,
];

echo "========================================================\n";
echo " Vapehaus Full E-Commerce Production Test Suite (TDD)\n";
echo "========================================================\n\n";

$totalTests = 0;
$totalAssertions = 0;
$passed = 0;
$failed = 0;
$failures = [];
$startTime = microtime(true);

foreach ($testClasses as $className) {
    echo "Running {$className}:\n";
    $reflector = new ReflectionClass($className);
    $methods = $reflector->getMethods(ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }

        $totalTests++;
        /** @var TestCase $testInstance */
        $testInstance = new $className();

        try {
            $testInstance->runTest($method->getName());
            $assertions = $testInstance->getAssertionCount();
            $totalAssertions += $assertions;
            $passed++;
            echo "  ✔ {$method->getName()} ({$assertions} assertions)\n";
        } catch (Throwable $e) {
            $failed++;
            $failures[] = [
                'class' => $className,
                'method' => $method->getName(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            echo "  ✖ {$method->getName()} - FAILED: {$e->getMessage()}\n";
        }
    }
    echo "\n";
}

$duration = round(microtime(true) - $startTime, 4);

echo "========================================================\n";
if ($failed === 0) {
    echo "OK ({$totalTests} tests, {$totalAssertions} assertions, {$duration}s) - ALL TESTS PASSED! 🎉\n";
} else {
    echo "FAILURES! Total: {$totalTests}, Passed: {$passed}, Failed: {$failed}\n\n";
    foreach ($failures as $f) {
        echo "FAIL: {$f['class']}::{$f['method']}\n";
        echo "      {$f['message']}\n";
        echo "      at {$f['file']}:{$f['line']}\n\n";
    }
}
echo "========================================================\n";

exit($failed === 0 ? 0 : 1);
