<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_jwt(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ammar Example',
            'email' => 'ammar@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'ammar@example.com')
            ->assertJsonStructure(['access_token', 'expires_in']);

        $this->withToken($response->json('access_token'))
            ->getJson('/api/orders')
            ->assertOk();
    }

    public function test_login_rejects_incorrect_credentials(): void
    {
        User::factory()->create([
            'email' => 'ammar@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'ammar@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_api_rejects_missing_or_invalid_jwt(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();

        $this->withToken('not-a-token')
            ->getJson('/api/orders')
            ->assertUnauthorized();
    }
}
