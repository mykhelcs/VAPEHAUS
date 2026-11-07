<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Middleware\RateLimiterMiddleware;
use Vapehaus\Tests\TestCase;

final class KycApiSecurityTest extends TestCase
{
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiterMiddleware::reset();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo, true);
        (new SchemaManager($this->pdo))->seedSampleProducts();
        $this->pdo->exec("UPDATE users SET is_age_verified = 0 WHERE email = 'customer@vapehaus.ph'");
    }

    private function loginCustomer(): string
    {
        $req = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'customer@vapehaus.ph',
            'password' => 'VapePass123!',
        ]);
        $res = $this->app->handle($req);
        $data = json_decode($res->getBody(), true);
        return $data['data']['token'];
    }

    private function loginAdmin(): string
    {
        $req = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'admin@vapehaus.ph',
            'password' => 'AdminPass123!',
        ]);
        $res = $this->app->handle($req);
        $data = json_decode($res->getBody(), true);
        return $data['data']['token'];
    }

    public function testUnauthenticatedAccessToKycEndpointsIsRejected(): void
    {
        $req1 = new Request('GET', '/api/v1/user/kyc');
        $res1 = $this->app->handle($req1);
        $this->assertEquals(401, $res1->getStatusCode());

        $req2 = new Request('POST', '/api/v1/user/kyc/submit', [], [
            'id_type' => 'passport',
            'id_number' => 'P12345678',
        ]);
        $res2 = $this->app->handle($req2);
        $this->assertEquals(401, $res2->getStatusCode());

        $req3 = new Request('POST', '/api/v1/user/kyc/upload', [], [
            'image_data' => 'dummy',
        ]);
        $res3 = $this->app->handle($req3);
        $this->assertEquals(401, $res3->getStatusCode());
    }

    public function testCustomerCannotAccessAdminKycQueue(): void
    {
        $token = $this->loginCustomer();
        $req = new Request('GET', '/api/v1/admin/kyc', [], [], [
            'authorization' => "Bearer {$token}",
        ]);
        $res = $this->app->handle($req);
        $this->assertEquals(403, $res->getStatusCode());
    }

    public function testCustomerKycDocumentUploadAndSubmissionFlow(): void
    {
        $token = $this->loginCustomer();
        $authHeader = ['authorization' => "Bearer {$token}"];

        // 1. Valid 1x1 PNG image (Base64)
        $png1x1Base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $uploadReq = new Request('POST', '/api/v1/user/kyc/upload', [], [
            'image_data' => $png1x1Base64,
            'filename' => 'id_card.png',
        ], $authHeader);

        $uploadRes = $this->app->handle($uploadReq);
        $this->assertEquals(201, $uploadRes->getStatusCode());
        $uploadJson = json_decode($uploadRes->getBody(), true);
        $this->assertTrue($uploadJson['success']);
        $this->assertTrue(!empty($uploadJson['data']['url']));
        $uploadedUrl = $uploadJson['data']['url'];

        // 2. Submit KYC with uploaded image
        $submitReq = new Request('POST', '/api/v1/user/kyc/submit', [], [
            'id_type' => 'drivers_license',
            'id_number' => 'N01-99-887766',
            'front_image_url' => $uploadedUrl,
        ], $authHeader);

        $submitRes = $this->app->handle($submitReq);
        $this->assertEquals(201, $submitRes->getStatusCode());
        $submitJson = json_decode($submitRes->getBody(), true);
        $this->assertTrue($submitJson['success']);
        $this->assertEquals('pending', $submitJson['data']['status']);
        $kycId = $submitJson['data']['id'];

        // 3. Admin views queue and reviews
        $adminToken = $this->loginAdmin();
        $adminHeader = ['authorization' => "Bearer {$adminToken}"];

        $queueReq = new Request('GET', '/api/v1/admin/kyc', [], [], $adminHeader);
        $queueRes = $this->app->handle($queueReq);
        $this->assertEquals(200, $queueRes->getStatusCode());
        $queueJson = json_decode($queueRes->getBody(), true);
        $this->assertTrue($queueJson['success']);
        $this->assertTrue(!empty($queueJson['data']));

        // Check enriched fields (name, email)
        $found = null;
        foreach ($queueJson['data'] as $item) {
            if ($item['id'] === $kycId) {
                $found = $item;
                break;
            }
        }
        $this->assertNotNull($found);
        $this->assertEquals('customer@vapehaus.ph', $found['customer_email'] ?? $found['user_email'] ?? null);

        // 4. Admin approves
        $approveReq = new Request('PATCH', "/api/v1/admin/kyc/{$kycId}/approve", [], [
            'decision' => 'approved',
        ], $adminHeader);
        $approveRes = $this->app->handle($approveReq);
        $this->assertEquals(200, $approveRes->getStatusCode());

        // 5. Customer status is now approved
        $statusReq = new Request('GET', '/api/v1/user/kyc', [], [], $authHeader);
        $statusRes = $this->app->handle($statusReq);
        $this->assertEquals(200, $statusRes->getStatusCode());
        $statusJson = json_decode($statusRes->getBody(), true);
        $this->assertEquals('approved', $statusJson['data']['status']);
    }
}
