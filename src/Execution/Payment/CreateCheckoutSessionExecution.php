<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Payment;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Infrastructure\Payment\PayMongoGateway;

final class CreateCheckoutSessionExecution
{
    private PayMongoGateway $gateway;
    private OrderRepositoryInterface $orderRepo;

    public function __construct(
        PayMongoGateway $gateway,
        OrderRepositoryInterface $orderRepo
    ) {
        $this->gateway = $gateway;
        $this->orderRepo = $orderRepo;
    }

    /**
     * @param array<int, string> $paymentMethods
     * @return array{order_id: string, order_number: string, checkout_session_id: string, checkout_url: string, amount_centavos: int}
     */
    public function execute(
        string $orderId,
        ?string $successUrl = null,
        ?string $cancelUrl = null,
        array $paymentMethods = ['gcash', 'paymaya', 'card']
    ): array {
        if (trim($orderId) === '') {
            throw new ValidationException('Order ID is required to create a checkout session.');
        }

        $order = $this->orderRepo->findById($orderId);
        if ($order === null) {
            $order = $this->orderRepo->findByOrderNumber($orderId);
        }

        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        if ($order->getStatus()->getValue() === 'cancelled') {
            throw new ValidationException('Cannot create checkout session for cancelled order.');
        }

        if ($order->getStatus()->getValue() === 'paid') {
            throw new ValidationException('Order is already paid.');
        }

        $centavos = (int) round($order->getTotal()->toFloat() * 100);
        if ($centavos <= 0) {
            throw new ValidationException('Order total must be greater than zero for payment.');
        }

        // Prepare line items
        $lineItems = [];
        foreach ($order->getItems() as $item) {
            $flavor = $item->getFlavor();
            $itemName = $item->getProductName() . ($flavor !== '' ? " ({$flavor})" : '');
            $itemCentavos = (int) round($item->getPrice()->toFloat() * 100);

            $lineItems[] = [
                'currency' => 'PHP',
                'amount'   => $itemCentavos,
                'name'     => $itemName,
                'quantity' => $item->getQuantity(),
            ];
        }

        // Add shipping fee if applicable
        if ($order->getShippingFee()->toFloat() > 0) {
            $shippingCentavos = (int) round($order->getShippingFee()->toFloat() * 100);
            $courier = $order->getCourierName() ?: 'Standard Courier';
            $lineItems[] = [
                'currency' => 'PHP',
                'amount'   => $shippingCentavos,
                'name'     => "Shipping Fee ({$courier})",
                'quantity' => 1,
            ];
        }

        $appUrl = rtrim((string) (getenv('APP_URL') ?: 'http://localhost/vapehaus/public'), '/');
        $defaultSuccessUrl = $appUrl . '/index.html?order_id=' . urlencode($order->getId()) . '&status=paid';
        $defaultCancelUrl = $appUrl . '/index.html?order_id=' . urlencode($order->getId()) . '&status=cancelled';

        $resSuccessUrl = !empty($successUrl) ? $successUrl : $defaultSuccessUrl;
        $resCancelUrl = !empty($cancelUrl) ? $cancelUrl : $defaultCancelUrl;

        $description = "VAPEHAUS Order #{$order->getOrderNumber()}";
        $metadata = [
            'order_id'     => $order->getId(),
            'order_number' => $order->getOrderNumber(),
            'user_id'      => $order->getUserId(),
        ];

        $result = $this->gateway->createCheckoutSession(
            $centavos,
            $description,
            $lineItems,
            $metadata,
            $resSuccessUrl,
            $resCancelUrl,
            $paymentMethods
        );

        return [
            'order_id'            => $order->getId(),
            'order_number'        => $order->getOrderNumber(),
            'checkout_session_id' => $result['checkout_session_id'],
            'checkout_url'        => $result['checkout_url'],
            'amount_centavos'     => $centavos,
        ];
    }
}
