<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;
use Vapehaus\Domain\ValueObject\NicotineStrength;
use Vapehaus\Domain\ValueObject\Sku;

final class AdminCreateVariantExecution
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @param array{
     *     sku: string,
     *     flavor: string,
     *     nicotine_strength: string,
     *     price: float,
     *     stock_quantity?: int,
     *     is_active?: bool
     * } $data
     * @return array<string, mixed>
     */
    public function execute(string $productId, array $data): array
    {
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $productId);
        }

        $skuStr = $data['sku'] ?? '';
        $existing = $this->productRepository->findVariantBySku($skuStr);
        if ($existing !== null) {
            throw new ValidationException("SKU '{$skuStr}' already exists.");
        }

        $varId = 'var_' . bin2hex(random_bytes(6));
        $variant = new ProductVariant(
            $varId,
            $productId,
            new Sku($skuStr),
            $data['flavor'] ?? '',
            new NicotineStrength($data['nicotine_strength'] ?? '0mg'),
            Money::fromFloat((float) ($data['price'] ?? 0.0)),
            (int) ($data['stock_quantity'] ?? 0),
            (bool) ($data['is_active'] ?? true)
        );

        $this->productRepository->saveVariant($variant);
        return $variant->toArray();
    }
}
