<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user (email + password).
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        $this->createDefaultGym($user, $input['name']);

        return $user;
    }

    /**
     * Find or create a user from a Socialite provider.
     *
     * @param  array{name: string, email: string, provider: string, provider_id: string, avatar?: string}  $input
     *
     * @throws ValidationException
     */
    public function createFromSocialite(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'provider' => ['required', 'string'],
            'provider_id' => ['required', 'string'],
            'avatar' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $existingUser = User::where('email', $input['email'])->first();

        if ($existingUser) {
            $existingUser->update([
                'provider' => $input['provider'],
                'provider_id' => $input['provider_id'],
                'avatar' => $input['avatar'] ?? $existingUser->avatar,
            ]);

            return $existingUser;
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'provider' => $input['provider'],
            'provider_id' => $input['provider_id'],
            'avatar' => $input['avatar'] ?? null,
        ]);

        $user->markEmailAsVerified();

        $this->createDefaultGym($user, $input['name']);

        return $user;
    }

    private function createDefaultGym(User $user, string $ownerName): void
    {
        $user->gym()->create([
            'name' => $ownerName."'s Gym",
            'status' => 'active',
        ]);
    }
}
