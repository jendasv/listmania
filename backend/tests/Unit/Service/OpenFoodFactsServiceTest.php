<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\OpenFoodFactsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenFoodFactsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_timed_out_service_does_not_abort_the_lookup_for_the_others(): void
    {
        Http::fake([
            'world.openfoodfacts.org/*' => fn () => throw new ConnectionException('Connection timed out'),
            'world.openbeautyfacts.org/*' => Http::response([
                'status' => 1,
                'product' => ['product_name' => 'Test Shampoo', 'brands' => 'Acme'],
            ]),
            '*' => Http::response(['status' => 0], 404),
        ]);

        $product = app(OpenFoodFactsService::class)->fetchAndStore('1234567890123');

        $this->assertNotNull($product);
        $this->assertSame('Test Shampoo', $product->name);
    }

    public function test_returns_null_when_every_service_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $product = app(OpenFoodFactsService::class)->fetchAndStore('1234567890123');

        $this->assertNull($product);
    }

    public function test_non_gtin_barcode_never_reaches_the_external_apis(): void
    {
        Http::fake();

        foreach (['../../../etc', '123abc', '123', '1234567890123456'] as $barcode) {
            $this->assertNull(app(OpenFoodFactsService::class)->fetchAndStore($barcode));
        }

        Http::assertNothingSent();
    }

    public function test_definitive_miss_is_cached(): void
    {
        Http::fake(['*' => Http::response(['status' => 0], 404)]);
        $service = app(OpenFoodFactsService::class);

        $this->assertNull($service->fetchAndStore('1234567890123'));
        $this->assertNull($service->fetchAndStore('1234567890123'));

        Http::assertSentCount(4);
    }

    public function test_timeout_is_not_cached_as_miss(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));
        $service = app(OpenFoodFactsService::class);

        $service->fetchAndStore('1234567890123');
        $service->fetchAndStore('1234567890123');

        $this->assertFalse(Cache::has('openfacts:miss:1234567890123'));
    }
}
