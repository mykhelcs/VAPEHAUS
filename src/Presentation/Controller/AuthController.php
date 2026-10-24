<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Auth\AuthenticateUserExecution;
use Vapehaus\Execution\Auth\GetProfileExecution;
use Vapehaus\Execution\Auth\RegisterUserExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class AuthController
{
    private RegisterUserExecution $registerExecution;
    private AuthenticateUserExecution $authExecution;
    private GetProfileExecution $profileExecution;

    public function __construct(
        RegisterUserExecution $registerExecution,
        AuthenticateUserExecution $authExecution,
        GetProfileExecution $profileExecution
    ) {
        $this->registerExecution = $registerExecution;
        $this->authExecution = $authExecution;
        $this->profileExecution = $profileExecution;
    }

    public function register(Request $request): Response
    {
        $result = $this->registerExecution->execute($request->getBody());
        return ApiResponse::success($result, [], 201);
    }

    public function login(Request $request): Response
    {
        $result = $this->authExecution->execute($request->getBody());
        return ApiResponse::success($result);
    }

    public function me(Request $request): Response
    {
        $userId = (string) $request->getAttribute('userId');
        $profile = $this->profileExecution->execute($userId);
        return ApiResponse::success($profile);
    }
}
