<?php

namespace App\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

class JwtTokenService
{
    public function issue(User $user): string
    {
        $now = time();
        $ttl = max(1, (int) config('jwt.ttl_minutes')) * 60;

        return JWT::encode([
            'iss' => (string) config('app.url'),
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $this->secret(), 'HS256');
    }

    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }

    public function expiresIn(): int
    {
        return max(1, (int) config('jwt.ttl_minutes')) * 60;
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET or APP_KEY must be configured.');
        }

        return $secret;
    }
}
