<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

final class CatalogApiTest extends TestCase
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

    public function testGetCategoriesReturnsSeededCategories(): void
    {
        $request = new Request('GET', '/api/v1/categories');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(3, $data['data']);
    }

    public function testGetProductsWithNicotineFilter(): void
    {
        $request = new Request('GET', '/api/v1/products', ['nicotine' => '50mg']);
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['meta']['total'] >= 1);
    }

    public function testGetProductDetailBySlug(): void
    {
        $request = new Request('GET', '/api/v1/products/geek-bar-pulse-15000');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Geek Bar Pulse 15000 Puffs', $data['data']['name']);
        $this->assertCount(2, $data['data']['variants']);
    }
}
