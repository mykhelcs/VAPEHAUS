<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Auth\AuthenticateUserExecution;
use Vapehaus\Execution\Auth\GetProfileExecution;
use Vapehaus\Execution\Auth\LogoutUserExecution;
use Vapehaus\Execution\Auth\RegisterUserExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class AuthController
{
    private RegisterUserExecution $registerExecution;
    private AuthenticateUserExecution $authExecution;
    private GetProfileExecution $profileExecution;
    private LogoutUserExecution $logoutExecution;

    public function __construct(
        RegisterUserExecution $registerExecution,
        AuthenticateUserExecution $authExecution,
        GetProfileExecution $profileExecution,
        ?LogoutUserExecution $logoutExecution = null
    ) {
        $this->registerExecution = $registerExecution;
        $this->authExecution = $authExecution;
        $this->profileExecution = $profileExecution;
        $this->logoutExecution = $logoutExecution ?? new LogoutUserExecution();
    }

    public function register(Request $request): Response
    {
        $body = $request->getBody();
        $body['ip_address'] = $request->getHeader('x-forwarded-for') ?? $request->getHeader('remote-addr');
        $body['user_agent'] = $request->getHeader('user-agent');

        $result = $this->registerExecution->execute($body);
        return ApiResponse::success($result, [], 201);
    }

    public function login(Request $request): Response
    {
        $body = $request->getBody();
        $body['ip_address'] = $request->getHeader('x-forwarded-for') ?? $request->getHeader('remote-addr');
        $body['user_agent'] = $request->getHeader('user-agent');

        $result = $this->authExecution->execute($body);
        return ApiResponse::success($result);
    }

    public function logout(Request $request): Response
    {
        $token = $request->getBearerToken();
        $userId = $request->getAttribute('userId');

        $result = $this->logoutExecution->execute($token, $userId !== null ? (string) $userId : null);
        return ApiResponse::success($result);
    }

    public function me(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $profile = $this->profileExecution->execute($userId);
        return ApiResponse::success($profile);
    }
}
