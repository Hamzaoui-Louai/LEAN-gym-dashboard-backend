<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function __construct(private readonly CreateNewUser $createNewUser) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback(): RedirectResponse
    {
        $socialiteUser = Socialite::driver('google')->stateless()->user();

        $user = $this->createNewUser->createFromSocialite([
            'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'User',
            'email' => $socialiteUser->getEmail(),
            'provider' => 'google',
            'provider_id' => $socialiteUser->getId(),
            'avatar' => $socialiteUser->getAvatar(),
        ]);

        $token = Auth::guard('api')->login($user);

        $frontendUrl = config('app.frontend_url');

        return redirect()->away(
            $frontendUrl.'/auth/google/callback?token='.$token.'&expires_in='.config('jwt.ttl') * 60,
        );
    }
}
