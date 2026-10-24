<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Cart;

use Vapehaus\Domain\Model\Cart;
use Vapehaus\Domain\Repository\CartRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class GetCartExecution
{
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        ProductRepositoryInterface $productRepository
    ) {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(?string $userId, ?string $sessionToken = null): array
    {
        $cart = null;
        if ($userId !== null) {
            $cart = $this->cartRepository->findByUserId($userId);
        } elseif ($sessionToken !== null) {
            $cart = $this->cartRepository->findBySessionToken($sessionToken);
        }

        if ($cart === null) {
            $cartId = 'crt_' . bin2hex(random_bytes(8));
            $cart = new Cart($cartId, $userId, $sessionToken, []);
            $this->cartRepository->save($cart);
        }

        // Attach fresh variant details and check stock
        $warnings = [];
        foreach ($cart->getItems() as $item) {
            $variant = $this->productRepository->findVariantById($item->getVariantId());
            if ($variant !== null) {
                $item->attachVariant($variant);
                if (!$variant->hasSufficientStock($item->getQuantity())) {
                    $warnings[] = sprintf("SKU %s only has %d items left in stock.", $variant->getSku()->getValue(), $variant->getStockQuantity());
                }
            }
        }

        $result = $cart->toArray();
        $result['warnings'] = $warnings;
        return $result;
    }
}
