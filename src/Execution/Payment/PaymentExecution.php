<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Payment;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\InventoryAuditLog;
use Vapehaus\Domain\Model\PaymentTransaction;
use Vapehaus\Domain\Repository\InventoryAuditRepositoryInterface;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Domain\Repository\PaymentTransactionRepositoryInterface;
use Vapehaus\Domain\Repository\ProductRepositoryInterface;
use Vapehaus\Domain\ValueObject\Money;

final class PaymentExecution
{
    private PaymentTransactionRepositoryInterface $paymentRepo;
    private OrderRepositoryInterface $orderRepo;
    private ProductRepositoryInterface $productRepo;
    private ?InventoryAuditRepositoryInterface $auditRepo;
    private string $webhookSecret;

    public function __construct(
        PaymentTransactionRepositoryInterface $paymentRepo,
        OrderRepositoryInterface $orderRepo,
        ProductRepositoryInterface $productRepo,
        ?InventoryAuditRepositoryInterface $auditRepo = null,
        string $webhookSecret = 'whsec_vapehaus_secret_2026'
    ) {
        $this->paymentRepo = $paymentRepo;
        $this->orderRepo = $orderRepo;
        $this->productRepo = $productRepo;
        $this->auditRepo = $auditRepo;
        $this->webhookSecret = $webhookSecret;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload, ?string $signatureHeader = null): array
    {
        $eventType = $payload['event'] ?? $payload['type'] ?? 'payment.succeeded';
        $data = $payload['data'] ?? $payload;

        $orderId = $data['order_id'] ?? $data['metadata']['order_id'] ?? null;
        $reference = $data['transaction_reference'] ?? $data['id'] ?? ('tx_' . bin2hex(random_bytes(8)));
        $amount = (float) ($data['amount'] ?? 0);
        $gateway = $payload['gateway'] ?? 'paymongo';

        if ($orderId === null) {
            throw new ValidationException('Webhook payload missing target order_id.');
        }

        $order = $this->orderRepo->findById((string) $orderId);
        if ($order === null) {
            $order = $this->orderRepo->findByOrderNumber((string) $orderId);
        }

        if ($order === null) {
            throw new EntityNotFoundException('Order', (string) $orderId);
        }

        // Idempotency: If transaction was already processed, return existing
        $existingTx = $this->paymentRepo->findByReference($reference);
        if ($existingTx !== null) {
            return [
                'status' => 'already_processed',
                'transaction' => $existingTx->toArray(),
                'order' => $order->toArray(),
            ];
        }

        $txId = 'ptx_' . bin2hex(random_bytes(6));
        $transaction = new PaymentTransaction(
            $txId,
            $order->getId(),
            $gateway,
            $reference,
            Money::fromFloat($amount > 0 ? $amount : $order->getTotal()->toFloat()),
            PaymentTransaction::STATUS_SUCCEEDED,
            $payload
        );

        $this->paymentRepo->save($transaction);

        // Update order status to paid
        if ($order->getStatus()->getValue() === 'pending') {
            $order->markAsPaid();
            $this->orderRepo->save($order);
        }

        return [
            'status' => 'success',
            'transaction' => $transaction->toArray(),
            'order' => $order->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminRefund(string $orderId, ?string $reason = null, ?string $actorId = null): array
    {
        $order = $this->orderRepo->findById($orderId);
        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        $order->markAsRefunded();
        $this->orderRepo->save($order);

        // Return inventory
        foreach ($order->getItems() as $item) {
            $variant = $this->productRepo->findVariantById($item->getVariantId());
            if ($variant !== null) {
                $prev = $variant->getStockQuantity();
                $variant->incrementStock($item->getQuantity());
                $new = $variant->getStockQuantity();
                $this->productRepo->saveVariant($variant);

                if ($this->auditRepo !== null) {
                    $this->auditRepo->log(new InventoryAuditLog(
                        'log_' . bin2hex(random_bytes(6)),
                        $variant->getId(),
                        $prev,
                        $new,
                        $item->getQuantity(),
                        "Order #{$order->getOrderNumber()} refund: " . ($reason ?? 'Customer refund'),
                        $actorId
                    ));
                }
            }
        }

        return $order->toArray();
    }
}
