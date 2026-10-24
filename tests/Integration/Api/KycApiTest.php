<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class KycApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private string $customerToken;
    private string $adminToken;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo);

        $tokenService = new JwtTokenService();
        $this->customerToken = $tokenService->generateToken([
            'sub' => 'usr_customer_default',
            'email' => 'customer@vapehaus.ph',
            'role' => 'customer',
        ]);
        $this->adminToken = $tokenService->generateToken([
            'sub' => 'usr_admin_default',
            'email' => 'admin@vapehaus.ph',
            'role' => 'admin',
        ]);
    }

    public function testKycSubmissionAndAdminApproval(): void
    {
        $custHeaders = ['authorization' => "Bearer {$this->customerToken}"];
        $adminHeaders = ['authorization' => "Bearer {$this->adminToken}"];

        // 1. Submit Government ID for 18+ Verification
        $subReq = new Request('POST', '/api/v1/user/kyc/submit', [], [
            'id_type' => 'drivers_license',
            'id_number' => 'N02-12-123456',
            'front_image_url' => 'https://vapehaus.test/uploads/id_front.jpg',
            'back_image_url' => 'https://vapehaus.test/uploads/id_back.jpg',
        ], $custHeaders);

        $subResp = $this->app->handle($subReq);
        $this->assertEquals(201, $subResp->getStatusCode());

        $subData = json_decode($subResp->getBody(), true);
        $kycId = $subData['data']['id'];
        $this->assertEquals('pending', $subData['data']['status']);

        // 2. Admin Reviews and Approves KYC
        $revReq = new Request('PATCH', "/api/v1/admin/kyc/{$kycId}/review", [], [
            'decision' => 'approved',
        ], $adminHeaders);

        $revResp = $this->app->handle($revReq);
        $this->assertEquals(200, $revResp->getStatusCode());
        $revData = json_decode($revResp->getBody(), true);
        $this->assertEquals('approved', $revData['data']['status']);
    }
}
