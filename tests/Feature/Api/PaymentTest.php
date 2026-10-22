<?php

namespace Tests\Feature\Api;

use App\Auth\JwtTokenService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_gateway_processes_confirmed_order(): void
    {
        $user = User::factory()->create();
        $order = $this->createConfirmedOrder($user);

        $response = $this->authenticate($user)->postJson('/api/payments', [
            'order_id' => $order->id,
            'payment_method' => 'paypal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'successful')
            ->assertJsonPath('data.payment_method', 'paypal')
            ->assertJsonPath('data.amount', '25.00')
            ->assertJsonPath('data.order.id', $order->id);

        $this->authenticate($user)
            ->getJson("/api/payments?order_id={$order->id}")
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_payment_requires_confirmed_order_and_a_configured_gateway(): void
    {
        $user = User::factory()->create();
        $pendingOrder = $this->createConfirmedOrder($user, 'pending');

        $this->authenticate($user)->postJson('/api/payments', [
            'order_id' => $pendingOrder->id,
            'payment_method' => 'credit_card',
        ])->assertConflict();

        $confirmedOrder = $this->createConfirmedOrder($user);

        $this->authenticate($user)->postJson('/api/payments', [
            'order_id' => $confirmedOrder->id,
            'payment_method' => 'bank_transfer',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('payment_method');

        $this->assertDatabaseCount('payments', 0);
    }

    private function authenticate(User $user): static
    {
        return $this->withToken(app(JwtTokenService::class)->issue($user));
    }

    private function createConfirmedOrder(User $user, string $status = 'confirmed'): Order
    {
        $order = $user->orders()->create([
            'status' => $status,
            'total_amount' => '25.00',
        ]);

        return $order;
    }
}
