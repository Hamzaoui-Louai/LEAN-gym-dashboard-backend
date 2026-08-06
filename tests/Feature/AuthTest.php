<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_returns_json_and_logs_the_user_in(): void
    {
        $response = $this->postJson('/register', [
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
        ]);

        $this->getJson('/api/user')
            ->assertJson(['email' => 'admin@example.com']);
    }

    public function test_registration_requires_name_and_password_confirmation(): void
    {
        $this->postJson('/register', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'password']);
    }

    public function test_login_returns_json_response(): void
    {
        User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJson(['two_factor' => false]);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJson(['email' => 'admin@example.com']);
    }

    public function test_login_with_invalid_credentials_fails(): void
    {
        User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->actingAs($user)->postJson('/logout')->assertNoContent();

        $this->getJson('/api/user')->assertUnauthorized();
    }
}
