<?php

declare(strict_types=1);

namespace Tests\Feature\Invitation;

use App\Enums\InvitationStatus;
use App\Mail\HouseholdInvitation;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_in_household_and_inviter_name_is_rendered_as_text(): void
    {
        $owner = $this->createUserWithHousehold('[Confirm here](https://evil.example/a)');
        $owner->forceFill(['name' => '**Support** ![x](https://evil.example/b.png)'])->save();

        $invitation = Invitation::create([
            'household_id' => $owner->household()->id,
            'invited_by' => $owner->id,
            'email' => 'invitee@example.com',
            'token' => Str::random(64),
            'status' => InvitationStatus::Pending->value,
            'expires_at' => now()->addDays(7),
        ]);

        $html = (new HouseholdInvitation($invitation->load(['household', 'invitedBy'])))->render();

        $this->assertStringNotContainsString('href="https://evil.example', $html);
        $this->assertStringNotContainsString('src="https://evil.example', $html);
        $this->assertStringNotContainsString('<strong>Support</strong>', $html);
        $this->assertStringContainsString('Confirm here', $html);
    }
}
