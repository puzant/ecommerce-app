<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function checkout(User $user, ?string $promotionCode = null)
    {
        return DB::transaction(function () use ($user, $promotionCode) {

            // 1. Get the user's cart
            $cart = $user->cart()
                ->with('items')
                ->first();

            if (!$cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Cart is empty.'],
                ]);
            }

            // 2. Recalculate subtotal using current product prices
            // and lock product rows for concurrency safety.
            $subtotal = 0;
            $products = [];

            foreach ($cart->items as $item) {
                $product = $item->product()
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($product->stock_quantity < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => [
                            "Insufficient stock for {$product->name}."
                        ],
                    ]);
                }

                $subtotal += $product->price * $item->quantity;

                $products[$item->id] = $product;
            }

            // 3. Validate promotion and calculate discount
            $promotion = null;
            $discount = 0;

            if ($promotionCode) {
                $promotionService = app(PromotionService::class);

                $promotion = $promotionService->validate(
                    $promotionCode,
                    $subtotal,
                    $user
                );

                if ($promotion->type === 'percentage') {
                    $discount = (int) floor(
                        $subtotal * ($promotion->value / 100)
                    );
                }

                if ($promotion->type === 'fixed') {
                    $discount = $promotion->value;
                }

                if ($promotion->maximum_discount !== null) {
                    $discount = min(
                        $discount,
                        $promotion->maximum_discount
                    );
                }

                // Discount can never exceed the subtotal.
                $discount = min($discount, $subtotal);
            }

            // 4. Calculate final total
            $total = $subtotal - $discount;

            // 5. Reduce inventory
            foreach ($cart->items as $item) {
                $product = $products[$item->id];

                $product->decrement(
                    'stock_quantity',
                    $item->quantity
                );
            }

            // 6. Create order
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'promotion_code' => $promotion?->code,
            ]);

            // 7. Create order items using product snapshots
            foreach ($cart->items as $item) {
                $product = $products[$item->id];

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'quantity' => $item->quantity,
                ]);
            }

            // 8. Record promotion usage
            if ($promotion) {
                PromotionUsage::create([
                    'promotion_id' => $promotion->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                ]);
            }

            // 9. Clear the cart
            $cart->items()->delete();

            // 10. Return the completed order
            return $order->load('items');
        });
    }
}