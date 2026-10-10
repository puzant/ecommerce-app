<?php

namespace App\Http\Controllers;

use App\Services\CheckoutService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function store(
        Request $request,
        CheckoutService $checkoutService
    ) {
        $validated = $request->validate([
            'promotion_code' => ['nullable', 'string'],
        ]);

        $order = $checkoutService->checkout(
            $request->user(),
            $validated['promotion_code'] ?? null
        );

        return response()->json([
            'message' => 'Order created successfully.',
            'order' => $order,
        ], 201);
    }
}