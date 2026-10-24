<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit\Execution;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\UnderageException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Execution\Auth\RegisterUserExecution;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Infrastructure\Security\PasswordHasher;
use Vapehaus\Tests\Mocks\InMemoryUserRepository;
use Vapehaus\Tests\TestCase;

final class RegisterUserExecutionTest extends TestCase
{
    private InMemoryUserRepository $userRepo;
    private PasswordHasher $hasher;
    private JwtTokenService $tokenService;
    private RegisterUserExecution $execution;

    protected function setUp(): void
    {
        $this->userRepo = new InMemoryUserRepository();
        $this->hasher = new PasswordHasher();
        $this->tokenService = new JwtTokenService('test_secret');
        $this->execution = new RegisterUserExecution($this->userRepo, $this->hasher, $this->tokenService, 18);
    }

    public function testRegisterSuccessfulAdultCustomer(): void
    {
        $dob = (new DateTimeImmutable('-20 years'))->format('Y-m-d');
        $result = $this->execution->execute([
            'email' => 'customer@vapehaus.test',
            'password' => 'secret123',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'phone' => '+639181112222',
            'birth_date' => $dob,
            'is_age_verified' => true,
        ]);

        $this->assertNotNull($result['token']);
        $this->assertEquals('customer@vapehaus.test', $result['user']['email']);
        $this->assertEquals('customer', $result['user']['role']);
        $this->assertTrue($result['user']['is_age_verified']);
    }

    public function testRegisterUnderageThrowsUnderageException(): void
    {
        $dob = (new DateTimeImmutable('-15 years'))->format('Y-m-d');
        $this->expectException(UnderageException::class);

        $this->execution->execute([
            'email' => 'teen@vapehaus.test',
            'password' => 'secret123',
            'first_name' => 'Young',
            'last_name' => 'Boy',
            'birth_date' => $dob,
            'is_age_verified' => true,
        ]);
    }

    public function testDuplicateEmailThrowsValidationException(): void
    {
        $dob = (new DateTimeImmutable('-25 years'))->format('Y-m-d');
        $this->execution->execute([
            'email' => 'duplicate@vapehaus.test',
            'password' => 'secret123',
            'first_name' => 'First',
            'last_name' => 'User',
            'birth_date' => $dob,
        ]);

        $this->expectException(ValidationException::class);
        $this->execution->execute([
            'email' => 'duplicate@vapehaus.test',
            'password' => 'secret456',
            'first_name' => 'Second',
            'last_name' => 'User',
            'birth_date' => $dob,
        ]);
    }
}
