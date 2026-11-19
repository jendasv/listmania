<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use App\Models\Liste;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    private function product(User $user, string $name): void
    {
        Product::create(['household_id' => $user->household()->id, 'created_by' => $user->id, 'name' => $name]);
    }

    public function test_autocomplete_is_case_insensitive(): void
    {
        $user = $this->createUserWithHousehold();
        $this->product($user, 'Milk');

        $this->actingAs($user)->getJson('/api/products/search?q=milk')->assertOk()->assertJsonCount(1);
        $this->actingAs($user)->getJson('/api/products?q=MILK')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_like_wildcards_in_the_term_match_literally(): void
    {
        $user = $this->createUserWithHousehold();
        $this->product($user, 'Cream 30%');
        $this->product($user, 'Cream 300');
        $this->product($user, 'a_b');
        $this->product($user, 'axb');

        $this->actingAs($user)->getJson('/api/products/search?q='.urlencode('30%'))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Cream 30%');
        $this->actingAs($user)->getJson('/api/products/search?q=a_b')
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'a_b');
    }

    public function test_list_search_escapes_wildcards(): void
    {
        $user = $this->createUserWithHousehold();
        foreach (['100% fun', 'Groceries'] as $name) {
            Liste::factory()->create(['household_id' => $user->household()->id, 'created_by' => $user->id, 'name' => $name]);
        }

        $this->actingAs($user)->getJson('/api/lists?search='.urlencode('%'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', '100% fun');
    }

    public function test_catalog_uses_the_standard_pagination_meta(): void
    {
        $user = $this->createUserWithHousehold();
        foreach (range(1, 11) as $i) {
            $this->product($user, "Product {$i}");
        }

        $this->actingAs($user)->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertExactJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total', 'per_page']])
            ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2, 'total' => 11, 'per_page' => 10]);
    }
}
