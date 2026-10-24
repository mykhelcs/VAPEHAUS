<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Cart;
use Vapehaus\Domain\Model\CartItem;
use Vapehaus\Domain\Model\Product;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Role;
use Vapehaus\Domain\ValueObject\Sku;
use Vapehaus\Execution\Order\PlaceOrderExecution;
use Vapehaus\Tests\Mocks\InMemoryCartRepository;
use Vapehaus\Tests\Mocks\InMemoryOrderRepository;
use Vapehaus\Tests\Mocks\InMemoryProductRepository;
use Vapehaus\Tests\Mocks\InMemoryUserRepository;
use Vapehaus\Tests\TestCase;

final class PlaceOrderExecutionTest extends TestCase
{
    private InMemoryOrderRepository $orderRepo;
    private InMemoryCartRepository $cartRepo;
    private InMemoryProductRepository $productRepo;
    private InMemoryUserRepository $userRepo;
    private PlaceOrderExecution $execution;

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

        // Seed Adult User
        $dob = (new DateTimeImmutable('-23 years'))->format('Y-m-d');
        $user = new User(
            'usr_100',
            new Email('john@vapehaus.test'),
            'hash',
            'John',
            'Doe',
            '+639998887777',
            Role::customer(),
            $dob,
            true
        );
        $this->userRepo->save($user);

        // Seed Product and Variant
        $product = new Product('prd_10', 'cat_1', 'Geek Bar Pulse 15000', 'geek-bar-pulse', 'Geek Bar');
        $this->productRepo->save($product);

        $variant = new ProductVariant(
            'var_10',
            'prd_10',
            new Sku('GEEK-PLSE-MINT-50MG'),
            'Miami Mint',
            new NicotineStrength('50mg'),
            Money::fromFloat(650.00),
            10
        );
        $this->productRepo->saveVariant($variant);
    }

    public function testPlaceOrderSuccessfullyDeductsStockAndEmptiesCart(): void
    {
        $cart = new Cart('crt_100', 'usr_100', null, [
            new CartItem('cit_1', 'crt_100', 'var_10', 2),
        ]);
        $this->cartRepo->save($cart);

        $result = $this->execution->execute('usr_100', [
            'shipping_address' => 'Unit 402, Sunset Condominium, Cebu City',
            'payment_method' => 'cod',
        ]);

        $this->assertEquals('pending', $result['status']);
        $this->assertEquals(1300.0, $result['subtotal']);
        $this->assertEquals(50.0, $result['shipping_fee']);
        $this->assertEquals(1350.0, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Miami Mint', $result['items'][0]['flavor']);
        $this->assertEquals('50mg', $result['items'][0]['nicotine']);

        // Check stock was decremented from 10 to 8
        $updatedVariant = $this->productRepo->findVariantById('var_10');
        $this->assertEquals(8, $updatedVariant->getStockQuantity());

        // Check cart is now empty
        $updatedCart = $this->cartRepo->findByUserId('usr_100');
        $this->assertCount(0, $updatedCart->getItems());
    }

    public function testEmptyCartThrowsValidationException(): void
    {
        $cart = new Cart('crt_empty', 'usr_100', null, []);
        $this->cartRepo->save($cart);

        $this->expectException(ValidationException::class);
        $this->execution->execute('usr_100', [
            'shipping_address' => 'Sample Address',
        ]);
    }
}
