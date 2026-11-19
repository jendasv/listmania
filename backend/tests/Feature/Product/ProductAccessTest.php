<?php

declare(strict_types=1);

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\GlobalProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAccessTest extends TestCase
{
    use RefreshDatabase;

    private function customCategory(User $owner, string $name = 'Custom'): Category
    {
        return Category::create([
            'household_id' => $owner->household()->id,
            'is_global' => false,
            'name' => $name,
            'sort_order' => 0,
        ]);
    }

    public function test_product_cannot_use_another_households_category(): void
    {
        $user = $this->createUserWithHousehold();
        $other = $this->createUserWithHousehold();
        $foreign = $this->customCategory($other, 'Their secret category');

        $this->actingAs($user)->postJson('/api/products', ['name' => 'Milk', 'category_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');

        $this->assertDatabaseMissing('products', ['category_id' => $foreign->id]);
    }

    public function test_product_update_cannot_use_another_households_category(): void
    {
        $user = $this->createUserWithHousehold();
        $other = $this->createUserWithHousehold();
        $foreign = $this->customCategory($other);
        $product = Product::create(['household_id' => $user->household()->id, 'created_by' => $user->id, 'name' => 'Milk']);

        $this->actingAs($user)->putJson("/api/products/{$product->id}", ['category_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_product_can_use_own_and_global_categories(): void
    {
        $user = $this->createUserWithHousehold();
        $own = $this->customCategory($user);
        $global = Category::create(['household_id' => null, 'is_global' => true, 'name' => 'Dairy', 'sort_order' => 1]);

        $this->actingAs($user)->postJson('/api/products', ['name' => 'Milk', 'category_id' => $own->id])->assertCreated();
        $this->actingAs($user)->postJson('/api/products', ['name' => 'Cheese', 'category_id' => $global->id])->assertCreated();
    }

    public function test_product_cannot_link_another_households_submitted_global_product(): void
    {
        $user = $this->createUserWithHousehold();
        $other = $this->createUserWithHousehold();
        $foreign = GlobalProduct::create([
            'household_id' => $other->household()->id, 'barcode' => '123', 'name' => 'X', 'source' => 'user', 'verified' => false, 'scan_count' => 0,
        ]);
        $public = GlobalProduct::create([
            'household_id' => null, 'barcode' => '456', 'name' => 'Y', 'source' => 'open_food_facts', 'verified' => true, 'scan_count' => 0,
        ]);

        $this->actingAs($user)->postJson('/api/products', ['name' => 'X', 'global_product_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('global_product_id');
        $this->actingAs($user)->postJson('/api/products', ['name' => 'Y', 'global_product_id' => $public->id])
            ->assertCreated();
    }
}
