<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Liste;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Array inputs that turn into one query per element must be bounded.
 */
class RequestSizeLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_reorder_is_capped(): void
    {
        $user = $this->createUserWithHousehold();

        $this->actingAs($user)->postJson('/api/lists/reorder', ['order' => range(1, 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order');
    }

    public function test_item_reorder_is_capped(): void
    {
        $user = $this->createUserWithHousehold();
        $list = Liste::factory()->create(['household_id' => $user->household()->id, 'created_by' => $user->id]);

        $this->actingAs($user)->postJson("/api/lists/{$list->id}/items/reorder", ['order' => range(1, 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order');
    }

    public function test_list_creation_caps_nested_items(): void
    {
        $user = $this->createUserWithHousehold();
        $items = array_fill(0, 201, ['name' => 'x', 'quantity' => 1]);

        $this->actingAs($user)->postJson('/api/lists', ['name' => 'Big', 'items' => $items])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }
}
