<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every API error uses Laravel's standard { message, errors? } shape.
 */
class ErrorShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated(): void
    {
        $this->getJson('/api/lists')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_form_request_validation(): void
    {
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)->postJson('/api/lists', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name']])
            ->assertJsonMissingPath('error')
            ->assertJsonMissingPath('details');
    }

    public function test_inline_validation(): void
    {
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)->postJson('/api/categories', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_domain_not_found(): void
    {
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)->getJson('/api/lists/999999')
            ->assertStatus(404)
            ->assertExactJson(['message' => 'List not found.']);
    }
}
