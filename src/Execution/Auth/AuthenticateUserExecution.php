<?php

declare(strict_types=1);

namespace Vapehaus\Execution\Auth;

use Vapehaus\Domain\Exception\AuthenticationException;
use Vapehaus\Domain\Exception\ValidationException;
use Vapehaus\Domain\Repository\UserRepositoryInterface;
use Vapehaus\Infrastructure\Security\PasswordHasherInterface;
use Vapehaus\Infrastructure\Security\TokenServiceInterface;

final class AuthenticateUserExecution
{
    private UserRepositoryInterface $userRepository;
    private PasswordHasherInterface $passwordHasher;
    private TokenServiceInterface $tokenService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        PasswordHasherInterface $passwordHasher,
        TokenServiceInterface $tokenService
    ) {
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->tokenService = $tokenService;
    }

    /**
     * @param array{email: string, password: string} $credentials
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

        $token = $this->tokenService->generateToken([
            'sub' => $user->getId(),
            'email' => $user->getEmail()->getValue(),
            'role' => $user->getRole()->getValue(),
        ]);

        return [
            'user' => $user->toArray(),
            'token' => $token,
        ];
    }
}
