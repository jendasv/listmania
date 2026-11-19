<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_signed_link_verifies_the_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get($this->verificationUrl($user))->assertRedirect();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_tampered_link_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get($this->verificationUrl($user).'x')->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_endpoint_is_rate_limited(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $url = $this->verificationUrl($user).'x';

        foreach (range(1, 6) as $_) {
            $this->get($url)->assertForbidden();
        }

        $this->get($url)->assertStatus(429);
    }
}
