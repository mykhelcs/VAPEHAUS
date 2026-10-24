<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Security\JwtTokenService;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class ReviewApiTest extends TestCase
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

    public function testCustomerSubmitsReviewUpdatesAggregateRating(): void
    {
        $headers = ['authorization' => "Bearer {$this->customerToken}"];

        $req = new Request('POST', '/api/v1/products/prd_geek_pulse/reviews', [], [
            'rating' => 5,
            'title' => 'Best disposable flavor ever!',
            'comment' => 'Watermelon Ice has great vapor production and battery life.',
        ], $headers);

        $resp = $this->app->handle($req);
        $this->assertEquals(201, $resp->getStatusCode());

        // Check product reviews list
        $listReq = new Request('GET', '/api/v1/products/prd_geek_pulse/reviews');
        $listResp = $this->app->handle($listReq);
        $this->assertEquals(200, $listResp->getStatusCode());

        $data = json_decode($listResp->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals(5.0, $data['meta']['rating_summary']['average']);
    }
}
