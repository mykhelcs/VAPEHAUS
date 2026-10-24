<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Order;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\OrderRepositoryInterface;
use Vapehaus\Infrastructure\Notification\NotificationServiceInterface;

final class AdminShipOrderExecution
{
    private OrderRepositoryInterface $orderRepo;
    private ?NotificationServiceInterface $notifService;

    public function __construct(
        OrderRepositoryInterface $orderRepo,
        ?NotificationServiceInterface $notifService = null
    ) {
        $this->orderRepo = $orderRepo;
        $this->notifService = $notifService;
    }

    /**
     * @param array{
     *     tracking_number: string,
     *     courier_name: string,
     *     tracking_url?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function execute(string $orderId, array $data): array
    {
        $order = $this->orderRepo->findById($orderId);
        if ($order === null) {
            throw new EntityNotFoundException('Order', $orderId);
        }

        $trackingNumber = trim($data['tracking_number'] ?? '');
        $courierName = trim($data['courier_name'] ?? '');

        if ($trackingNumber === '') {
            throw new ValidationException('Tracking number is required.');
        }
        if ($courierName === '') {
            throw new ValidationException('Courier name is required (e.g. J&T Express, Lalamove, Ninja Van).');
        }

        $order->setTracking($trackingNumber, $courierName, $data['tracking_url'] ?? null);
        $this->orderRepo->save($order);

        if ($this->notifService !== null) {
            $this->notifService->sendShippingUpdate($order, $trackingNumber, $courierName);
        }

        return $order->toArray();
    }
}
