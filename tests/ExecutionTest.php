<?php

declare(strict_types=1);

namespace Vapehaus\Tests;

use DateTimeImmutable;
use PDO;
use Vapehaus\Domain\Exception\AuthenticationException;
use Vapehaus\Domain\Exception\UnderageException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\OrderStatus;
use Vapehaus\Execution\Admin\AdminCreateProductExecution;
use Vapehaus\Execution\Admin\AdminUpdateOrderStatusExecution;
use Vapehaus\Execution\Admin\AdminUpdateStockExecution;
use Vapehaus\Execution\Auth\AuthenticateUserExecution;
use Vapehaus\Execution\Auth\LogoutUserExecution;
use Vapehaus\Execution\Auth\RegisterUserExecution;
use Vapehaus\Execution\Cart\AddToCartExecution;
use Vapehaus\Execution\Cart\GetCartExecution;
use Vapehaus\Execution\Coupon\CouponExecution;
use Vapehaus\Execution\Order\PlaceOrderExecution;
use Vapehaus\Infrastructure\Database\DatabaseConnection;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Infrastructure\Repository\PdoAddressRepository;
use Vapehaus\Infrastructure\Repository\PdoCartRepository;
use Vapehaus\Infrastructure\Repository\PdoCategoryRepository;
use Vapehaus\Infrastructure\Repository\PdoCouponRepository;
use Vapehaus\Infrastructure\Repository\PdoInventoryAuditRepository;
use Vapehaus\Infrastructure\Repository\PdoOrderRepository;
use Vapehaus\Infrastructure\Repository\PdoProductRepository;
use Vapehaus\Infrastructure\Repository\PdoSessionRepository;
use Vapehaus\Infrastructure\Repository\PdoUserRepository;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Infrastructure\Security\PasswordHasher;

final class ExecutionTest extends TestCase
{
    private PDO $pdo;
    private PdoUserRepository $userRepo;
    private PdoCategoryRepository $categoryRepo;
    private PdoProductRepository $productRepo;
    private PdoCartRepository $cartRepo;
    private PdoOrderRepository $orderRepo;
    private PdoCouponRepository $couponRepo;
    private PdoSessionRepository $sessionRepo;
    private PasswordHasher $hasher;
    private JwtTokenService $tokenService;

    protected function setUp(): void
    {
        // Use fresh in-memory SQLite database for high determinism & isolated runs
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $schema = new SchemaManager($this->pdo);
        $schema->createTables();
        $schema->seedDefaults();

        $this->userRepo = new PdoUserRepository($this->pdo);
        $this->categoryRepo = new PdoCategoryRepository($this->pdo);
        $this->productRepo = new PdoProductRepository($this->pdo);
        $this->cartRepo = new PdoCartRepository($this->pdo, $this->productRepo);
        $this->orderRepo = new PdoOrderRepository($this->pdo);
        $this->couponRepo = new PdoCouponRepository($this->pdo);
        $this->sessionRepo = new PdoSessionRepository($this->pdo);
        $this->hasher = new PasswordHasher();
        $this->tokenService = new JwtTokenService();
    }

    // ── 1. Authentication: Login & Password Verification ────────────────

    public function testAuthenticateUserValidCredentials(): void
    {
        $exec = new AuthenticateUserExecution($this->userRepo, $this->hasher, $this->tokenService, $this->sessionRepo);
        $res = $exec->execute(['email' => 'customer@vapehaus.ph', 'password' => 'VapePass123!']);

        $this->assertNotNull($res['token']);
        $this->assertEquals('customer@vapehaus.ph', $res['user']['email']);
        $this->assertEquals('customer', $res['user']['role']);

        // Verify session was persisted in database
        $session = $this->sessionRepo->findByToken($res['token']);
        $this->assertNotNull($session);
        $this->assertEquals('usr_customer_default', $session->getUserId());
    }

    public function testAuthenticateUserInvalidPasswordThrows(): void
    {
        $exec = new AuthenticateUserExecution($this->userRepo, $this->hasher, $this->tokenService, $this->sessionRepo);
        $this->expectException(AuthenticationException::class);
        $exec->execute(['email' => 'customer@vapehaus.ph', 'password' => 'WrongPassword!']);
    }

    public function testAuthenticateUserUnknownEmailThrows(): void
    {
        $exec = new AuthenticateUserExecution($this->userRepo, $this->hasher, $this->tokenService, $this->sessionRepo);
        $this->expectException(AuthenticationException::class);
        $exec->execute(['email' => 'ghost@unknown.ph', 'password' => 'AnyPassword']);
    }

    // ── 2. Registration & 18+ Age Enforcement ───────────────────────────

    public function testRegisterAdultSuccess(): void
    {
        $exec = new RegisterUserExecution($this->userRepo, $this->hasher, $this->tokenService, 18, $this->sessionRepo);
        $dob = (new DateTimeImmutable('-22 years'))->format('Y-m-d');

        $res = $exec->execute([
            'email' => 'maria.santos@test.ph',
            'password' => 'MariaPass123!',
            'firstName' => 'Maria',
            'lastName' => 'Santos',
            'birthDate' => $dob,
            'isAgeAttested' => true,
        ]);

        $this->assertNotNull($res['token']);
        $this->assertEquals('maria.santos@test.ph', $res['user']['email']);
        $this->assertTrue($res['user']['is_age_verified']);
    }

    public function testRegisterUnderageThrows(): void
    {
        $exec = new RegisterUserExecution($this->userRepo, $this->hasher, $this->tokenService, 18, $this->sessionRepo);
        $minorDob = (new DateTimeImmutable('-15 years'))->format('Y-m-d');

        $this->expectException(UnderageException::class);
        $exec->execute([
            'email' => 'teen@test.ph',
            'password' => 'TeenPass123!',
            'firstName' => 'Teen',
            'lastName' => 'User',
            'birthDate' => $minorDob,
            'isAgeAttested' => true,
        ]);
    }

    public function testRegisterWithoutAttestationThrows(): void
    {
        $exec = new RegisterUserExecution($this->userRepo, $this->hasher, $this->tokenService, 18, $this->sessionRepo);
        $dob = (new DateTimeImmutable('-22 years'))->format('Y-m-d');

        $this->expectException(ValidationException::class);
        $exec->execute([
            'email' => 'noattest@test.ph',
            'password' => 'Pass123!',
            'firstName' => 'No',
            'lastName' => 'Attest',
            'birthDate' => $dob,
            'isAgeAttested' => false,
        ]);
    }

    // ── 3. Logout & Session Invalidation ────────────────────────────────

    public function testLogoutInvalidatesSession(): void
    {
        $auth = new AuthenticateUserExecution($this->userRepo, $this->hasher, $this->tokenService, $this->sessionRepo);
        $res = $auth->execute(['email' => 'customer@vapehaus.ph', 'password' => 'VapePass123!']);
        $token = $res['token'];

        $this->assertNotNull($this->sessionRepo->findByToken($token));

        $logout = new LogoutUserExecution($this->sessionRepo);
        $out = $logout->execute($token);
        $this->assertTrue($out['logged_out']);

        // Token must now be deleted/revoked
        $this->assertNull($this->sessionRepo->findByToken($token));
    }

    // ── 4. Cart & Stock Validations ────────────────────────────────────

    public function testCartAddAndGet(): void
    {
        $addExec = new AddToCartExecution($this->cartRepo, $this->productRepo);
        $getExec = new GetCartExecution($this->cartRepo, $this->productRepo);

        // Add 2 units of Geek Bar Watermelon Ice
        $cartData = $addExec->execute('usr_customer_default', null, 'var_gb_watermelon', 2);
        $this->assertCount(1, $cartData['items']);
        $this->assertEquals(2, $cartData['items'][0]['quantity']);

        // Check get cart
        $cart = $getExec->execute('usr_customer_default', null);
        $this->assertEquals(2, $cart['item_count']);
        $this->assertEquals(1300.0, $cart['subtotal']); // 650 * 2
    }

    // ── 5. Order Placement (VH- Prefix, Stock Deduction, Cart Clear) ───

    public function testPlaceOrderDeductsStockAndClearsCart(): void
    {
        $addExec = new AddToCartExecution($this->cartRepo, $this->productRepo);
        $addExec->execute('usr_customer_default', null, 'var_gb_watermelon', 2);

        $variantBefore = $this->productRepo->findVariantById('var_gb_watermelon');
        $stockBefore = $variantBefore->getStockQuantity();

        $placeExec = new PlaceOrderExecution(
            $this->orderRepo,
            $this->cartRepo,
            $this->productRepo,
            $this->userRepo,
            new PdoAddressRepository($this->pdo),
            $this->couponRepo,
            new PdoInventoryAuditRepository($this->pdo)
        );

        $order = $placeExec->execute('usr_customer_default', [
            'shippingAddress' => 'Unit 12B, Emerald Tower, Pasig City',
            'paymentMethod' => 'cod',
            'isAgeAttested' => true,
        ]);

        $this->assertNotNull($order['orderNumber']);
        $this->assertTrue(str_starts_with($order['orderNumber'], 'VH-'));
        $this->assertEquals('pending', $order['status']);

        // Stock must be deducted by 2
        $variantAfter = $this->productRepo->findVariantById('var_gb_watermelon');
        $this->assertEquals($stockBefore - 2, $variantAfter->getStockQuantity());

        // Cart must be cleared
        $getCart = new GetCartExecution($this->cartRepo, $this->productRepo);
        $cartAfter = $getCart->execute('usr_customer_default', null);
        $this->assertEquals(0, $cartAfter['item_count']);
    }

    // ── 6. Admin Inventory Adjustments ─────────────────────────────────

    public function testAdminStockAdjustments(): void
    {
        $stockExec = new AdminUpdateStockExecution($this->productRepo);

        // Absolute set
        $res = $stockExec->execute('var_gb_blowpop', ['stock' => 50, 'isDelta' => false]);
        $this->assertEquals(50, $res['stock']);

        // Delta increment
        $res2 = $stockExec->execute('var_gb_blowpop', ['stock' => 5, 'isDelta' => true]);
        $this->assertEquals(55, $res2['stock']);

        // Standard format { adjustment_type: 'decrement', amount: 10 }
        $res3 = $stockExec->execute('var_gb_blowpop', ['adjustment_type' => 'decrement', 'amount' => 10]);
        $this->assertEquals(45, $res3['stock']);
    }
}
