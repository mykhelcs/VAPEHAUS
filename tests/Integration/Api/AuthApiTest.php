<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use DateTimeImmutable;
use PDO;
use Vapehaus\App;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class AuthApiTest extends TestCase
{
    private App $app;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->app = new App($this->pdo);
    }

    public function testCustomerRegistrationSuccess(): void
    {
        $dob = (new DateTimeImmutable('-22 years'))->format('Y-m-d');
        $request = new Request('POST', '/api/v1/auth/register', [], [
            'email' => 'newvaper@vapehaus.ph',
            'password' => 'Pass12345!',
            'first_name' => 'Kairus',
            'last_name' => 'Santos',
            'birth_date' => $dob,
            'is_age_verified' => true,
        ]);

        $response = $this->app->handle($request);
        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('newvaper@vapehaus.ph', $data['data']['user']['email']);
        $this->assertNotNull($data['data']['token']);
    }

    public function testMinorRegistrationRejectedByAgeGate(): void
    {
        $dob = (new DateTimeImmutable('-15 years'))->format('Y-m-d');
        $request = new Request('POST', '/api/v1/auth/register', [], [
            'email' => 'underage@vapehaus.ph',
            'password' => 'Pass12345!',
            'first_name' => 'Minor',
            'last_name' => 'Boy',
            'birth_date' => $dob,
            'is_age_verified' => true,
        ]);

        $response = $this->app->handle($request);
        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('UNDERAGE_RESTRICTION', $data['error']['code']);
    }

    public function testAdminLoginSuccess(): void
    {
        $request = new Request('POST', '/api/v1/auth/login', [], [
            'email' => 'admin@vapehaus.ph',
            'password' => 'AdminPass123!',
        ]);

        $response = $this->app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('admin', $data['data']['user']['role']);
        $this->assertNotNull($data['data']['token']);
    }
}
