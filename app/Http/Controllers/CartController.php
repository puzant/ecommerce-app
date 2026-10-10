<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;

class CartController extends Controller
{

    public function index(Request $request) 
    {
        $cart = $request->user()
            ->cart()
            ->with('items.product')
            ->first();

        if (!$cart) {
            return response()->json([
                'message' => 'Cart is empty.',
                'items' => [],
            ]);
        }

        return response()->json([
            'id' => $cart->id,
            'items' => $cart->items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $cart = $user->cart;

        if (!$cart) {
            $cart = $user->cart()->create();
        }

        $product = Product::findOrFail($validated['product_id']);

        $cartItem = $cart->items()
            ->where('product_id', $validated['product_id'])
            ->first();
        
        $newQuantity = $validated['quantity'];

        if ($cartItem) {
            $newQuantity += $cartItem->quantity;
        }

        if ($product->stock_quantity < $newQuantity) {
            return response()->json([
                'message' => 'Insufficient stock.'
            ], 422);
        }

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $newQuantity,
            ]);
        } else {
            $cartItem = $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart.',
            'item' => $cartItem,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1']
        ]);

        $cart = $request->user()->cart;

        if (!$cart) {
            return response()->json([
                'message' => 'Cart is empty.'
            ], 404);
        }

        $cartItem = $cart->items()
            ->with('product')
            ->findOrFail($id);

        if ($validated['quantity'] > $cartItem->product->stock_quantity) {
            return response()->json([
                'message' => 'Insufficient stock.'
            ], 422);
        }

        $cartItem->update([
            'quantity' => $validated['quantity'],
        ]);

        return response()->json([
            'message' => 'Cart item updated.',
            'item' => $cartItem->load('product'),
        ]);
    }

    public function destroy(Request $request, $id) 
    {
        $cart = $request->user()->cart;

        if (!$cart) {
            return response()->json([
                'message' => 'Cart is empty.'
            ], 404);
        }

        $cartItem = $cart->items()->findOrFail($id);

        $cartItem->delete();

        return response()->json([
            'message' => 'Cart item removed.'
        ]);
    }
}
