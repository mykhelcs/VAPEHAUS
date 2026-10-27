<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Payment;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Infrastructure\Payment\PayMongoGateway;

final class CreatePaymentIntentExecution
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
     * @return array{order_id: string, payment_intent_id: string, client_key: string, amount_centavos: int}
     */
    public function execute(string $orderId): array
    {
        if (trim($orderId) === '') {
            throw new ValidationException('Order ID is required to create a payment intent.');
        }

        $order = $this->orderRepo->findById($orderId);
        if ($order === null) {
            $order = $this->orderRepo->findByOrderNumber($orderId);
        }

        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        if ($order->getStatus()->getValue() === 'cancelled') {
            throw new ValidationException('Cannot create payment intent for cancelled order.');
        }

        $centavos = (int) round($order->getTotal()->toFloat() * 100);
        if ($centavos <= 0) {
            throw new ValidationException('Order total must be greater than zero for payment.');
        }

        $result = $this->gateway->createPaymentIntent(
            $centavos,
            "VAPEHAUS Order #{$order->getOrderNumber()}",
            [
                'order_id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
            ]
        );

        return [
            'order_id' => $order->getId(),
            'payment_intent_id' => $result['payment_intent_id'],
            'client_key' => $result['client_key'],
            'amount_centavos' => $centavos,
        ];
    }
}
