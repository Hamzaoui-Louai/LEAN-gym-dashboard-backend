<?php

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly CreateNewUser $createNewUser) {}

    public function register(Request $request): JsonResponse
    {
        $user = $this->createNewUser->create($request->all());

        event(new Registered($user));

        $token = Auth::guard('api')->login($user);

        return response()->json($this->tokenResponse($token, $user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                'message' => __('auth.failed'),
            ], 401);
        }

        return response()->json($this->tokenResponse($token, Auth::guard('api')->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(Auth::guard('api')->user());
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(null, 204);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['status' => 'verification-link-sent'], 202);
    }

    private function tokenResponse(string $token, $user): array
    {
        $ttl = config('jwt.ttl');

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttl ? $ttl * 60 : null,
            'user' => $user,
        ];
    }
}
