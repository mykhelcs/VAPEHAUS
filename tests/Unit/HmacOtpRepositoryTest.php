<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

use Vapehaus\Infrastructure\Repository\HmacOtpRepository;
use Vapehaus\Tests\TestCase;

final class HmacOtpRepositoryTest extends TestCase
{
    private HmacOtpRepository $repository;
    private string $secret = 'test-secret-key-12345';

    protected function setUp(): void
    {
        $this->repository = new HmacOtpRepository($this->secret);
    }

    public function testCreateOtpGeneratesSignedToken(): void
    {
        $email = 'user@example.com';
        $otp = '123456';
        $this->repository->createOtp($email, $otp, 'registration', 10);

        $token = $this->repository->getLastToken();
        $this->assertNotNull($token);
        $this->assertTrue(strlen($token) > 20);

        // Verify with the generated token
        $this->repository->setProofToken($token);
        $verified = $this->repository->verifyAndConsumeOtp($email, $otp, 'registration');
        $this->assertTrue($verified);
    }

    public function testVerifyFailsWithWrongOtp(): void
    {
        $email = 'user@example.com';
        $this->repository->createOtp($email, '123456', 'registration', 10);
        $token = $this->repository->getLastToken();

        $this->repository->setProofToken($token);
        $verified = $this->repository->verifyAndConsumeOtp($email, '999999', 'registration');
        $this->assertFalse($verified);
    }

    public function testVerifyFailsWithDifferentEmail(): void
    {
        $this->repository->createOtp('user@example.com', '123456', 'registration', 10);
        $token = $this->repository->getLastToken();

        $this->repository->setProofToken($token);
        $verified = $this->repository->verifyAndConsumeOtp('other@example.com', '123456', 'registration');
        $this->assertFalse($verified);
    }

    public function testVerifyFailsWithDifferentPurpose(): void
    {
        $email = 'user@example.com';
        $this->repository->createOtp($email, '123456', 'registration', 10);
        $token = $this->repository->getLastToken();

        $this->repository->setProofToken($token);
        $verified = $this->repository->verifyAndConsumeOtp($email, '123456', 'password_reset');
        $this->assertFalse($verified);
    }

    public function testProofTokenLifecycle(): void
    {
        $email = 'user@example.com';
        $proof = $this->repository->buildProofToken($email, 'registration');
        $this->assertNotNull($proof);

        $this->repository->setProofToken($proof);
        $this->assertTrue($this->repository->isOtpVerified($email, 'registration'));

        // Wrong email should fail proof
        $this->assertFalse($this->repository->isOtpVerified('wrong@example.com', 'registration'));

        // Wrong purpose should fail proof
        $this->assertFalse($this->repository->isOtpVerified($email, 'password_reset'));
    }

    public function testVerifyDirectlyByEmailAndOtpWithoutToken(): void
    {
        $email = 'direct@example.com';
        $otp = '654321';
        $this->repository->createOtp($email, $otp, 'registration', 10);

        // Verification without setting any proofToken must succeed
        $this->repository->setProofToken(null);
        $verified = $this->repository->verifyAndConsumeOtp($email, $otp, 'registration');
        $this->assertTrue($verified);

        // Second attempt must fail because OTP was consumed
        $reused = $this->repository->verifyAndConsumeOtp($email, $otp, 'registration');
        $this->assertFalse($reused);

        // After consumption, isOtpVerified must return true within window
        $this->assertTrue($this->repository->isOtpVerified($email, 'registration'));
    }

    public function testTamperedTokenIsRejected(): void
    {
        $email = 'user@example.com';
        $this->repository->createOtp($email, '123456', 'registration', 10);
        $token = $this->repository->getLastToken();

        // Tamper with the token
        $tampered = base64_encode(base64_decode($token) . 'tampered');
        $this->repository->setProofToken($tampered);

        $this->assertFalse($this->repository->verifyAndConsumeOtp($email, '123456', 'registration'));
    }
}
