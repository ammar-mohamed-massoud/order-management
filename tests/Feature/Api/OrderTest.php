<?php

namespace Tests\Feature\Api;

use App\Auth\JwtTokenService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_calculates_total_and_paginates_results(): void
    {
        $user = User::factory()->create();

        $response = $this->authenticate($user)->postJson('/api/orders', [
            'customer_name' => 'Sam Customer',
            'customer_email' => 'sam@example.com',
            'items' => [
                ['product_name' => 'Notebook', 'quantity' => 2, 'unit_price' => '12.50'],
                ['product_name' => 'Pen', 'quantity' => 3, 'unit_price' => '1.20'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_amount', '28.60')
            ->assertJsonCount(2, 'data.items');

        $this->authenticate($user)
            ->getJson('/api/orders?status=pending&per_page=1')
            ->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 1);
    }

    public function test_order_update_recalculates_items_and_delete_is_blocked_after_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $this->authenticate($user)->patchJson("/api/orders/{$order->id}", [
            'status' => 'confirmed',
            'items' => [
                ['product_name' => 'Updated product', 'quantity' => 4, 'unit_price' => '3.25'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.total_amount', '13.00')
            ->assertJsonPath('data.status', 'confirmed');

        $order->payments()->create([
            'user_id' => $user->id,
            'payment_method' => 'credit_card',
            'status' => 'successful',
            'amount' => '13.00',
        ]);

        $this->authenticate($user)
            ->deleteJson("/api/orders/{$order->id}")
            ->assertConflict();
    }

    public function test_user_cannot_read_another_users_order(): void
    {
        $order = $this->createOrder(User::factory()->create());

        $otherUser = User::factory()->create();
        $this->authenticate($otherUser)
            ->getJson("/api/orders/{$order->id}")
            ->assertNotFound();
    }

    private function authenticate(User $user): static
    {
        return $this->withToken(app(JwtTokenService::class)->issue($user));
    }

    private function createOrder(User $user): Order
    {
        $order = $user->orders()->create([
            'status' => 'pending',
            'total_amount' => '3.25',
        ]);
        $order->items()->create([
            'product_name' => 'Product',
            'quantity' => 1,
            'unit_price' => '3.25',
        ]);

        return $order;
    }
}
