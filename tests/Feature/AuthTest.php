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

    private function authHeader(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_registration_returns_token_and_sends_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/register', [
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user'])
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('user.email', 'admin@example.com')
            ->assertJsonPath('user.email_verified_at', null);

        Notification::assertSentTo(
            User::where('email', 'admin@example.com')->first(),
            VerifyEmail::class,
        );
    }

    public function test_registration_requires_name_and_password_confirmation(): void
    {
        $this->postJson('/api/register', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'password']);
    }

    public function test_login_returns_token_and_grant_access_to_user_endpoint(): void
    {
        User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user'])
            ->assertJsonPath('user.email', 'admin@example.com');

        $token = $response->json('access_token');

        $this->getJson('/api/user', $this->authHeader($token))
            ->assertOk()
            ->assertJson(['email' => 'admin@example.com']);
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    public function test_user_endpoint_requires_a_token(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_invalidates_the_token(): void
    {
        User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->json('access_token');

        $this->postJson('/api/logout', [], $this->authHeader($token))->assertNoContent();

        $this->getJson('/api/user', $this->authHeader($token))->assertUnauthorized();
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

        $token = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->json('access_token');

        $this->postJson('/api/email/verification-notification', [], $this->authHeader($token))
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

        $token = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->json('access_token');

        $this->postJson('/api/email/verification-notification', [], $this->authHeader($token))
            ->assertNoContent();

        Notification::assertNothingSent();
    }
}
