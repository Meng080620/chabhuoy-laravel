<?php

namespace App\Http\Controllers\Api\Customer;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\LoginRequest;
use App\Http\Requests\Customer\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * A valid bcrypt hash used to equalize response time when the email is
     * unknown. Without this, the "no such user" path skips Hash::check and
     * returns faster, letting an attacker enumerate registered emails by
     * timing. Cost matches the app's bcrypt rounds (12).
     */
    private const TIMING_SAFE_HASH = '$2y$12$Ike4BJdSHxIMNO24FkU/aeAeYtTevmbYvshcI703G0Z8krhpNWx1e';

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => UserRole::Customer,
        ]);

        return $this->tokenResponse($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        // Always run a hash comparison, even when the user is missing, so the
        // response time can't be used to enumerate registered emails.
        $passwordMatches = Hash::check($data['password'], $user?->password ?? self::TIMING_SAFE_HASH);

        if (! $user || ! $passwordMatches) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->tokenResponse($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        $token = $user->createToken('api', $user->role->abilities())->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ], $status);
    }
}
