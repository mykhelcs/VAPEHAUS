<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Admin;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;

final class AdminDeleteProductExecution
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * @return array{id: string, name: string, deleted: bool}
     */
    public function execute(string $id): array
    {
        $product = $this->productRepository->findById($id);
        if ($product === null) {
            throw new EntityNotFoundException('Product', $id);
        }

        $this->productRepository->delete($id);

        return [
            'id' => $id,
            'name' => $product->getName(),
            'deleted' => true,
        ];
    }
}
