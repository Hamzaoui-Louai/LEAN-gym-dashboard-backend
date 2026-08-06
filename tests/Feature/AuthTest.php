<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_returns_json_and_logs_the_user_in(): void
    {
        Notification::fake();

        $response = $this->postJson('/register', [
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'email_verified_at' => null,
        ]);

        $this->getJson('/api/user')
            ->assertJson(['email' => 'admin@example.com'])
            ->assertJsonPath('email_verified_at', null);

        Notification::assertSentTo(
            User::where('email', 'admin@example.com')->first(),
            VerifyEmail::class,
        );
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

    public function test_email_verification_link_marks_email_as_verified(): void
    {
        $user = User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->getJson($url)->assertNoContent();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verification_notification_can_be_resent(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->actingAs($user)
            ->postJson('/email/verification-notification')
            ->assertStatus(202);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verification_notification_is_not_sent_when_already_verified(): void
    {
        Notification::fake();

        $user = User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);
        $user->markEmailAsVerified();

        $this->actingAs($user)
            ->postJson('/email/verification-notification')
            ->assertNoContent();

        Notification::assertNothingSent();
    }
}
