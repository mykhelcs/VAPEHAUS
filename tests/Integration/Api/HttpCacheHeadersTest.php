<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Integration\Api;

use PDO;
use Vapehaus\App;
use Vapehaus\Infrastructure\Database\SchemaManager;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Presentation\Middleware\RateLimiterMiddleware;
use Vapehaus\Tests\TestCase;

final class HttpCacheHeadersTest extends TestCase
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
    }

    public function testPublicCatalogRoutesEmitHttpCacheHeaders(): void
    {
        $req = new Request('GET', '/api/v1/categories');
        $res = $this->app->handle($req);

        $this->assertEquals(200, $res->getStatusCode());
        $headers = $res->getHeaders();

        // 1. Must emit Cache-Control
        $cacheControl = $headers['cache-control'] ?? $headers['Cache-Control'] ?? null;
        $this->assertNotNull($cacheControl);
        $this->assertTrue(str_contains($cacheControl, 'public'));
        $this->assertTrue(str_contains($cacheControl, 'max-age'));

        // 2. Must emit ETag
        $etag = $headers['etag'] ?? $headers['ETag'] ?? null;
        $this->assertNotNull($etag);
        $this->assertTrue(strlen($etag) > 5);

        // 3. Must emit Last-Modified
        $lastModified = $headers['last-modified'] ?? $headers['Last-Modified'] ?? null;
        $this->assertNotNull($lastModified);
    }

    public function testCatalogFacetsEmitsCacheHeadersAndHandlesIfNoneMatch304(): void
    {
        $req1 = new Request('GET', '/api/v1/products/facets');
        $res1 = $this->app->handle($req1);

        $this->assertEquals(200, $res1->getStatusCode());
        $headers = $res1->getHeaders();
        $etag = $headers['etag'] ?? $headers['ETag'] ?? null;
        $this->assertNotNull($etag);

        // Send conditional request with If-None-Match
        $req2 = new Request('GET', '/api/v1/products/facets', [], [], [
            'if-none-match' => $etag,
        ]);
        $res2 = $this->app->handle($req2);

        // Must return 304 Not Modified with empty body
        $this->assertEquals(304, $res2->getStatusCode());
        $this->assertEquals('', $res2->getBody());
        $headers2 = $res2->getHeaders();
        $this->assertEquals($etag, $headers2['etag'] ?? $headers2['ETag'] ?? null);
    }

    public function testProductsListEmitsCacheHeaders(): void
    {
        $req = new Request('GET', '/api/v1/products');
        $res = $this->app->handle($req);

        $this->assertEquals(200, $res->getStatusCode());
        $headers = $res->getHeaders();

        $cacheControl = $headers['cache-control'] ?? $headers['Cache-Control'] ?? null;
        $this->assertNotNull($cacheControl);
        $this->assertTrue(str_contains($cacheControl, 'max-age'));

        $etag = $headers['etag'] ?? $headers['ETag'] ?? null;
        $this->assertNotNull($etag);
    }
}
