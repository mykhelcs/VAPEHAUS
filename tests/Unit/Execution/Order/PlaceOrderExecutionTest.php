<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution\Order;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Cart;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\Repository\AddressRepositoryInterface;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\CouponRepositoryInterface;
use Vapehaus\Domain\Repository\InventoryAuditRepositoryInterface;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;
use Vapehaus\Domain\ValueObject\UserRole;
use Vapehaus\Execution\Order\PlaceOrderExecution;
use Vapehaus\Tests\TestCase;

final class PlaceOrderExecutionTest extends TestCase
{
    private InMemoryOrderRepository $orderRepo;
    private InMemoryCartRepository $cartRepo;
    private InMemoryProductRepository $productRepo;
    private InMemoryUserRepository $userRepo;
    private PlaceOrderExecution $execution;
    private ProductVariant $testVariant;
    private User $testUser;

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

        // Seed test product & variant
        $this->testVariant = new ProductVariant(
            'var_test_100',
            'prod_test_1',
            new Sku('TEST-SKU-100'),
            'Mint Frost',
            new NicotineStrength('3%'),
            Money::fromFloat(350.0),
            50,
            true
        );

        $product = new Product(
            'prod_test_1',
            'cat_pods',
            'Test Device Pod',
            'test-device-pod',
            'Vapehaus Brand',
            'Description',
            true,
            '2026-01-01 00:00:00',
            [$this->testVariant]
        );
        $this->productRepo->addProduct($product);
        $this->productRepo->addVariant($this->testVariant);

        // Seed customer user
        $this->testUser = new User(
            'usr_cust_001',
            'test@vapehaus.ph',
            'hash',
            'Juan',
            'Dela Cruz',
            '+639171234567',
            UserRole::customer(),
            '1995-05-15',
            true
        );
        $this->userRepo->addUser($this->testUser);
    }

    public function testThrowsWhenCartAndPayloadItemsAreBothEmpty(): void
    {
        $this->expectException(ValidationException::class);

        $this->execution->execute('usr_cust_001', [
            'is_age_attested' => true,
            'shipping_address' => 'Unit 12B Emerald Ave, Pasig',
            'items' => [],
        ]);
    }

    public function testSucceedsWhenDbCartIsEmptyButPayloadItemsAreProvided(): void
    {
        // DB cart is completely empty (null)
        $data = [
            'is_age_attested' => true,
            'shipping_address' => 'Unit 12B Emerald Ave, Pasig City',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'items' => [
                [
                    'variant_id' => 'var_test_100',
                    'quantity' => 2,
                ],
            ],
        ];

        $orderData = $this->execution->execute('usr_cust_001', $data);

        $this->assertNotNull($orderData);
        $this->assertEquals('pending', $orderData['status']);
        $this->assertEquals(700.0, $orderData['subtotal']); // 2 * 350
        $this->assertEquals(150.0, $orderData['shipping_fee']); // Standard < 1500
        $this->assertEquals(850.0, $orderData['total']); // 700 + 150
        $this->assertCount(1, $orderData['items']);
        $this->assertEquals(48, $this->testVariant->getStockQuantity()); // 50 - 2
    }

    public function testExpressShippingCalculates250Fee(): void
    {
        $data = [
            'is_age_attested' => true,
            'shipping_address' => 'Unit 12B Emerald Ave, Pasig City',
            'shipping_method' => 'express',
            'items' => [
                ['variant_id' => 'var_test_100', 'quantity' => 1],
            ],
        ];

        $orderData = $this->execution->execute('usr_cust_001', $data);
        $this->assertEquals(250.0, $orderData['shipping_fee']);
        $this->assertEquals(600.0, $orderData['total']); // 350 + 250
    }

    public function testStandardShippingOverFreeThresholdIsFree(): void
    {
        $data = [
            'is_age_attested' => true,
            'shipping_address' => 'Unit 12B Emerald Ave, Pasig City',
            'shipping_method' => 'standard',
            'items' => [
                ['variant_id' => 'var_test_100', 'quantity' => 5], // 5 * 350 = 1750 (>= 1500)
            ],
        ];

        $orderData = $this->execution->execute('usr_cust_001', $data);
        $this->assertEquals(0.0, $orderData['shipping_fee']);
        $this->assertEquals(1750.0, $orderData['total']);
    }

    public function testDeliveryNotesAppendedToAddress(): void
    {
        $data = [
            'is_age_attested' => true,
            'shipping_address' => 'Unit 12B Emerald Ave, Pasig City',
            'delivery_notes' => 'Please leave with tower lobby guard',
            'shipping_method' => 'standard',
            'items' => [
                ['variant_id' => 'var_test_100', 'quantity' => 1],
            ],
        ];

        $orderData = $this->execution->execute('usr_cust_001', $data);
        $this->assertTrue(str_contains($orderData['shipping_address'], 'Please leave with tower lobby guard'));
    }

    public function testThrowsValidationExceptionWhenVariantNotFoundInCatalog(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Item 'var_810da53e6a8e' is no longer available in the active catalog.");

        $data = [
            'is_age_attested' => true,
            'shipping_address' => 'Unit 12B Emerald Ave, Pasig City',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'items' => [
                [
                    'variant_id' => 'var_810da53e6a8e',
                    'quantity' => 1,
                ],
            ],
        ];

        $this->execution->execute('usr_cust_001', $data);
    }

    public function testResolvesVariantBySkuWhenIdMatchesSku(): void
    {
        $data = [
            'is_age_attested' => true,
            'shipping_address' => 'Unit 12B Emerald Ave, Pasig City',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'items' => [
                [
                    'variant_id' => 'TEST-SKU-100', // Sku passed instead of var_ ID
                    'quantity' => 1,
                ],
            ],
        ];

        $orderData = $this->execution->execute('usr_cust_001', $data);

        $this->assertNotNull($orderData);
        $this->assertEquals(350.0, $orderData['subtotal']);
        $this->assertEquals(1, count($orderData['items']));
        $this->assertEquals('var_test_100', $orderData['items'][0]['variant_id']);
    }
}

// ── In-Memory Repositories for TDD Execution ─────────────────────────────────

final class InMemoryOrderRepository implements OrderRepositoryInterface
{
    /** @var array<string, Order> */
    public array $orders = [];

    public function save(Order $order): void
    {
        $this->orders[$order->getId()] = $order;
    }

    public function findById(string $id): ?Order
    {
        return $this->orders[$id] ?? null;
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        foreach ($this->orders as $o) {
            if ($o->getOrderNumber() === $orderNumber) return $o;
        }
        return null;
    }

    public function findByUserId(string $userId, int $limit = 20, int $offset = 0): array
    {
        return array_values(array_filter($this->orders, fn (Order $o) => $o->getUserId() === $userId));
    }

    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return array_values($this->orders);
    }

    public function countAll(array $filters = []): int
    {
        return count($this->orders);
    }

    public function getMetrics(): array
    {
        return ['total_revenue' => 0.0, 'total_orders' => count($this->orders), 'pending_orders' => 0, 'delivered_orders' => 0];
    }
}

final class InMemoryCartRepository implements CartRepositoryInterface
{
    /** @var array<string, Cart> */
    public array $carts = [];

    public function findByUserId(string $userId): ?Cart
    {
        foreach ($this->carts as $c) {
            if ($c->getUserId() === $userId) return $c;
        }
        return null;
    }

    public function findBySessionToken(string $sessionToken): ?Cart
    {
        foreach ($this->carts as $c) {
            if ($c->getSessionToken() === $sessionToken) return $c;
        }
        return null;
    }

    public function findById(string $id): ?Cart
    {
        return $this->carts[$id] ?? null;
    }

    public function save(Cart $cart): void
    {
        $this->carts[$cart->getId()] = $cart;
    }

    public function delete(string $id): void
    {
        unset($this->carts[$id]);
    }
}

final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var array<string, Product> */
    private array $products = [];
    /** @var array<string, ProductVariant> */
    private array $variants = [];

    public function addProduct(Product $p): void { $this->products[$p->getId()] = $p; }
    public function addVariant(ProductVariant $v): void { $this->variants[$v->getId()] = $v; }

    public function findVariantById(string $id): ?ProductVariant { return $this->variants[$id] ?? null; }
    public function findVariantBySku(string $sku): ?ProductVariant
    {
        $normalized = strtoupper(trim($sku));
        foreach ($this->variants as $v) {
            if (strtoupper(trim($v->getSku()->getValue())) === $normalized) {
                return $v;
            }
        }
        return null;
    }
    public function findById(string $id): ?Product { return $this->products[$id] ?? null; }
    public function saveVariant(ProductVariant $variant): void { $this->variants[$variant->getId()] = $variant; }

    public function save(Product $product): void {}
    public function delete(string $id): void {}
    public function findBySlug(string $slug): ?Product { return null; }
    public function findByCategory(string $categoryId, int $limit = 20, int $offset = 0): array { return []; }
    public function search(array $filters = [], int $limit = 20, int $offset = 0): array { return []; }
    public function countSearch(array $filters = []): int { return 0; }
    public function getFacets(): array { return []; }
    public function saveCategory(array $category): void {}
    public function deleteVariant(string $id): void {}
}

final class InMemoryUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    private array $users = [];

    public function addUser(User $u): void { $this->users[$u->getId()] = $u; }

    public function findById(string $id): ?User { return $this->users[$id] ?? null; }
    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $u) {
            if (strtolower($u->getEmail()) === strtolower($email)) return $u;
        }
        return null;
    }
    public function save(User $user): void { $this->users[$user->getId()] = $user; }
    public function delete(string $id): void { unset($this->users[$id]); }
    public function findAll(int $limit = 50, int $offset = 0): array { return array_values($this->users); }
    public function count(): int { return count($this->users); }
}
