<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Notification;

use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Model\User;

interface NotificationServiceInterface
{
    public function sendOrderConfirmation(Order $order, User $user): void;

    public function sendShippingUpdate(Order $order, string $trackingNumber, string $courier): void;

    public function sendRestockAlert(User $user, ProductVariant $variant): void;

    public function sendKycStatusUpdate(User $user, string $status, ?string $reason = null): void;
}
