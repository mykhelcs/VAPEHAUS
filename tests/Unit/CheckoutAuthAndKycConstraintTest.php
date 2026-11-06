<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

require_once __DIR__ . '/Execution/Order/PlaceOrderExecutionTest.php';

use Vapehaus\App;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;
use Vapehaus\Domain\ValueObject\UserRole;
use Vapehaus\Execution\Order\PlaceOrderExecution;
use Vapehaus\Tests\TestCase;
use Vapehaus\Tests\Unit\Execution\Order\InMemoryCartRepository;
use Vapehaus\Tests\Unit\Execution\Order\InMemoryOrderRepository;
use Vapehaus\Tests\Unit\Execution\Order\InMemoryProductRepository;
use Vapehaus\Tests\Unit\Execution\Order\InMemoryUserRepository;

class CheckoutAuthAndKycConstraintTest extends TestCase
{
    private InMemoryOrderRepository $orderRepo;
    private InMemoryCartRepository $cartRepo;
    private InMemoryProductRepository $productRepo;
    private InMemoryUserRepository $userRepo;
    private PlaceOrderExecution $execution;
    private ProductVariant $testVariant;

    protected function setUp(): void
    {
        $this->orderRepo = new InMemoryOrderRepository();
        $this->cartRepo = new InMemoryCartRepository();
        $this->productRepo = new InMemoryProductRepository();
        $this->userRepo = new InMemoryUserRepository();

        $this->execution = new PlaceOrderExecution(
            $this->orderRepo,
            $this->cartRepo,
            $this->productRepo,
            $this->userRepo
        );

        $this->testVariant = new ProductVariant(
            'var_kyc_test_1',
            'prod_kyc_1',
            new Sku('KYC-TEST-001'),
            'Classic Tobacco',
            new NicotineStrength('3%'),
            Money::fromFloat(450.0),
            25,
            true
        );

        $product = new Product(
            'prod_kyc_1',
            'cat_pods',
            'KYC Verified Device Pod',
            'kyc-device-pod',
            'Vapehaus Brand',
            'Description',
            true,
            '2026-01-01 00:00:00',
            [$this->testVariant]
        );
        $this->productRepo->addProduct($product);
        $this->productRepo->addVariant($this->testVariant);
    }

    public function testPlaceOrderRejectsUnauthenticatedGuestCheckout(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You must be signed in with an active account to checkout');

        // Attempt checkout with null or empty userId (guest)
        $this->execution->execute(null, [
            'shipping_address' => '123 Bonifacio High Street, Taguig',
            'is_age_attested' => true,
            'items' => [
                ['variant_id' => 'var_kyc_test_1', 'quantity' => 1]
            ]
        ]);
    }

    public function testPlaceOrderRejectsUserWithoutApprovedKycIdVerification(): void
    {
        // Seed customer user with is_age_verified = false
        $unverifiedUser = new User(
            'usr_unverified_01',
            'unverified@domain.ph',
            'hashed_pass',
            'Maria',
            'Clara',
            '+639180000000',
            UserRole::customer(),
            '1998-08-10',
            false // NOT age/KYC verified
        );
        $this->userRepo->addUser($unverifiedUser);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Age verification with a valid government ID is required');

        $this->execution->execute('usr_unverified_01', [
            'shipping_address' => '123 Bonifacio High Street, Taguig',
            'is_age_attested' => true,
            'items' => [
                ['variant_id' => 'var_kyc_test_1', 'quantity' => 1]
            ]
        ]);
    }

    public function testPlaceOrderSucceedsWhenUserIsAuthenticatedAndKycVerified(): void
    {
        // Seed customer user with is_age_verified = true
        $verifiedUser = new User(
            'usr_verified_01',
            'verified@domain.ph',
            'hashed_pass',
            'Juan',
            'Dela Cruz',
            '+639170000001',
            UserRole::customer(),
            '1995-05-15',
            true // Verified 18+ KYC
        );
        $this->userRepo->addUser($verifiedUser);

        $result = $this->execution->execute('usr_verified_01', [
            'shipping_address' => '123 Bonifacio High Street, Taguig',
            'is_age_attested' => true,
            'items' => [
                ['variant_id' => 'var_kyc_test_1', 'quantity' => 1]
            ]
        ]);

        $this->assertNotNull($result);
        $this->assertEquals('usr_verified_01', $result['user_id']);
        $this->assertEquals(450.0, (float)$result['subtotal']);
    }

    public function testServiceWorkerAndOfflineQueueFilesExist(): void
    {
        $baseDir = dirname(__DIR__, 2);
        $swPath = $baseDir . '/public/sw.js';
        $queuePath = $baseDir . '/public/js/offline-queue.js';
        $indexPath = $baseDir . '/public/index.html';
        $checkoutJsPath = $baseDir . '/public/js/components/checkout.js';
        $appPath = $baseDir . '/src/App.php';

        $this->assertTrue(file_exists($swPath), 'public/sw.js must exist for image/data caching');
        $this->assertTrue(file_exists($queuePath), 'public/js/offline-queue.js must exist for offline sync');

        $swContent = (string) file_get_contents($swPath);
        $this->assertTrue(str_contains($swContent, 'vapehaus-media-v1'), 'sw.js must cache media');
        $this->assertTrue(str_contains($swContent, 'caches.open'), 'sw.js must use caches API');

        $queueContent = (string) file_get_contents($queuePath);
        $this->assertTrue(str_contains($queueContent, 'OfflineQueue'), 'offline-queue.js must declare OfflineQueue');
        $this->assertTrue(str_contains($queueContent, 'processQueue'), 'offline-queue.js must declare processQueue');

        $indexContent = (string) file_get_contents($indexPath);
        $this->assertTrue(str_contains($indexContent, 'offline-queue.js'), 'index.html must load offline-queue.js');
        $this->assertTrue(str_contains($indexContent, 'serviceWorker.register'), 'index.html must register sw.js');

        $checkoutJsContent = (string) file_get_contents($checkoutJsPath);
        $this->assertTrue(str_contains($checkoutJsContent, 'Store.isLoggedIn()'), 'checkout.js must check Store.isLoggedIn()');
        $this->assertTrue(str_contains($checkoutJsContent, 'uploadKycIdPhoto'), 'checkout.js must have uploadKycIdPhoto');

        $appContent = (string) file_get_contents($appPath);
        $this->assertTrue(str_contains($appContent, 'isInMemorySqlite'), 'App.php must guard SchemaManager runtime execution');
    }
}
