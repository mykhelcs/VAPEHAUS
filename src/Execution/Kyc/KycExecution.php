<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Kyc;

use Vapehaus\Domain\Exception\EntityNotFoundException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\KycVerification;
use Vapehaus\Domain\Repository\KycRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Infrastructure\Notification\NotificationServiceInterface;

final class KycExecution
{
    private KycRepositoryInterface $kycRepo;
    private UserRepositoryInterface $userRepo;
    private ?NotificationServiceInterface $notifService;

    public function __construct(
        KycRepositoryInterface $kycRepo,
        UserRepositoryInterface $userRepo,
        ?NotificationServiceInterface $notifService = null
    ) {
        $this->kycRepo = $kycRepo;
        $this->userRepo = $userRepo;
        $this->notifService = $notifService;
    }

    /**
     * @param array{
     *     id_type: string,
     *     id_number: string,
     *     front_image_url: string,
     *     back_image_url?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function submit(string $userId, array $data): array
    {
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            throw new EntityNotFoundException('User', $userId);
        }

        $id = 'kyc_' . bin2hex(random_bytes(6));
        $kyc = new KycVerification(
            $id,
            $userId,
            $data['id_type'] ?? 'drivers_license',
            $data['id_number'] ?? '',
            $data['front_image_url'] ?? '',
            $data['back_image_url'] ?? null,
            KycVerification::STATUS_PENDING
        );

        $this->kycRepo->save($kyc);
        return $kyc->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatus(string $userId): ?array
    {
        $kyc = $this->kycRepo->findLatestByUserId($userId);
        return $kyc?->toArray();
    }

    /**
     * @param array{decision: 'approved'|'rejected', reason?: ?string} $data
     * @return array<string, mixed>
     */
    public function adminReview(string $kycId, array $data): array
    {
        $kyc = $this->kycRepo->findById($kycId);
        if ($kyc === null) {
            throw new EntityNotFoundException('KycVerification', $kycId);
        }

        $decision = strtolower(trim($data['decision'] ?? ''));
        $user = $this->userRepo->findById($kyc->getUserId());

        if ($decision === 'approved') {
            $kyc->approve();
            if ($user !== null) {
                $user->markAgeVerified(18);
                $this->userRepo->save($user);
            }
        } elseif ($decision === 'rejected') {
            $reason = trim($data['reason'] ?? 'Document unreadable or invalid.');
            $kyc->reject($reason);
        } else {
            throw new ValidationException("Decision must be 'approved' or 'rejected'.");
        }

        $this->kycRepo->save($kyc);

        if ($this->notifService !== null && $user !== null) {
            $this->notifService->sendKycStatusUpdate($user, $decision, $data['reason'] ?? null);
        }

        return $kyc->toArray();
    }

    /**
     * @param array{status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function adminList(array $filters = []): array
    {
        $kycs = $this->kycRepo->findAll($filters);
        return array_map(fn (KycVerification $k) => $k->toArray(), $kycs);
    }
}
