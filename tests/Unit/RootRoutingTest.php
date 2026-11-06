<?php

declare(strict_types=1);

namespace Vapehaus\Tests\Unit;

use Vapehaus\App;
use Vapehaus\Presentation\Http\Request;
use Vapehaus\Tests\TestCase;

class RootRoutingTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $this->app = new App();
    }

    public function testRootEndpointReturnsSuccessEnvelope(): void
    {
        $request = new Request('GET', '/');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);

        $this->assertTrue($body['success']);
        $this->assertEquals('VAPEHAUS API', $body['data']['service']);
        $this->assertEquals('operational', $body['data']['status']);
    }

    public function testApiEndpointsReturnSuccessEnvelope(): void
    {
        foreach (['/api', '/api/v1'] as $path) {
            $request = new Request('GET', $path);
            $response = $this->app->handle($request);

            $this->assertEquals(200, $response->getStatusCode(), "Path {$path} should return 200");
            $body = json_decode($response->getBody(), true);

            $this->assertTrue($body['success']);
            $this->assertEquals('VAPEHAUS API', $body['data']['service']);
        }
    }
}
