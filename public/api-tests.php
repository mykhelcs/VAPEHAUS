<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Vapehaus\Tests\ApiIntegrationTest;
use Vapehaus\Tests\DomainTest;
use Vapehaus\Tests\ExecutionTest;
use Vapehaus\Tests\TestCase;

$testClasses = [
    DomainTest::class,
    ExecutionTest::class,
    ApiIntegrationTest::class,
];

$results = [];
$totalTests = 0;
$totalAssertions = 0;
$passed = 0;
$failed = 0;

$startTime = microtime(true);

foreach ($testClasses as $class) {
    $ref = new ReflectionClass($class);
    $methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
    $className = $ref->getShortName();

    $suiteResults = [];

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
            $assertions = $testInstance->getAssertionCount();
            $totalAssertions += $assertions;
            $suiteResults[] = [
                'name' => $method->getName(),
                'passed' => true,
                'assertions' => $assertions,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $failed++;
            $suiteResults[] = [
                'name' => $method->getName(),
                'passed' => false,
                'assertions' => 0,
                'error' => $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine(),
            ];
        }
    }

    $results[$className] = $suiteResults;
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);
$pct = $totalTests > 0 ? round(($passed / $totalTests) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VAPEHAUS — PHP Backend Test Suite</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Courier New', monospace; background: #0b0b10; color: #e2e8f0; padding: 24px; }
    h1 { font-size: 1.4rem; color: #8b5cf6; margin-bottom: 4px; }
    .suite-meta { font-size: 0.8rem; color: #64748b; margin-bottom: 24px; }
    .suite { margin-bottom: 28px; }
    .suite-title { font-size: 1rem; font-weight: 700; color: #22d3ee; border-bottom: 1px solid #1e293b; padding-bottom: 6px; margin-bottom: 10px; }
    .test { display: flex; align-items: flex-start; gap: 10px; padding: 6px 0; font-size: 0.82rem; border-bottom: 1px solid #0f172a; }
    .test:last-child { border-bottom: none; }
    .icon { font-size: 0.9rem; flex-shrink: 0; margin-top: 1px; }
    .pass { color: #4ade80; }
    .fail { color: #f87171; }
    .test-name { flex: 1; }
    .test-error { font-size: 0.75rem; color: #f87171; margin-top: 2px; }
    #summary { position: sticky; top: 0; background: #0b0b10; border-bottom: 1px solid #1e293b; padding: 12px 0 16px; margin-bottom: 24px; font-size: 0.88rem; }
    #summary .totals { display: flex; gap: 20px; margin-top: 6px; }
    .total-pass { color: #4ade80; font-weight: 700; }
    .total-fail { color: #f87171; font-weight: 700; }
    .progress-bar { height: 4px; background: #1e293b; border-radius: 2px; margin-top: 10px; }
    .progress-fill { height: 100%; border-radius: 2px; }
  </style>
</head>
<body>

<div id="summary">
  <h1>VAPEHAUS — PHP Backend Automated Test Suite</h1>
  <div class="suite-meta"><?= $totalTests ?> tests · <?= $totalAssertions ?> assertions · <?= $elapsed ?>ms · <?= $pct ?>% pass rate</div>
  <div class="totals">
    <span class="total-pass">✓ <?= $passed ?> passed</span>
    <span class="total-fail">✗ <?= $failed ?> failed</span>
  </div>
  <div class="progress-bar">
    <div class="progress-fill" style="width: <?= $pct ?>%; background: <?= $failed > 0 ? '#f87171' : '#4ade80' ?>;"></div>
  </div>
</div>

<div id="test-output">
  <?php foreach ($results as $suiteName => $tests): ?>
    <div class="suite">
      <div class="suite-title"><?= htmlspecialchars($suiteName) ?></div>
      <?php foreach ($tests as $t): ?>
        <div class="test">
          <span class="icon <?= $t['passed'] ? 'pass' : 'fail' ?>"><?= $t['passed'] ? '✓' : '✗' ?></span>
          <div class="test-name <?= $t['passed'] ? '' : 'fail' ?>">
            <?= htmlspecialchars($t['name']) ?>
            <?php if (!$t['passed']): ?>
              <div class="test-error"><?= htmlspecialchars((string) $t['error']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

</body>
</html>
