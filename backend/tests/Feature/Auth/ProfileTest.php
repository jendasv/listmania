<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_their_name(): void
    {
        $user = $this->createUserWithHousehold();

        $response = $this->actingAs($user)->putJson('/api/auth/profile', ['name' => 'New Name']);

        $response->assertOk()->assertJsonPath('user.name', 'New Name');
    }

    public function test_changing_email_resets_verification_and_sends_notification(): void
    {
        Notification::fake();

        $user = $this->createUserWithHousehold();
        $this->assertNotNull($user->email_verified_at);

        $response = $this->actingAs($user)->putJson('/api/auth/profile', ['email' => 'new@example.com', 'current_password' => 'password']);

        $response->assertOk()->assertJsonPath('user.email', 'new@example.com');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertNull($response->json('user.emailVerifiedAt'));

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_updating_name_without_changing_email_keeps_verification(): void
    {
        Notification::fake();

        $user = $this->createUserWithHousehold();

        $this->actingAs($user)->putJson('/api/auth/profile', ['name' => 'Same Email Person', 'email' => $user->email]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        Notification::assertNothingSent();
    }

    public function test_changing_email_requires_current_password(): void
    {
        $user = $this->createUserWithHousehold();
        $originalEmail = $user->email;

        $this->actingAs($user)->putJson('/api/auth/profile', ['email' => 'attacker@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($user)->putJson('/api/auth/profile', ['email' => 'attacker@example.com', 'current_password' => 'wrong'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertSame($originalEmail, $user->fresh()->email);
    }
}
