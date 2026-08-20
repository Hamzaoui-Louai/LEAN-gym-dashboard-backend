<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialiteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function fakeSocialiteUser(array $overrides = []): SocialiteUser
    {
        $defaults = [
            'id' => 'google-12345',
            'nickname' => null,
            'name' => 'Jane Google',
            'email' => 'jane@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/avatar.jpg',
            'token' => null,
            'refreshToken' => null,
            'expiresIn' => null,
        ];

        $data = array_merge($defaults, $overrides);

        $user = new SocialiteUser;
        $user->map($data);

        return $user;
    }

    private function mockGoogleDriver(SocialiteUser $socialiteUser): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
    }

    public function test_google_redirect_returns_302_to_google(): void
    {
        $response = $this->get('/api/auth/google/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->getTargetUrl());
    }

    public function test_google_callback_creates_new_user_and_redirects_with_token(): void
    {
        $this->mockGoogleDriver($this->fakeSocialiteUser());

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'jane@gmail.com',
            'name' => 'Jane Google',
            'provider' => 'google',
            'provider_id' => 'google-12345',
        ]);

        $user = User::where('email', 'jane@gmail.com')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->password);

        $targetUrl = $response->getTargetUrl();
        $this->assertStringContainsString('/auth/google/callback?token=', $targetUrl);
    }

    public function test_google_callback_finds_existing_user_by_email(): void
    {
        $existing = User::create([
            'name' => 'Existing User',
            'email' => 'existing@gmail.com',
            'password' => bcrypt('password123'),
        ]);

        $this->mockGoogleDriver($this->fakeSocialiteUser([
            'id' => 'google-99999',
            'name' => 'Existing User',
            'email' => 'existing@gmail.com',
            'avatar' => 'https://lh3.googleusercontent.com/new-avatar.jpg',
        ]));

        $response = $this->get('/api/auth/google/callback');

        $response->assertRedirect();

        $existing->refresh();
        $this->assertEquals('google', $existing->provider);
        $this->assertEquals('google-99999', $existing->provider_id);
        $this->assertEquals('https://lh3.googleusercontent.com/new-avatar.jpg', $existing->avatar);
    }

    public function test_google_callback_with_duplicate_provider_id_is_idempotent(): void
    {
        $this->mockGoogleDriver($this->fakeSocialiteUser());

        $this->get('/api/auth/google/callback')->assertRedirect();

        $this->mockGoogleDriver($this->fakeSocialiteUser([
            'name' => 'Jane Google Updated',
            'avatar' => 'https://lh3.googleusercontent.com/avatar2.jpg',
        ]));

        $this->get('/api/auth/google/callback')->assertRedirect();

        $this->assertEquals(1, User::where('email', 'jane@gmail.com')->count());
    }

    public function test_socialite_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('socialite.google.redirect'));
        $this->assertTrue(Route::has('socialite.google.callback'));
    }
}
