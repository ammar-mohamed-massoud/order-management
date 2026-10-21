<?php

namespace App\Http\Controllers\Api;

use App\Auth\JwtTokenService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, JwtTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->create($data);

        return $this->authenticatedResponse($user, $tokens, 201);
    }

    public function login(Request $request, JwtTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return $this->authenticatedResponse($user, $tokens);
    }

    private function authenticatedResponse(User $user, JwtTokenService $tokens, int $status = 200): JsonResponse
    {
        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $tokens->issue($user),
            'expires_in' => $tokens->expiresIn(),
            'user' => $user,
        ], $status);
    }
}
