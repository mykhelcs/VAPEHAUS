<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Exception\AuthenticationException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Model\Session;
use Vapehaus\Domain\Repository\SessionRepositoryInterface;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Infrastructure\Security\PasswordHasherInterface;
use Vapehaus\Infrastructure\Security\TokenServiceInterface;

final class AuthenticateUserExecution
{
    private UserRepositoryInterface $userRepository;
    private PasswordHasherInterface $passwordHasher;
    private TokenServiceInterface $tokenService;
    private ?SessionRepositoryInterface $sessionRepository;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher,
        TokenServiceInterface $tokenService,
        ?SessionRepositoryInterface $sessionRepository = null
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenService = $tokenService;
        $this->sessionRepository = $sessionRepository;
    }

    /**
     * @param array{email: string, password: string, ip_address?: ?string, user_agent?: ?string} $credentials
     * @return array{user: array<string, mixed>, token: string}
     */
    public function execute(array $credentials): array
    {
        $email = strtolower(trim($credentials['email'] ?? ''));
        $password = $credentials['password'] ?? '';

        if ($email === '' || $password === '') {
            throw new ValidationException('Email and password are required.');
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null || !$this->passwordHasher->verify($password, $user->getPasswordHash())) {
            throw new AuthenticationException('Invalid email or password.');
        }

        $ttlSeconds = 86400; // 24 hours
        $token = $this->tokenService->generateToken([
            'sub' => $user->getId(),
            'email' => $user->getEmail()->getValue(),
            'role' => $user->getRole()->getValue(),
        ], $ttlSeconds);

        if ($this->sessionRepository !== null) {
            $session = new Session(
                $token,
                $user->getId(),
                $credentials['ip_address'] ?? null,
                $credentials['user_agent'] ?? null,
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
