<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GlobalProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_with_path_characters_is_rejected(): void
    {
        Http::fake();
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)->getJson('/api/global-products/search?barcode='.urlencode('../../x'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('barcode');

        Http::assertNothingSent();
    }

    public function test_barcode_lookup_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['status' => 0], 404)]);
        $user = $this->createUserWithHousehold();

        foreach (range(1, 30) as $i) {
            $this->actingAs($user)->getJson('/api/global-products/search?barcode=40000000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT))->assertOk();
        }

        $this->actingAs($user)->getJson('/api/global-products/search?barcode=4000000000099')->assertStatus(429);
    }
}
