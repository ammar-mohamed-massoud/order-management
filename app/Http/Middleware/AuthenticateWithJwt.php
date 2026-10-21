<?php

namespace App\Http\Middleware;

use App\Auth\JwtTokenService;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class AuthenticateWithJwt
{
    public function __construct(private readonly JwtTokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return $this->unauthorized();
        }

        try {
            $claims = $this->tokens->decode($token);
        } catch (UnexpectedValueException) {
            return $this->unauthorized();
        }

        if (
            ! isset($claims->sub, $claims->iss)
            || (string) $claims->iss !== (string) config('app.url')
            || ! ctype_digit((string) $claims->sub)
        ) {
            return $this->unauthorized();
        }

        $user = User::query()->find($claims->sub);

        if ($user === null) {
            return $this->unauthorized();
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
