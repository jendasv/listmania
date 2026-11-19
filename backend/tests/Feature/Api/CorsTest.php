<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_private_ip_origin_is_not_allowed_outside_local(): void
    {
        $this->assertNotSame('local', config('app.env'));

        $response = $this->withHeader('Origin', 'http://192.168.1.50:5173')
            ->options('/api/lists', [], ['Access-Control-Request-Method' => 'GET']);

        $this->assertNotSame('http://192.168.1.50:5173', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_configured_frontend_origin_is_allowed(): void
    {
        $origin = config('cors.allowed_origins')[0];

        $this->withHeader('Origin', $origin)
            ->options('/api/lists', [], ['Access-Control-Request-Method' => 'GET'])
            ->assertHeader('Access-Control-Allow-Origin', $origin);
    }
}
