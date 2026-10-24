<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Rma;

use Vapehaus\Domain\Exception\AuthenticationException;
use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Model\ReturnRequest;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\Repository\ReturnRepositoryInterface;

final class RmaExecution
{
    private ReturnRepositoryInterface $returnRepo;
    private OrderRepositoryInterface $orderRepo;
    private ProductRepositoryInterface $productRepo;

    public function __construct(
        ReturnRepositoryInterface $returnRepo,
        OrderRepositoryInterface $orderRepo,
        ProductRepositoryInterface $productRepo
    ) {
        $this->returnRepo = $returnRepo;
        $this->orderRepo = $orderRepo;
        $this->productRepo = $productRepo;
    }

    /**
     * @param array{
     *     order_id: string,
     *     variant_id: string,
     *     reason: string,
     *     description: string,
     *     photo_url?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function create(string $userId, array $data): array
    {
        $orderId = $data['order_id'] ?? '';
        $order = $this->orderRepo->findById($orderId);
        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        if ($order->getUserId() !== $userId) {
            throw new AuthenticationException('You can only file returns on your own orders.');
        }

        $variantId = $data['variant_id'] ?? '';
        $variant = $this->productRepo->findVariantById($variantId);
        if ($variant === null) {
            throw new EntityNotFoundException('ProductVariant', $variantId);
        }

        $id = 'rma_' . bin2hex(random_bytes(6));
        $rma = new ReturnRequest(
            $id,
            $orderId,
            $userId,
            $variantId,
            $data['reason'] ?? ReturnRequest::REASON_DOA,
            $data['description'] ?? '',
            $data['photo_url'] ?? null,
            ReturnRequest::STATUS_REQUESTED
        );

        $this->returnRepo->save($rma);
        return $rma->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listUserReturns(string $userId): array
    {
        $returns = $this->returnRepo->findByUserId($userId);
        return array_map(fn (ReturnRequest $r) => $r->toArray(), $returns);
    }

    /**
     * @return array<string, mixed>
     */
    public function adminUpdateStatus(string $rmaId, string $status, ?string $notes = null): array
    {
        $rma = $this->returnRepo->findById($rmaId);
        if ($rma === null) {
            throw new EntityNotFoundException('ReturnRequest', $rmaId);
        }

        $rma->updateStatus($status, $notes);
        $this->returnRepo->save($rma);
        return $rma->toArray();
    }

    /**
     * @param array{status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function adminList(array $filters = []): array
    {
        $returns = $this->returnRepo->findAll($filters);
        return array_map(fn (ReturnRequest $r) => $r->toArray(), $returns);
    }
}
