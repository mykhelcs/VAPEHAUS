<?php

declare(strict_types=1);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'Vapehaus\\';
        $baseDir = __DIR__ . '/';
        $len = strlen($prefix);

        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }

        $testPrefix = 'Vapehaus\\Tests\\';
        $testBaseDir = dirname(__DIR__) . '/tests/';
        $testLen = strlen($testPrefix);

        if (strncmp($testPrefix, $class, $testLen) === 0) {
            $relativeTestClass = substr($class, $testLen);
            $testFile = $testBaseDir . str_replace('\\', '/', $relativeTestClass) . '.php';
            if (file_exists($testFile)) {
                require_once $testFile;
            }
        }
    });
}
