<?php

declare(strict_types=1);

namespace Vapehaus\Domain\Model;

final class Session
{
    private string $token;
    private string $userId;
    private ?string $ipAddress;
    private ?string $userAgent;
    private string $expiresAt;
    private string $createdAt;

    public function __construct(
        string $token,
        string $userId,
        ?string $ipAddress,
        ?string $userAgent,
        string $expiresAt,
        ?string $createdAt = null
    ) {
        $this->token = $token;
        $this->userId = $userId;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return strtotime($this->expiresAt) < time();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user_id' => $this->userId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'expires_at' => $this->expiresAt,
            'created_at' => $this->createdAt,
        ];
    }
}
