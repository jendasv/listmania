<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->spaPostJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email']]);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->spaPostJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_nonexistent_email_is_rejected(): void
    {
        $response = $this->spaPostJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_required_fields_are_validated(): void
    {
        $response = $this->spaPostJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_is_returned(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->spaPostJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'John Doe')
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_attempts_are_limited_per_account_across_ips(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        // A distributed attack: every attempt from a different IP.
        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->spaPostJson('/api/auth/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->spaPostJson('/api/auth/login', ['email' => strtoupper($user->email), 'password' => 'password123'])
            ->assertStatus(429);
    }

    public function test_login_attempts_are_limited_per_ip_across_accounts(): void
    {
        foreach (range(1, 20) as $i) {
            $this->spaPostJson('/api/auth/login', ['email' => "user{$i}@example.com", 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->spaPostJson('/api/auth/login', ['email' => 'user21@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }
}
