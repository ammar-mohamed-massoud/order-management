<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Inventory\InventoryService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = $request->user()->orders()
            ->with('items')
            ->when(isset($filters['status']), fn($query) => $query->where('status', $filters['status']))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->orderRules(true));

        $order = DB::transaction(function () use ($request, $data): Order {
            $order = $request->user()->orders()->create([
                'customer_name' => $data['customer_name'] ?? null,
                'customer_email' => $data['customer_email'] ?? null,
                'status' => 'pending',
                'total_amount' => '0.00',
            ]);

            $this->replaceItems($order, $data['items'], false);

            return $order->load('items');
        });

        return response()->json(['data' => $order], 201);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return response()->json([
            'data' => $this->findOrder($request, $order)->load('items'),
        ]);
    }

    public function update(Request $request, int $order): JsonResponse
    {
        $data = $request->validate($this->orderRules(false));

        $updated = DB::transaction(function () use ($request, $order, $data): Order {
            $model = $request->user()->orders()->with('items')->lockForUpdate()->findOrFail($order);

            // Stock is held for every order except cancelled ones; capture this before the status changes.
            $holdsStock = $model->status !== 'cancelled';
            $items = $data['items'] ?? $model->items->map(fn($item) => [
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->all();

            $model->fill(collect($data)->only(['customer_name', 'customer_email', 'status'])->all());
            $model->save();

            $this->replaceItems($model, $items, $holdsStock);

            return $model->load('items');
        });

        return response()->json(['data' => $updated]);
    }

    public function destroy(Request $request, int $order): JsonResponse
    {
        $deleted = DB::transaction(function () use ($request, $order): bool {
            $model = $request->user()->orders()->with('items')->lockForUpdate()->findOrFail($order);

            if ($model->payments()->exists()) {
                return false;
            }

            $model->delete();

            return true;
        });

        if (! $deleted) {
            return response()->json([
                'message' => 'Orders with associated payments cannot be deleted.',
            ], 409);
        }

        return response()->json(null, 204);
    }

    private function findOrder(Request $request, int $order): Order
    {
        return $request->user()->orders()->findOrFail($order);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function orderRules(bool $creating): array
    {
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'customer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'cancelled'])],
            'items' => [$presence, 'array', 'min:1', 'max:100'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01', 'max:1000000', 'decimal:0,2'],
        ];
    }

    /**
     * Replaces the order's items, returning previously held stock and reserving the new quantities.
     *
     * @param  array<int, array{product_name: string, quantity: int, unit_price: numeric-string|int|float}>  $items
     */
    private function replaceItems(Order $order, array $items, bool $releasePrevious): void
    {
        $order->items()->delete();
        $totalCents = 0;
        $rows = [];

        foreach ($items as $item) {
            $unitPrice = number_format((float) $item['unit_price'], 2, '.', '');
            $totalCents += (int) str_replace('.', '', $unitPrice) * (int) $item['quantity'];
            $rows[] = [
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
            ];
        }

        $order->items()->createMany($rows);
        $order->update([
            'total_amount' => intdiv($totalCents, 100) . '.' . str_pad((string) ($totalCents % 100), 2, '0', STR_PAD_LEFT),
        ]);
    }
}
