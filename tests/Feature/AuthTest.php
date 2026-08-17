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

        $this->getJson($url)->assertNoContent();

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_link_redirects_browser_to_frontend(): void
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

        $this->get($url)
            ->assertRedirect(config('app.frontend_url').'/email-verified');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_link_with_invalid_hash_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => sha1('wrong-email@example.com'),
        ]);

        $this->getJson($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_link_with_expired_signature_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(2),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
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

    public function test_update_user_changes_name_and_email(): void
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

        $response = $this->putJson('/api/user', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ], $this->authHeader($token));

        $response->assertOk()
            ->assertJsonPath('name', 'Updated Name')
            ->assertJsonPath('email', 'updated@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'updated@example.com',
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_user_rejects_duplicate_email(): void
    {
        User::create([
            'name' => 'Lean Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);
        User::create([
            'name' => 'Other User',
            'email' => 'taken@example.com',
            'password' => bcrypt('password123'),
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->json('access_token');

        $this->putJson('/api/user', [
            'name' => 'Lean Admin',
            'email' => 'taken@example.com',
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_user_requires_name_and_email(): void
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

        $this->putJson('/api/user', [], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_change_password_updates_password_and_rejects_wrong_current(): void
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

        // Wrong current password
        $this->putJson('/api/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        // Correct current password
        $this->putJson('/api/user/password', [
            'current_password' => 'password123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], $this->authHeader($token))
            ->assertOk();

        // Old password no longer works
        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertUnauthorized();

        // New password works
        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_change_password_requires_confirmation_and_min_length(): void
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

        $this->putJson('/api/user/password', [
            'current_password' => 'password123',
            'password' => 'short',
            'password_confirmation' => 'different',
        ], $this->authHeader($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_delete_account_removes_user(): void
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

        $this->deleteJson('/api/user', [], $this->authHeader($token))
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
    }

    public function test_user_endpoints_require_authentication(): void
    {
        $this->putJson('/api/user', ['name' => 'X', 'email' => 'x@x.com'])->assertUnauthorized();
        $this->putJson('/api/user/password', ['current_password' => 'x', 'password' => 'x', 'password_confirmation' => 'x'])->assertUnauthorized();
        $this->deleteJson('/api/user')->assertUnauthorized();
    }
}
