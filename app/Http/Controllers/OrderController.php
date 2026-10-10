<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function cancel(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'This order cannot be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->load('items');

            foreach ($order->items as $item) {
                $product = $item->product()
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->increment(
                        'stock_quantity',
                        $item->quantity
                    );
                }
            }

            $order->update([
                'status' => 'cancelled',
            ]);
        });

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'order' => $order->fresh('items'),
        ]);
    }
}