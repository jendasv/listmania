<?php

declare(strict_types=1);

namespace Tests\Feature\List;

use App\Events\ItemAdded;
use App\Events\ItemDeleted;
use App\Events\ItemsReordered;
use App\Events\ItemUpdated;
use App\Events\ListCreated;
use App\Events\ListDeleted;
use App\Events\ListUpdated;
use App\Models\Liste;
use App\Models\ListItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The household channel reaches every member — nothing about a private list
 * may be broadcast on it.
 */
class ListBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private const EVENTS = [
        ItemAdded::class, ItemUpdated::class, ItemDeleted::class, ItemsReordered::class,
        ListCreated::class, ListUpdated::class, ListDeleted::class,
    ];

    private function listFor(User $user, string $visibility): Liste
    {
        return Liste::factory()->create([
            'household_id' => $user->household()->id,
            'created_by' => $user->id,
            'visibility' => $visibility,
        ]);
    }

    public function test_item_changes_on_private_list_are_not_broadcast(): void
    {
        Event::fake(self::EVENTS);
        $user = $this->createUserWithHousehold();
        $list = $this->listFor($user, 'private');
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $this->actingAs($user)->postJson("/api/lists/{$list->id}/items", ['name' => 'Secret'])->assertCreated();
        $this->actingAs($user)->putJson("/api/lists/{$list->id}/items/{$item->id}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($user)->postJson("/api/lists/{$list->id}/items/reorder", ['order' => [$item->id]])->assertNoContent();
        $this->actingAs($user)->deleteJson("/api/lists/{$list->id}/items/{$item->id}")->assertNoContent();

        Event::assertNotDispatched(ItemAdded::class);
        Event::assertNotDispatched(ItemUpdated::class);
        Event::assertNotDispatched(ItemsReordered::class);
        Event::assertNotDispatched(ItemDeleted::class);
    }

    public function test_item_changes_on_shared_list_are_broadcast(): void
    {
        Event::fake(self::EVENTS);
        $user = $this->createUserWithHousehold();
        $list = $this->listFor($user, 'shared');
        $item = ListItem::factory()->create(['list_id' => $list->id]);

        $this->actingAs($user)->postJson("/api/lists/{$list->id}/items", ['name' => 'Milk'])->assertCreated();
        $this->actingAs($user)->putJson("/api/lists/{$list->id}/items/{$item->id}", ['name' => 'Renamed'])->assertOk();
        $this->actingAs($user)->postJson("/api/lists/{$list->id}/items/reorder", ['order' => [$item->id]])->assertNoContent();
        $this->actingAs($user)->deleteJson("/api/lists/{$list->id}/items/{$item->id}")->assertNoContent();

        Event::assertDispatched(ItemAdded::class);
        Event::assertDispatched(ItemUpdated::class);
        Event::assertDispatched(ItemsReordered::class);
        Event::assertDispatched(ItemDeleted::class);
    }

    public function test_renaming_private_list_is_not_broadcast(): void
    {
        Event::fake(self::EVENTS);
        $user = $this->createUserWithHousehold();
        $list = $this->listFor($user, 'private');

        $this->actingAs($user)->putJson("/api/lists/{$list->id}", ['name' => 'Secret plans'])->assertOk();

        Event::assertNothingDispatched();
    }

    public function test_renaming_shared_list_broadcasts_update(): void
    {
        Event::fake(self::EVENTS);
        $user = $this->createUserWithHousehold();
        $list = $this->listFor($user, 'shared');

        $this->actingAs($user)->putJson("/api/lists/{$list->id}", ['name' => 'Weekend'])->assertOk();

        Event::assertDispatched(ListUpdated::class);
    }

    public function test_making_shared_list_private_tells_members_it_is_gone(): void
    {
        Event::fake(self::EVENTS);
        $user = $this->createUserWithHousehold();
        $list = $this->listFor($user, 'shared');

        $this->actingAs($user)->putJson("/api/lists/{$list->id}", ['name' => 'Secret', 'visibility' => 'private'])->assertOk();

        Event::assertDispatched(ListDeleted::class);
        Event::assertNotDispatched(ListUpdated::class);
    }

    public function test_making_private_list_shared_announces_it(): void
    {
        Event::fake(self::EVENTS);
        $user = $this->createUserWithHousehold();
        $list = $this->listFor($user, 'private');

        $this->actingAs($user)->putJson("/api/lists/{$list->id}", ['name' => 'Weekend', 'visibility' => 'shared'])->assertOk();

        Event::assertDispatched(ListCreated::class);
        Event::assertNotDispatched(ListUpdated::class);
    }
}
