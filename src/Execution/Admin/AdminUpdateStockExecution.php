<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class AdminUpdateStockExecution
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @param array{
     *     adjustment_type: 'set'|'increment'|'decrement',
     *     amount: int
     * } $data
     * @return array<string, mixed>
     */
    public function execute(string $variantId, array $data): array
    {
        $variant = $this->productRepository->findVariantById($variantId);
        if ($variant === null) {
            throw new EntityNotFoundException('ProductVariant', $variantId);
        }

        $type = $data['adjustment_type'] ?? 'set';
        $amount = (int) ($data['amount'] ?? 0);

        if ($type === 'increment') {
            $variant->incrementStock($amount);
        } elseif ($type === 'decrement') {
            $variant->decrementStock($amount);
        } elseif ($type === 'set') {
            if ($amount < 0) {
                throw new ValidationException('Stock cannot be set to a negative number.');
            }
            $variant->updateDetails(
                $variant->getFlavor(),
                $variant->getNicotineStrength(),
                $variant->getPrice(),
                $amount,
                $variant->isActive()
            );
        } else {
            throw new ValidationException("Invalid adjustment type: {$type}. Allowed: set, increment, decrement.");
        }

        $this->productRepository->saveVariant($variant);

        return $variant->toArray();
    }
}
