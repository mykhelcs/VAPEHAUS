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
     *     id_type?: string,
     *     idType?: string,
     *     id_number?: string,
     *     idNumber?: string,
     *     front_image_url?: string,
     *     frontImageUrl?: string,
     *     back_image_url?: ?string,
     *     backImageUrl?: ?string
     * } $data
     * @return array<string, mixed>
     */
    public function submit(string $userId, array $data): array
    {
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            throw new EntityNotFoundException('User', $userId);
        }

        if ($user->isAgeVerified()) {
            throw new ValidationException('Your government ID is already verified (18+ compliant).');
        }

        $idType = (string) ($data['id_type'] ?? $data['idType'] ?? KycVerification::TYPE_DRIVERS_LICENSE);
        $idNumber = (string) ($data['id_number'] ?? $data['idNumber'] ?? '');
        $frontImageUrl = (string) ($data['front_image_url'] ?? $data['frontImageUrl'] ?? '');
        $backImageUrl = $data['back_image_url'] ?? $data['backImageUrl'] ?? null;

        // Check if there is an existing pending submission; update it to prevent duplicate queue flooding
        $latest = $this->kycRepo->findLatestByUserId($userId);
        if ($latest !== null && $latest->getStatus() === KycVerification::STATUS_PENDING) {
            $latest->updateSubmission(
                $idType,
                $idNumber,
                $frontImageUrl,
                $backImageUrl ? (string) $backImageUrl : null
            );
            $this->kycRepo->save($latest);
            return $latest->toArray();
        }

        $id = 'kyc_' . bin2hex(random_bytes(6));
        $kyc = new KycVerification(
            $id,
            $userId,
            $idType,
            $idNumber,
            $frontImageUrl,
            $backImageUrl ? (string) $backImageUrl : null,
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
        $result = [];

        foreach ($kycs as $k) {
            $item = $k->toArray();
            $user = $this->userRepo->findById($k->getUserId());
            if ($user !== null) {
                $item['customer_name'] = $user->getFullName();
                $item['customer_email'] = $user->getEmail()->getValue();
                $item['customer_phone'] = $user->getPhone();
                $item['birth_date'] = $user->getBirthDate();
                $item['is_age_verified'] = $user->isAgeVerified();
            } else {
                $item['customer_name'] = 'Customer';
                $item['customer_email'] = '';
                $item['customer_phone'] = null;
                $item['birth_date'] = null;
                $item['is_age_verified'] = false;
            }
            $result[] = $item;
        }

        return $result;
    }
}
