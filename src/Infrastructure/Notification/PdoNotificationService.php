<?php

declare(strict_types=1);

namespace Vapehaus\Infrastructure\Notification;

use PDO;
use Vapehaus\Domain\Model\Order;
use Vapehaus\Domain\Model\ProductVariant;
use Vapehaus\Domain\Model\User;

final class PdoNotificationService implements NotificationServiceInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function sendOrderConfirmation(Order $order, User $user): void
    {
        $this->log(
            $user->getEmail()->getValue(),
            'email',
            "Order Confirmed: #{$order->getOrderNumber()}",
            "Thank you for your order, {$user->getFirstName()}! Total: PHP " . number_format($order->getTotal()->toFloat(), 2)
        );
    }

    public function sendShippingUpdate(Order $order, string $trackingNumber, string $courier): void
    {
        $this->log(
            $order->getUserId(),
            'sms',
            "Vapehaus Order Shipped",
            "Your order #{$order->getOrderNumber()} has been shipped via {$courier}. Tracking #: {$trackingNumber}"
        );
    }

    public function sendRestockAlert(User $user, ProductVariant $variant): void
    {
        $this->log(
            $user->getEmail()->getValue(),
            'email',
            "Back in Stock: {$variant->getFlavor()} ({$variant->getNicotineStrength()->getValue()})",
            "Good news! The item you were waiting for is back in stock at Vapehaus."
        );
    }

    public function sendKycStatusUpdate(User $user, string $status, ?string $reason = null): void
    {
        $subject = ($status === 'approved') ? 'Vape Age Verification Approved' : 'Action Required: Age Verification';
        $body = ($status === 'approved')
            ? "Your 18+ government ID has been verified. You may now purchase vape products."
            : "Your verification was not approved. Reason: {$reason}";

        $this->log($user->getEmail()->getValue(), 'email', $subject, $body);
    }

    private function log(string $recipient, string $channel, string $subject, string $body): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO notifications_log (id, recipient, channel, subject, body, sent_at)
            VALUES (:id, :to, :ch, :sub, :body, :sent_at)');
        $stmt->execute([
            ':id' => 'notif_' . bin2hex(random_bytes(6)),
            ':to' => $recipient,
            ':ch' => $channel,
            ':sub' => $subject,
            ':body' => $body,
            ':sent_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
