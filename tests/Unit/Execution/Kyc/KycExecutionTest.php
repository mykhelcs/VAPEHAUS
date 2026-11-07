<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution\Kyc;

use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\KycVerification;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\Repository\KycRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Domain\ValueObject\Role;
use Vapehaus\Execution\Kyc\KycExecution;
use Vapehaus\Tests\TestCase;

class InMemoryKycRepository implements KycRepositoryInterface
{
    /** @var array<string, KycVerification> */
    public array $records = [];

    public function save(KycVerification $kyc): void
    {
        $this->records[$kyc->getId()] = $kyc;
    }

    public function findById(string $id): ?KycVerification
    {
        return $this->records[$id] ?? null;
    }

    public function findLatestByUserId(string $userId): ?KycVerification
    {
        $userRecords = array_filter($this->records, fn ($k) => $k->getUserId() === $userId);
        if (empty($userRecords)) {
            return null;
        }
        usort($userRecords, fn ($a, $b) => strcmp($b->getCreatedAt(), $a->getCreatedAt()));
        return reset($userRecords);
    }

    public function findAll(array $filters = []): array
    {
        $res = array_values($this->records);
        if (!empty($filters['status'])) {
            $res = array_filter($res, fn ($k) => $k->getStatus() === $filters['status']);
        }
        return array_values($res);
    }
}

class InMemoryKycUserRepository implements UserRepositoryInterface
{
    /** @var array<string, User> */
    public array $users = [];

    public function save(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }

    public function findById(string $id): ?User
    {
        return $this->users[$id] ?? null;
    }

    public function findByEmail(string $email): ?User
    {
        foreach ($this->users as $u) {
            if ($u->getEmail()->getValue() === strtolower($email)) {
                return $u;
            }
        }
        return null;
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        return array_slice(array_values($this->users), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->users);
    }
}

final class KycExecutionTest extends TestCase
{
    private InMemoryKycRepository $kycRepo;
    private InMemoryKycUserRepository $userRepo;
    private KycExecution $execution;
    private User $customer;

    protected function setUp(): void
    {
        $this->kycRepo = new InMemoryKycRepository();
        $this->userRepo = new InMemoryKycUserRepository();
        $this->execution = new KycExecution($this->kycRepo, $this->userRepo);

        $this->customer = new User(
            'usr_kyc_cust_01',
            new Email('kyccustomer@test.ph'),
            'hash_pass',
            'Cardo',
            'Dalisay',
            '+639171112222',
            Role::customer(),
            '1995-10-20',
            false // initially not KYC verified
        );
        $this->userRepo->save($this->customer);
    }

    public function testSubmitRejectsInvalidIdType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid ID type');

        $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => 'student_id_card',
            'id_number' => '12345678',
            'front_image_url' => 'https://vapehaus.ph/uploads/kyc/front.jpg',
        ]);
    }

    public function testSubmitRejectsBlankIdNumber(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ID Number is required');

        $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => KycVerification::TYPE_DRIVERS_LICENSE,
            'id_number' => '   ',
            'front_image_url' => 'https://vapehaus.ph/uploads/kyc/front.jpg',
        ]);
    }

    public function testSubmitRejectsUnsafeFrontImageUrl(): void
    {
        $this->expectException(ValidationException::class);

        $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => KycVerification::TYPE_PASSPORT,
            'id_number' => 'P1234567A',
            'front_image_url' => 'javascript:alert(1)',
        ]);
    }

    public function testSubmitRejectsIfUserAlreadyVerified(): void
    {
        $this->customer->markAgeVerified(18);
        $this->userRepo->save($this->customer);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('already verified');

        $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => KycVerification::TYPE_NATIONAL_ID,
            'id_number' => 'PH-9876-5432',
            'front_image_url' => 'https://vapehaus.ph/uploads/kyc/front.jpg',
        ]);
    }

    public function testSubmitSuccessAndUpdatesExistingPending(): void
    {
        $first = $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => KycVerification::TYPE_DRIVERS_LICENSE,
            'id_number' => 'N01-22-111111',
            'front_image_url' => 'https://vapehaus.ph/uploads/kyc/front1.jpg',
        ]);

        $this->assertEquals(KycVerification::STATUS_PENDING, $first['status']);
        $this->assertEquals('N01-22-111111', $first['id_number']);

        // Submitting again while pending should update the pending record without creating a duplicate
        $second = $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => KycVerification::TYPE_DRIVERS_LICENSE,
            'id_number' => 'N01-22-222222',
            'front_image_url' => 'https://vapehaus.ph/uploads/kyc/front2.jpg',
        ]);

        $this->assertEquals($first['id'], $second['id']);
        $this->assertEquals('N01-22-222222', $second['id_number']);
        $this->assertEquals(1, count($this->kycRepo->records));
    }

    public function testAdminReviewApproveMarksUserAgeVerified(): void
    {
        $sub = $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => KycVerification::TYPE_UMID,
            'id_number' => 'CRN-0111-2222-3333',
            'front_image_url' => 'https://vapehaus.ph/uploads/kyc/umid_front.jpg',
        ]);

        $reviewed = $this->execution->adminReview($sub['id'], [
            'decision' => 'approved',
        ]);

        $this->assertEquals(KycVerification::STATUS_APPROVED, $reviewed['status']);
        $this->assertNotNull($reviewed['verified_at']);

        // Check user model
        $user = $this->userRepo->findById('usr_kyc_cust_01');
        $this->assertNotNull($user);
        $this->assertTrue($user->isAgeVerified());
    }

    public function testAdminReviewRejectStoresReason(): void
    {
        $sub = $this->execution->submit('usr_kyc_cust_01', [
            'id_type' => KycVerification::TYPE_POSTAL_ID,
            'id_number' => 'POSTAL-999',
            'front_image_url' => 'https://vapehaus.ph/uploads/kyc/postal_front.jpg',
        ]);

        $reviewed = $this->execution->adminReview($sub['id'], [
            'decision' => 'rejected',
            'reason' => 'Image blurry, birth date unreadable.',
        ]);

        $this->assertEquals(KycVerification::STATUS_REJECTED, $reviewed['status']);
        $this->assertEquals('Image blurry, birth date unreadable.', $reviewed['rejection_reason']);

        $user = $this->userRepo->findById('usr_kyc_cust_01');
        $this->assertNotNull($user);
        $this->assertFalse($user->isAgeVerified());
    }
}
