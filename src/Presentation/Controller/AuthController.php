<?php

declare(strict_types=1);

namespace Vapehaus\Presentation\Controller;

use Vapehaus\Execution\Auth\AuthenticateUserExecution;
use Vapehaus\Execution\Auth\GetProfileExecution;
use Vapehaus\Execution\Auth\LogoutUserExecution;
use Vapehaus\Execution\Auth\RegisterUserExecution;
use Vapehaus\Execution\Auth\ResetPasswordExecution;
use Vapehaus\Execution\Auth\SendOtpExecution;
use Vapehaus\Execution\Auth\VerifyOtpExecution;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Http\Response;
use Vapehaus\Presentation\Response\ApiResponse;

final class AuthController
{
    private RegisterUserExecution $registerExecution;
    private AuthenticateUserExecution $authExecution;
    private GetProfileExecution $profileExecution;
    private LogoutUserExecution $logoutExecution;
    private ?SendOtpExecution $sendOtpExecution;
    private ?VerifyOtpExecution $verifyOtpExecution;
    private ?ResetPasswordExecution $resetPasswordExecution;

    public function __construct(
        RegisterUserExecution $registerExecution,
        AuthenticateUserExecution $authExecution,
        GetProfileExecution $profileExecution,
        ?LogoutUserExecution $logoutExecution = null,
        ?SendOtpExecution $sendOtpExecution = null,
        ?VerifyOtpExecution $verifyOtpExecution = null,
        ?ResetPasswordExecution $resetPasswordExecution = null
    ) {
        $this->registerExecution = $registerExecution;
        $this->authExecution = $authExecution;
        $this->profileExecution = $profileExecution;
        $this->logoutExecution = $logoutExecution ?? new LogoutUserExecution();
        $this->sendOtpExecution = $sendOtpExecution;
        $this->verifyOtpExecution = $verifyOtpExecution;
        $this->resetPasswordExecution = $resetPasswordExecution;
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

    public function sendOtp(Request $request): Response
    {
        if ($this->sendOtpExecution === null) {
            return ApiResponse::error('NOT_IMPLEMENTED', 'Send OTP service is not configured.');
        }

        $result = $this->sendOtpExecution->execute($request->getBody());
        return ApiResponse::success($result);
    }

    public function verifyOtp(Request $request): Response
    {
        if ($this->verifyOtpExecution === null) {
            return ApiResponse::error('NOT_IMPLEMENTED', 'Verify OTP service is not configured.');
        }

        $result = $this->verifyOtpExecution->execute($request->getBody());
        return ApiResponse::success($result);
    }

    public function forgotPassword(Request $request): Response
    {
        if ($this->sendOtpExecution === null) {
            return ApiResponse::error('NOT_IMPLEMENTED', 'Password reset service is not configured.');
        }

        $body = $request->getBody();
        $body['purpose'] = 'password_reset';

        $result = $this->sendOtpExecution->execute($body);
        return ApiResponse::success($result);
    }

    public function resetPassword(Request $request): Response
    {
        if ($this->resetPasswordExecution === null) {
            return ApiResponse::error('NOT_IMPLEMENTED', 'Reset password service is not configured.');
        }

        $result = $this->resetPasswordExecution->execute($request->getBody());
        return ApiResponse::success($result);
    }
}
