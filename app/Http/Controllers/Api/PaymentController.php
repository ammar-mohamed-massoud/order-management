<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'order_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $payments = $request->user()->payments()
            ->with('order')
            ->when(isset($filters['order_id']), fn ($query) => $query->where('order_id', $filters['order_id']))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return response()->json($payments);
    }

    public function store(Request $request, PaymentGatewayManager $gateways): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'max:40'],
        ]);

        $payment = DB::transaction(function () use ($request, $data, $gateways): Payment {
            $order = $request->user()->orders()->lockForUpdate()->findOrFail($data['order_id']);

            if ($order->status !== 'confirmed') {
                abort(409, 'Payments can only be processed for confirmed orders.');
            }

            $payment = $request->user()->payments()->create([
                'order_id' => $order->id,
                'payment_method' => $data['payment_method'],
                'status' => 'pending',
                'amount' => $order->total_amount,
            ]);

            $result = $gateways->process($data['payment_method'], $order);

            $payment->update([
                'status' => $result->status,
                'transaction_reference' => $result->transactionReference,
            ]);

            return $payment->load('order');
        });

        return response()->json(['data' => $payment], 201);
    }
}
