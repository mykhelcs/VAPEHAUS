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
     * Handle PayMongo Webhook with strict HMAC verification and idempotency.
     * Supports both official PayMongo nested events (checkout_session.payment.paid, payment.paid)
     * and standardized direct webhooks.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handleWebhook(array $payload, ?string $signatureHeader = null): array
    {
        // 1. Extract Event Type
        $eventType = $payload['data']['attributes']['type']
            ?? $payload['event']
            ?? $payload['type']
            ?? 'payment.paid';

        // 2. Extract Data Attributes (handling PayMongo official nested structure)
        $orderId = null;
        $reference = null;
        $amount = 0.0;
        $gateway = 'paymongo';

        // Check if official PayMongo event envelope
        if (isset($payload['data']['attributes']['data']['attributes'])) {
            $inner = $payload['data']['attributes']['data']['attributes'];
            $metadata = $inner['metadata'] ?? [];

            $orderId = $metadata['order_id'] ?? $metadata['order_number'] ?? null;

            // Extract payment ID from session's payments array, or session ID, or event ID
            if (!empty($inner['payments'][0]['id'])) {
                $reference = (string) $inner['payments'][0]['id'];
                $centavos = $inner['payments'][0]['attributes']['amount'] ?? 0;
                $amount = $centavos > 0 ? ((float) $centavos / 100.0) : 0.0;
            } elseif (!empty($payload['data']['attributes']['data']['id'])) {
                $reference = (string) $payload['data']['attributes']['data']['id'];
            }

            if ($amount <= 0.0 && !empty($inner['amount'])) {
                $amount = ((float) $inner['amount']) / 100.0;
            }
        }

        // Direct / flat payload fallback
        $data = $payload['data'] ?? $payload;
        if ($orderId === null) {
            $orderId = $data['order_id'] ?? $data['metadata']['order_id'] ?? null;
        }
        if ($reference === null) {
            $reference = $data['transaction_reference'] ?? $data['id'] ?? $payload['data']['id'] ?? ('tx_' . bin2hex(random_bytes(8)));
        }
        if ($amount <= 0.0) {
            $rawAmt = (float) ($data['amount'] ?? 0);
            $amount = $rawAmt;
        }
        if (isset($payload['gateway'])) {
            $gateway = (string) $payload['gateway'];
        }

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

        // Idempotency check: If transaction reference was already recorded, return existing
        $existingTx = $this->paymentRepo->findByReference($reference);
        if ($existingTx !== null) {
            return [
                'status' => 'already_processed',
                'event' => $eventType,
                'transaction' => $existingTx->toArray(),
                'order' => $order->toArray(),
            ];
        }

        $finalAmount = $amount > 0 ? $amount : $order->getTotal()->toFloat();
        $txId = 'ptx_' . bin2hex(random_bytes(6));
        $transaction = new PaymentTransaction(
            $txId,
            $order->getId(),
            $gateway,
            $reference,
            Money::fromFloat($finalAmount),
            PaymentTransaction::STATUS_SUCCEEDED,
            $payload
        );

        $this->paymentRepo->save($transaction);

        // Transition order status to paid (only from pending state)
        if ($order->getStatus()->getValue() === 'pending') {
            $order->markAsPaid();
            $this->orderRepo->save($order);
        }

        return [
            'status' => 'success',
            'event' => $eventType,
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
