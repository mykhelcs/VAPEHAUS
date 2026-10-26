<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use DateTimeImmutable;
use Vapehaus\Domain\Exception\UnderageException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Session;
use Vapehaus\Domain\Model\User;
use Vapehaus\Domain\Repository\SessionRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Domain\ValueObject\Email;
use Vapehaus\Domain\ValueObject\Role;
use Vapehaus\Infrastructure\Security\PasswordHasherInterface;
use Vapehaus\Infrastructure\Security\TokenServiceInterface;

final class RegisterUserExecution
{
    private UserRepositoryInterface $userRepository;
    private PasswordHasherInterface $passwordHasher;
    private TokenServiceInterface $tokenService;
    private int $minimumAge;
    private ?SessionRepositoryInterface $sessionRepository;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher,
        TokenServiceInterface $tokenService,
        int $minimumAge = 18,
        ?SessionRepositoryInterface $sessionRepository = null
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenService = $tokenService;
        $this->minimumAge = $minimumAge;
        $this->sessionRepository = $sessionRepository;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{user: array<string, mixed>, token: string}
     */
    public function execute(array $data): array
    {
        $emailStr = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $firstName = trim((string) ($data['first_name'] ?? $data['firstName'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? $data['lastName'] ?? ''));
        $birthDate = trim((string) ($data['birth_date'] ?? $data['birthDate'] ?? ''));
        $isAgeVerified = (bool) ($data['is_age_verified'] ?? $data['isAgeAttested'] ?? false);

        $errors = [];
        if ($emailStr === '') {
            $errors[] = ['field' => 'email', 'issue' => 'Email is required.'];
        }
        if (strlen($password) < 6) {
            $errors[] = ['field' => 'password', 'issue' => 'Password must be at least 6 characters.'];
        }
        if ($firstName === '') {
            $errors[] = ['field' => 'first_name', 'issue' => 'First name is required.'];
        }
        if ($lastName === '') {
            $errors[] = ['field' => 'last_name', 'issue' => 'Last name is required.'];
        }
        if ($birthDate === '') {
            $errors[] = ['field' => 'birth_date', 'issue' => 'Birth date is required for vape age verification (YYYY-MM-DD).'];
        }
        if (!$isAgeVerified) {
            $errors[] = ['field' => 'is_age_verified', 'issue' => 'Mandatory age attestation (18+) is required by RA 11900.'];
        }

        if (!empty($errors)) {
            throw new ValidationException('Validation failed for user registration.', $errors);
        }

        $email = new Email($emailStr);

        $existing = $this->userRepository->findByEmail($email->getValue());
        if ($existing !== null) {
            throw new ValidationException("Email '{$email->getValue()}' is already registered.", [
                ['field' => 'email', 'issue' => 'Already taken'],
            ]);
        }

        // Age Verification
        $dob = DateTimeImmutable::createFromFormat('Y-m-d', $birthDate);
        if (!$dob) {
            throw new ValidationException('Invalid birth date format. Use YYYY-MM-DD.', [
                ['field' => 'birth_date', 'issue' => 'Invalid date format'],
            ]);
        }

        $now = new DateTimeImmutable('today');
        $age = $now->diff($dob)->y;
        if ($age < $this->minimumAge) {
            throw new UnderageException($this->minimumAge, $age);
        }

        $userId = 'usr_' . bin2hex(random_bytes(8));
        $passwordHash = $this->passwordHasher->hash($password);

        $user = new User(
            $userId,
            $email,
            $passwordHash,
            $firstName,
            $lastName,
            $data['phone'] ?? null,
            Role::customer(),
            $birthDate,
            true
        );

        $this->userRepository->save($user);

        $ttlSeconds = 86400;
        $token = $this->tokenService->generateToken([
            'sub' => $user->getId(),
            'email' => $user->getEmail()->getValue(),
            'role' => $user->getRole()->getValue(),
        ], $ttlSeconds);

        if ($this->sessionRepository !== null) {
            $session = new Session(
                $token,
                $user->getId(),
                $data['ip_address'] ?? null,
                $data['user_agent'] ?? null,
                date('Y-m-d H:i:s', time() + $ttlSeconds)
            );
            $this->sessionRepository->save($session);
        }

        return [
            'user' => $user->toArray(),
            'token' => $token,
        ];
    }
}
