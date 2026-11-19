<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Malformed client input is a client error (4xx), never a server error.
 */
class MalformedInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_numeric_route_ids_are_not_found(): void
    {
        $user = $this->createUserWithHousehold();

        foreach (['/api/lists/abc', '/api/lists/1/items/abc', '/api/products/abc', '/api/categories/abc'] as $uri) {
            $this->actingAs($user)->getJson($uri)->assertNotFound();
            $this->actingAs($user)->putJson($uri, ['name' => 'x'])->assertNotFound();
        }

        $this->actingAs($user)->deleteJson('/api/household/members/abc')->assertNotFound();
        $this->actingAs($user)->postJson('/api/household/abc/leave')->assertNotFound();
    }

    public function test_list_index_query_params_are_validated(): void
    {
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)->getJson('/api/lists?search[]=x')->assertStatus(422)->assertJsonValidationErrors('search');
        $this->actingAs($user)->getJson('/api/lists?sort=drop')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->actingAs($user)->getJson('/api/lists?filter=x')->assertStatus(422)->assertJsonValidationErrors('filter');
        $this->actingAs($user)->getJson('/api/lists?page=abc')->assertStatus(422)->assertJsonValidationErrors('page');
        $this->actingAs($user)->getJson('/api/lists?search=milk&sort=az&filter=shared&page=1')->assertOk();
    }
}
