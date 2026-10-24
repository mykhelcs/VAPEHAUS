<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class AddressApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;
    private string $customerToken;

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
    }

    public function testAddressBookCrudWorkflow(): void
    {
        $headers = ['authorization' => "Bearer {$this->customerToken}"];

        // 1. List seeded addresses
        $listReq = new Request('GET', '/api/v1/user/addresses', [], [], $headers);
        $listResp = $this->app->handle($listReq);
        $this->assertEquals(200, $listResp->getStatusCode());
        $listData = json_decode($listResp->getBody(), true);
        $this->assertCount(1, $listData['data']);

        // 2. Add second address
        $createReq = new Request('POST', '/api/v1/user/addresses', [], [
            'label' => 'Office',
            'recipient_name' => 'Juan Work',
            'phone' => '+639170001111',
            'address_line1' => 'Tower 1, Ayala Triangle',
            'city' => 'Makati City',
            'province' => 'Metro Manila',
            'postal_code' => '1226',
            'is_default' => false,
        ], $headers);

        $createResp = $this->app->handle($createReq);
        $this->assertEquals(201, $createResp->getStatusCode());
        $createData = json_decode($createResp->getBody(), true);
        $secondId = $createData['data']['id'];

        // 3. Set second address as default
        $defReq = new Request('PATCH', "/api/v1/user/addresses/{$secondId}/default", [], [], $headers);
        $defResp = $this->app->handle($defReq);
        $this->assertEquals(200, $defResp->getStatusCode());
        $defData = json_decode($defResp->getBody(), true);
        $this->assertTrue($defData['data']['is_default']);
    }
}
